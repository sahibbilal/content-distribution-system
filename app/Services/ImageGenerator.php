<?php

namespace App\Services;

use App\Models\AiIntegration;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Makes a picture from a prompt with the OpenAI image API (key and model are saved in Admin > AI integrations) */
class ImageGenerator
{
    public function generate(User $user, string $prompt): Media
    {
        $integration = AiIntegration::where('provider', 'openai')->where('enabled', true)->first();
        if (!$integration?->api_key) {
            throw new \RuntimeException('Connect and enable OpenAI in Admin > AI integrations to make images. Its Model field is the image model (e.g. gpt-image-1).');
        }

        $response = Http::timeout(180)->withToken($integration->api_key)->post('https://api.openai.com/v1/images/generations', [
            'model' => $integration->model ?: 'gpt-image-1',
            'prompt' => $prompt,
            'size' => '1024x1024',
            'n' => 1,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($response->json('error.message') ?: 'The image provider rejected the request (HTTP ' . $response->status() . ').');
        }

        $item = $response->json('data.0') ?? [];
        $bytes = !empty($item['b64_json']) ? base64_decode($item['b64_json']) : (!empty($item['url']) ? Http::timeout(60)->get($item['url'])->throw()->body() : null);
        if (!$bytes) {
            throw new \RuntimeException('The image provider returned no picture.');
        }

        $filename = Str::uuid() . '.png';
        $path = "uploads/{$filename}";
        Storage::disk('public')->put($path, $bytes);

        return Media::create([
            'user_id' => $user->id,
            'filename' => $filename,
            'original_filename' => 'ai-image.png',
            'mime_type' => 'image/png',
            'size' => strlen($bytes),
            'path' => $path,
            'type' => 'image',
        ]);
    }
}
