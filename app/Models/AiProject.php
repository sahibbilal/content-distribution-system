<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProject extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'instructions', 'platform_ids', 'options'];

    protected $casts = [
        'platform_ids' => 'array',
        'options' => 'array',
    ];

    public function generations()
    {
        return $this->hasMany(AiGeneration::class, 'project_id');
    }
}
