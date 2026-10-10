<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiIntegration extends Model
{
    protected $fillable = ['provider', 'model', 'api_key', 'enabled'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }
}
