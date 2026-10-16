<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Settings for the content pipeline (cloud worker, ElevenLabs, Series A times), editable on the Admin page.
 * A saved value overrides the .env default by being written into config() at boot and before every queued job,
 * so the rest of the code keeps reading config('services.worker.url') and friends.
 */
class PipelineSettings
{
    /** key => [config path, secret?, type (string, int, float or list)] */
    public const FIELDS = [
        'worker_url' => ['services.worker.url', false, 'string'],
        'worker_secret' => ['services.worker.secret', true, 'string'],
        'elevenlabs_key' => ['services.elevenlabs.key', true, 'string'],
        'elevenlabs_voice_id' => ['services.elevenlabs.voice_id', false, 'string'],
        'elevenlabs_model_id' => ['services.elevenlabs.model_id', false, 'string'],
        'elevenlabs_stability' => ['services.elevenlabs.voice_settings.stability', false, 'float'],
        'elevenlabs_similarity_boost' => ['services.elevenlabs.voice_settings.similarity_boost', false, 'float'],
        'elevenlabs_style' => ['services.elevenlabs.voice_settings.style', false, 'float'],
        'series_research_at' => ['series.a.research_at', false, 'string'],
        'series_publish_at' => ['series.a.publish_at', false, 'string'],
        'series_timezone' => ['series.a.timezone', false, 'string'],
        'series_shorts' => ['series.a.shorts', false, 'int'],
        'series_skip_before_minutes' => ['series.a.skip_before_minutes', false, 'int'],
        'series_user_id' => ['series.a.user_id', false, 'int'],
        'series_platforms_long' => ['series.a.platforms.long', false, 'list'],
        'series_platforms_short' => ['series.a.platforms.short', false, 'list'],
        'series_limit_researching' => ['series.a.limits.researching', false, 'int'],
        'series_limit_voicing' => ['series.a.limits.voicing', false, 'int'],
        'series_limit_editing' => ['series.a.limits.editing', false, 'int'],
    ];

    /** Saved values by key (secrets decrypted). Empty when the table does not exist yet (before migrating). */
    public static function saved(): array
    {
        try {
            $rows = DB::table('pipeline_settings')->pluck('value', 'key');
        } catch (\Throwable $e) {
            return [];
        }

        $saved = [];
        foreach ($rows as $key => $value) {
            if (!isset(self::FIELDS[$key]) || $value === null || $value === '') {
                continue;
            }
            try {
                $saved[$key] = self::FIELDS[$key][1] ? Crypt::decryptString($value) : $value;
            } catch (\Throwable $e) {
                // an unreadable secret (e.g. APP_KEY changed) is ignored, so the .env value is used instead
            }
        }

        return $saved;
    }

    /** The built-in (config file / .env) value of every setting, remembered before a saved value replaces it */
    private static array $defaults = [];

    /** Write the saved values into config() */
    public static function apply(): void
    {
        if (!self::$defaults) {
            foreach (self::FIELDS as $key => [$path]) {
                self::$defaults[$key] = config($path);
            }
        }

        foreach (self::saved() as $key => $value) {
            [$path, , $type] = self::FIELDS[$key];
            config([$path => match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                'list' => array_values(array_filter(array_map('trim', explode(',', $value)))), // saved as "a,b"
                default => $value,
            }]);
        }
    }

    /** Save changes: blank secrets are left alone, `clear` (and a blank normal field) goes back to the built-in default */
    public static function save(array $values, array $clear = []): void
    {
        foreach ($values as $key => $value) {
            if (!isset(self::FIELDS[$key])) {
                continue;
            }
            $value = is_array($value) ? implode(',', array_filter(array_map('trim', $value))) : (is_string($value) ? trim($value) : $value);
            if ($value === null || $value === '') {
                if (!self::FIELDS[$key][1]) {
                    $clear[] = $key; // a blank normal field means "use the default"
                }
                continue;
            }
            DB::table('pipeline_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => self::FIELDS[$key][1] ? Crypt::encryptString((string) $value) : (string) $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if ($clear) {
            $keys = array_intersect($clear, array_keys(self::FIELDS));
            DB::table('pipeline_settings')->whereIn('key', $keys)->delete();
            // this process keeps running with the old value in config() unless it is put back
            foreach ($keys as $key) {
                config([self::FIELDS[$key][0] => self::$defaults[$key] ?? null]);
            }
        }
    }
}
