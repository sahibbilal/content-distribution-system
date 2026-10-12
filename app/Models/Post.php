<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'content_html',
        'tags',
        'platforms',
        'status',
        'scheduled_at',
        'published_at',
        'platform_statuses',
        'thumbnail_media_id',
    ];

    protected $casts = [
        'tags' => 'array',
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

