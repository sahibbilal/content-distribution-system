<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'type' => 'nullable|in:image,video,dataset',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $type = $request->input('type') ?? $this->detectType($file->getMimeType());

        if ($type === 'image') {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'error' => 'Unsupported image file type: .' . $extension,
                ], 400);
            }
        } elseif ($type === 'video') {
            $allowedExtensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'error' => 'Unsupported video file type: .' . $extension,
                ], 400);
            }
        }

        $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('uploads', $filename, 'public');

        $media = Media::create([
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
            'type' => $request->type ?? $this->detectType($file->getMimeType(), $extension),
        ]);

        return response()->json([
            'id' => $media->id,
            'filename' => $media->original_filename,
            'url' => Storage::url($path),
            'type' => $media->type,
        ], 201);
    }

    private function detectType($mimeType, $extension = null)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif ($extension && in_array(strtolower($extension), ['ipynb', 'csv', 'json', 'parquet', 'xlsx', 'xls', 'tsv', 'zip', 'tar', 'gz'])) {
            return 'dataset';
        } else {
            return 'dataset';
        }
    }
}

