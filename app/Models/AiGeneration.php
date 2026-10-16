<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'project_id', 'prompt', 'long', 'short', 'hashtags', 'hook', 'post_id'];

    protected $casts = [
        'hashtags' => 'array',
        'created_at' => 'datetime',
    ];

    /** Remember a draft the assistant wrote */
    public static function record(int $userId, ?int $projectId, string $prompt, string $long, ?string $short, array $hashtags): self
    {
        $firstLine = trim(strtok(trim($long), "\n") ?: $long);

        return static::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'prompt' => $prompt,
            'long' => $long,
            'short' => $short,
            'hashtags' => $hashtags,
            'hook' => mb_substr($firstLine, 0, 250),
        ]);
    }
}
