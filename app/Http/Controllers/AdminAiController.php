<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminAiController extends Controller
{
    private const PROVIDERS = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI',
    ];

    public function index()
    {
        $saved = AiIntegration::all()->keyBy('provider');

        return collect(self::PROVIDERS)
            ->map(fn ($label, $provider) => $this->present($provider, $saved->get($provider)))
            ->values();
    }

    public function update(Request $request, string $provider)
    {
        $this->assertProvider($provider);

        $data = $request->validate([
            'model' => 'nullable|string|max:100',
            'enabled' => 'required|boolean',
            'api_key' => 'nullable|string|max:500',
            'clear_key' => 'boolean',
        ]);

        $integration = AiIntegration::firstOrNew(['provider' => $provider]);
        $integration->model = $data['model'] ?? null;
        $integration->enabled = $data['enabled'];

        if (!empty($data['clear_key'])) {
            $integration->api_key = null;
        } elseif (!empty($data['api_key'])) {
            $integration->api_key = $data['api_key'];
        }

        // An integration can't be enabled without a key
        if (!$integration->api_key) {
            $integration->enabled = false;
        }

        $integration->save();

        return $this->present($provider, $integration);
    }

    public function test(string $provider)
    {
        $this->assertProvider($provider);

        $integration = AiIntegration::where('provider', $provider)->first();

        if (!$integration || !$integration->api_key) {
            return response()->json(['success' => false, 'message' => 'Save an API key first.'], 422);
        }

        try {
            $response = $provider === 'anthropic'
                ? Http::timeout(20)->withHeaders([
                    'x-api-key' => $integration->api_key,
                    'anthropic-version' => '2023-06-01',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $integration->model ?: 'claude-haiku-5-5',
                    'max_tokens' => 8,
                    'messages' => [['role' => 'user', 'content' => 'Reply with OK']],
                ])
                : Http::timeout(20)->withToken($integration->api_key)->get('https://api.openai.com/v1/models');
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Could not reach the provider: ' . $e->getMessage()]);
        }

        if ($response->successful()) {
            return response()->json(['success' => true, 'message' => 'Connection works.']);
        }

        return response()->json([
            'success' => false,
            'message' => $response->json('error.message') ?: 'The provider rejected the request (HTTP ' . $response->status() . ').',
        ]);
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(isset(self::PROVIDERS[$provider]), 404);
    }

    private function present(string $provider, ?AiIntegration $integration): array
    {
        $key = $integration?->api_key;

        return [
            'provider' => $provider,
            'label' => self::PROVIDERS[$provider],
            'model' => $integration?->model,
            'enabled' => (bool) $integration?->enabled,
            'has_key' => (bool) $key,
            'key_hint' => $key ? '****' . substr($key, -4) : null,
        ];
    }
}
