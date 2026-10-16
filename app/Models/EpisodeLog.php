<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpisodeLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['episode_id', 'step', 'status', 'message', 'payload'];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
