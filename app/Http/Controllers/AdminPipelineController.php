<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\User;
use App\Services\ClaudeWorkerClient;
use App\Support\PipelineSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Admin > Content pipeline: the cloud worker, ElevenLabs and Series A settings, with connection tests */
class AdminPipelineController extends Controller
{
    public function show()
    {
        return response()->json($this->present());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'values' => 'required|array',
            'values.worker_url' => 'nullable|url|max:255',
            'values.worker_secret' => 'nullable|string|max:255',
            'values.elevenlabs_key' => 'nullable|string|max:255',
            'values.elevenlabs_voice_id' => 'nullable|string|max:100',
            'values.elevenlabs_model_id' => 'nullable|string|max:100',
            'values.elevenlabs_stability' => 'nullable|numeric|between:0,1',
            'values.elevenlabs_similarity_boost' => 'nullable|numeric|between:0,1',
            'values.elevenlabs_style' => 'nullable|numeric|between:0,1',
            'values.series_research_at' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'values.series_publish_at' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'values.series_timezone' => ['nullable', 'string', 'in:' . implode(',', \DateTimeZone::listIdentifiers())],
            'values.series_shorts' => 'nullable|integer|between:0,6',
            'clear' => 'nullable|array',
            'clear.*' => 'string',
        ]);

        PipelineSettings::save($data['values'], $data['clear'] ?? []);
        PipelineSettings::apply();

        return response()->json($this->present());
    }

    /** Check one connection with the saved settings */
    public function test(string $service)
    {
        abort_unless(in_array($service, ['worker', 'elevenlabs'], true), 404);

        return response()->json($service === 'worker' ? $this->testWorker() : $this->testElevenLabs());
    }

    private function testWorker(): array
    {
        $url = config('services.worker.url');
        if (!$url || !config('services.worker.secret')) {
            return ['success' => false, 'message' => 'Save the worker URL and the shared secret first.'];
        }

        try {
            // the worker answers GET /health when the signature (of the empty body) matches the shared secret
            $res = Http::timeout(15)->withHeaders(['X-Signature' => ClaudeWorkerClient::sign('')])->get(rtrim($url, '/') . '/health');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach the worker: ' . $e->getMessage()];
        }

        return match (true) {
            $res->successful() => ['success' => true, 'message' => 'The worker answered and accepted the secret.'],
            in_array($res->status(), [401, 403], true) => ['success' => false, 'message' => 'The worker rejected the secret. It must match the secret saved on the worker.'],
            $res->status() === 404 => ['success' => false, 'message' => 'The worker has no /health route yet (see document 02).'],
            default => ['success' => false, 'message' => 'The worker answered with HTTP ' . $res->status() . '.'],
        };
    }

    private function testElevenLabs(): array
    {
        $key = config('services.elevenlabs.key');
        if (!$key) {
            return ['success' => false, 'message' => 'Save the ElevenLabs API key first.'];
        }

        $api = fn (string $path) => Http::timeout(20)->withHeaders(['xi-api-key' => $key])->get('https://api.elevenlabs.io/v1' . $path);
        $details = [];

        try {
            $account = $api('/user/subscription');
            if ($account->status() === 401 && ($account->json('detail.status') ?? '') !== 'missing_permissions') {
                return ['success' => false, 'message' => 'ElevenLabs rejected the API key.'];
            }
            if ($account->successful()) {
                $details['tier'] = $account->json('tier');
                $details['characters_used'] = $account->json('character_count');
                $details['characters_limit'] = $account->json('character_limit');
            } else {
                $details['account_note'] = 'This key cannot read the account, so the characters left are not shown (it needs the User read permission).';
            }

            $voiceId = config('services.elevenlabs.voice_id');
            if (!$voiceId) {
                return ['success' => false, 'message' => 'The key works, but no voice ID is saved yet.', 'details' => $details];
            }
            $voice = $api('/voices/' . rawurlencode($voiceId));
            if ($voice->status() === 404 || $voice->status() === 422) {
                return ['success' => false, 'message' => 'The key works, but the voice ID was not found in this account.', 'details' => $details];
            }
            if (!$voice->successful()) {
                return ['success' => false, 'message' => 'Could not read the voice (HTTP ' . $voice->status() . ').', 'details' => $details];
            }
            $details['voice'] = $voice->json('name');
            $details['voice_category'] = $voice->json('category');

            $models = $api('/models');
            if ($models->successful()) {
                $model = collect($models->json())->firstWhere('model_id', config('services.elevenlabs.model_id'));
                $details['model_found'] = (bool) $model;
                $details['urdu'] = $model ? collect($model['languages'] ?? [])->contains(fn ($l) => ($l['language_id'] ?? '') === 'ur') : null;
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach ElevenLabs: ' . $e->getMessage()];
        }

        $notes = [];
        if (($details['model_found'] ?? true) === false) {
            $notes[] = 'The model ID was not found.';
        } elseif (($details['urdu'] ?? null) === false) {
            $notes[] = 'This model does not list Urdu.';
        }

        return ['success' => true, 'message' => trim("Connected. Voice: {$details['voice']}. " . implode(' ', $notes)), 'details' => $details];
    }

    private function present(): array
    {
        $saved = PipelineSettings::saved();
        $fields = [];
        foreach (PipelineSettings::FIELDS as $key => [$path, $secret]) {
            $value = config($path);
            $fields[$key] = [
                'source' => array_key_exists($key, $saved) ? 'saved' : 'default',
                'value' => $secret ? null : $value,
                'has_value' => $value !== null && $value !== '',
                'hint' => $secret && $value ? '****' . substr((string) $value, -4) : null,
            ];
        }

        return ['fields' => $fields, 'checks' => $this->checks()];
    }

    /** "Is everything in place?" list shown at the top of the page and on the AI Content page */
    private function checks(): array
    {
        $age = function (string $key): ?int {
            $at = Cache::get($key);

            return $at ? now()->timestamp - (int) $at : null;
        };
        $ago = fn (?int $s) => $s === null ? 'never seen' : ($s < 90 ? 'seen just now' : 'seen ' . round($s / 60) . ' min ago');

        $scheduler = $age('pipeline:scheduler-ran');
        $queue = $age('pipeline:queue-ran');
        $sync = config('queue.default') === 'sync';
        $video = config('series.a.platforms');
        $owner = config('series.a.user_id') ? User::find(config('series.a.user_id')) : User::where('is_admin', true)->orderBy('id')->first();
        // the daily video is posted to the owner's platforms
        $connected = Platform::where('user_id', $owner?->id)->where('is_active', true)->pluck('platform_type')->unique()->all();

        return [
            ['key' => 'worker', 'label' => 'Claude worker', 'ok' => (bool) (config('services.worker.url') && config('services.worker.secret')),
                'detail' => config('services.worker.url') ?: 'URL and secret not set'],
            ['key' => 'elevenlabs', 'label' => 'ElevenLabs', 'ok' => (bool) (config('services.elevenlabs.key') && config('services.elevenlabs.voice_id')),
                'detail' => config('services.elevenlabs.key') ? (config('services.elevenlabs.voice_id') ? 'Key and voice set' : 'Voice ID not set') : 'API key not set'],
            ['key' => 'scheduler', 'label' => 'Scheduler (cron)', 'ok' => $scheduler !== null && $scheduler < 300,
                'detail' => $ago($scheduler) . '. The server must run "php artisan schedule:run" every minute.'],
            ['key' => 'queue', 'label' => 'Queue worker', 'ok' => $sync || ($queue !== null && $queue < 900),
                'detail' => $sync ? 'Queue is "sync": jobs run inside the request.' : $ago($queue) . '. A worker must run "php artisan queue:work".'],
            ['key' => 'platforms', 'label' => 'Platforms for video', 'ok' => (bool) array_intersect($connected, array_unique(array_merge($video['long'], $video['short']))),
                'detail' => implode(', ', array_intersect($connected, array_unique(array_merge($video['long'], $video['short'])))) ?: 'Connect YouTube, Facebook, Instagram or TikTok'],
            ['key' => 'mail', 'label' => 'Email notifications', 'ok' => !in_array(config('mail.default'), ['log', 'array'], true),
                'detail' => 'Mailer: ' . config('mail.default')],
            ['key' => 'owner', 'label' => 'Daily video owner', 'ok' => (bool) $owner, 'detail' => $owner ? $owner->email : 'No admin user found'],
        ];
    }
}
