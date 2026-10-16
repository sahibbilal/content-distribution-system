<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // the text is hashed so the same content can be found (and refused) quickly
        static::saving(function (Post $post) {
            if ($post->isDirty('content') || !$post->exists) {
                $post->content_hash = self::hashContent((string) $post->content);
            }
        });
    }

    /** Same text in another case or spacing counts as the same content; an empty text (media only) has no hash */
    public static function hashContent(string $content): ?string
    {
        $normal = trim(preg_replace('/\s+/u', ' ', mb_strtolower($content)));

        return $normal === '' ? null : sha1($normal);
    }

    public function series()
    {
        return $this->belongsTo(PostSeries::class, 'series_id');
    }

    protected $fillable = [
        'user_id',
        'series_id',
        'content',
        'content_html',
        'tags',
        'hashtags',
        'platform_overrides',
        'platforms',
        'status',
        'scheduled_at',
        'published_at',
        'platform_statuses',
        'thumbnail_media_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'hashtags' => 'array',
        'platform_overrides' => 'array',
        'platforms' => 'array',
        'platform_statuses' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    /** Custom cover image for the post's video (not part of the post's media list) */
    public function thumbnail()
    {
        return $this->belongsTo(Media::class, 'thumbnail_media_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}

