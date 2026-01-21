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
        'platforms',
        'status',
        'scheduled_at',
        'published_at',
        'platform_statuses',
    ];

    protected $casts = [
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

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}

