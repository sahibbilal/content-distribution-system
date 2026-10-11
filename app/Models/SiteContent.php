<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * All public site content: saved values, falling back to config/site_content.php defaults.
     */
    public static function current(): array
    {
        $saved = static::pluck('value', 'key');
        $content = [];

        foreach (config('site_content') as $key => $default) {
            $content[$key] = $saved->has($key) ? json_decode($saved[$key], true) : $default;
        }

        return $content;
    }

    /** One saved (or default) setting, e.g. SiteContent::setting('registration_open') */
    public static function setting(string $key): mixed
    {
        return static::current()[$key] ?? null;
    }

    public static function store(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
