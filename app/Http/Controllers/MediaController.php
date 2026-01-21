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
            'platform' => 'nullable|string', // Optional: specify platform for file type validation
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $platform = $request->input('platform');
        
        // Kaggle-specific file extensions
        $kaggleOnlyExtensions = ['ipynb', 'parquet', 'feather', 'arrow', 'avro', 'orc', 'h5', 'hdf5', 'pkl', 'pickle'];
        $isKaggleOnlyFile = in_array($extension, $kaggleOnlyExtensions);
        
        // Check if user has Kaggle platform connected
        $userHasKaggle = \App\Models\Platform::where('user_id', $request->user()->id)
            ->where('platform_type', 'kaggle')
            ->where('is_active', true)
            ->exists();
        
        // Validate file type based on platform
        if ($platform === 'kaggle' || ($isKaggleOnlyFile && $userHasKaggle)) {
            // Kaggle accepts various dataset file types
            $allowedExtensions = [
                'csv', 'json', 'jsonl', 'ipynb', 'zip', 'tar', 'gz', 'parquet', 
                'xlsx', 'xls', 'tsv', 'txt', 'sql', 'db', 'sqlite', 'h5', 'hdf5',
                'pkl', 'pickle', 'feather', 'arrow', 'avro', 'orc', 'xml', 'html'
            ];
            
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'error' => 'Unsupported file type for Kaggle: .' . $extension,
                    'message' => 'Kaggle accepts: CSV, JSON, Jupyter notebooks (.ipynb), ZIP, Parquet, Excel, and other dataset formats.',
                    'allowed_types' => $allowedExtensions,
                ], 400);
            }
        } elseif ($isKaggleOnlyFile && !$userHasKaggle) {
            // Kaggle-only file but user doesn't have Kaggle connected
            return response()->json([
                'error' => 'This file type (.' . $extension . ') is only supported for Kaggle',
                'message' => 'Please connect your Kaggle account in Settings to upload this file type.',
            ], 400);
        } else {
            // For other platforms, validate based on type
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

