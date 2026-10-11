<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Support\PlatformMediaRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Allowed uploads, keyed by the MIME type detected from the file's actual bytes
     * (never the client-supplied name or Content-Type). The stored extension comes from here too.
     */
    private const ALLOWED = [
        'image/jpeg' => ['type' => 'image', 'ext' => 'jpg'],
        'image/png' => ['type' => 'image', 'ext' => 'png'],
        'image/gif' => ['type' => 'image', 'ext' => 'gif'],
        'image/webp' => ['type' => 'image', 'ext' => 'webp'],
        'image/bmp' => ['type' => 'image', 'ext' => 'bmp'],
        'video/mp4' => ['type' => 'video', 'ext' => 'mp4'],
        'video/quicktime' => ['type' => 'video', 'ext' => 'mov'],
        'video/webm' => ['type' => 'video', 'ext' => 'webm'],
        'video/x-msvideo' => ['type' => 'video', 'ext' => 'avi'],
        'video/x-ms-wmv' => ['type' => 'video', 'ext' => 'wmv'],
        'video/x-flv' => ['type' => 'video', 'ext' => 'flv'],
        'video/x-matroska' => ['type' => 'video', 'ext' => 'mkv'],
        'audio/mpeg' => ['type' => 'audio', 'ext' => 'mp3'],
        'audio/wav' => ['type' => 'audio', 'ext' => 'wav'],
        'audio/x-wav' => ['type' => 'audio', 'ext' => 'wav'],
        'audio/ogg' => ['type' => 'audio', 'ext' => 'ogg'],
        'audio/mp4' => ['type' => 'audio', 'ext' => 'm4a'],
        'audio/x-m4a' => ['type' => 'audio', 'ext' => 'm4a'],
        'audio/aac' => ['type' => 'audio', 'ext' => 'aac'],
        'audio/flac' => ['type' => 'audio', 'ext' => 'flac'],
        'audio/x-flac' => ['type' => 'audio', 'ext' => 'flac'],
        'audio/webm' => ['type' => 'audio', 'ext' => 'weba'],
    ];

    private const MAX_IMAGE_PIXELS = 25_000_000; // guards against decompression bombs
    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
        ]);

        $file = $request->file('file');

        if (!$file->isValid()) {
            return $this->reject('The upload failed or was corrupted. Please try again.');
        }

        // Content-based detection (finfo reads the file's magic bytes)
        $mime = $file->getMimeType();
        $kind = self::ALLOWED[$mime] ?? null;

        if (!$kind) {
            return $this->reject('This file is not a supported image, video or audio file. Its real content type is ' . ($mime ?: 'unknown') . ', whatever the file name says.');
        }

        if ($kind['type'] === 'image') {
            // Must also parse as a real image, and not be absurdly large
            $info = @getimagesize($file->getRealPath());
            if ($info === false || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1) {
                return $this->reject('This image file is corrupt or not a valid ' . strtoupper($kind['ext']) . '.');
            }
            if ($info[0] * $info[1] > self::MAX_IMAGE_PIXELS || $file->getSize() > self::MAX_IMAGE_BYTES) {
                return $this->reject('This image is too large (max 20MB and 25 megapixels).');
            }
            // Fully decode it: catches truncated or tampered files whose header still looks valid
            if (!@imagecreatefromstring(file_get_contents($file->getRealPath()))) {
                return $this->reject('This image file is corrupt or not a valid ' . strtoupper($kind['ext']) . '.');
            }
        }

        $filename = Str::uuid() . '.' . $kind['ext'];
        $path = $file->storeAs('uploads', $filename, 'public');

        $media = Media::create([
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'path' => $path,
            'type' => $kind['type'],
        ]);

        return response()->json([
            'id' => $media->id,
            'filename' => $media->original_filename,
            'url' => Storage::url($path),
            'type' => $media->type,
        ], 201);
    }

    /** The signed-in user's own files only, newest first (the media library) */
    public function index(Request $request)
    {
        $media = Media::where('user_id', $request->user()->id)
            ->with('post:id,status')
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        $media->getCollection()->transform(fn (Media $m) => [
            'id' => $m->id,
            'filename' => $m->original_filename,
            'url' => Storage::url($m->path),
            'type' => $m->type,
            'size' => (int) $m->size,
            'created_at' => $m->created_at,
            // a file waiting on a scheduled post can't be deleted yet
            'in_use' => $m->post?->status === 'scheduled',
        ]);

        return response()->json($media);
    }

    public function destroy(Request $request, $id)
    {
        $media = Media::where('user_id', $request->user()->id)->with('post:id,status')->findOrFail($id);

        $usedAsThumbnail = Post::where('thumbnail_media_id', $media->id)->where('status', 'scheduled')->exists();
        if ($media->post?->status === 'scheduled' || $usedAsThumbnail) {
            return response()->json(['message' => 'This file is attached to a scheduled post. Edit or delete that post first.'], 422);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'File deleted']);
    }

    /**
     * Check the chosen platforms against the uploaded files, so the UI can show problems
     * (e.g. an image on YouTube) before the user publishes.
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'platforms' => 'present|array',
            'platforms.*' => 'integer',
            'media' => 'present|array',
            'media.*' => 'integer',
        ]);

        return response()->json([
            'issues' => (object) PlatformMediaRules::forPost($request->user()->id, $data['platforms'], $data['media']),
        ]);
    }

    private function reject(string $message)
    {
        return response()->json([
            'error' => $message,
            'allowed_types' => array_values(array_unique(array_column(self::ALLOWED, 'ext'))),
        ], 422);
    }
}
