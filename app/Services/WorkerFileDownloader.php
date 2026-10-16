<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Media;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Downloads a file the worker produced into the user's media library */
class WorkerFileDownloader
{
    /** @param Media[] $created  collects every media row made, so a failed batch can be cleaned up */
    public static function download(Episode $e, ?string $url, string $mime, string $ext, array &$created): Media
    {
        if (!$url) {
            throw new \RuntimeException('The worker did not return all the expected files.');
        }

        // only files from our own worker, never an arbitrary address
        $workerHost = parse_url((string) config('services.worker.url'), PHP_URL_HOST);
        if (!$workerHost || parse_url($url, PHP_URL_HOST) !== $workerHost) {
            throw new \RuntimeException('Refusing to download a file that is not from the worker.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ep');
        try {
            Http::timeout(900)->withOptions(['sink' => $tmp])->get($url)->throw();
            if (filesize($tmp) === 0) {
                throw new \RuntimeException('The worker returned an empty file.');
            }

            $filename = Str::uuid() . '.' . $ext;
            $path = Storage::disk('public')->putFileAs('uploads', new File($tmp), $filename);
            $media = Media::create([
                'user_id' => $e->user_id,
                'filename' => $filename,
                'original_filename' => basename(parse_url($url, PHP_URL_PATH) ?: $filename),
                'mime_type' => $mime,
                'size' => filesize($tmp),
                'path' => $path,
                'type' => str_starts_with($mime, 'video/') ? 'video' : 'image',
            ]);
            $created[] = $media;

            return $media;
        } finally {
            @unlink($tmp);
        }
    }

    /** Remove media rows and files, e.g. after a half-finished import */
    public static function discard(array $media): void
    {
        foreach ($media as $m) {
            Storage::disk('public')->delete($m->path);
            $m->delete();
        }
    }
}
