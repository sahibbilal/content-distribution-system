<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform_type',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setCredentialsAttribute($value)
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    public function getCredentialsAttribute($value)
    {
        if ($value) {
            return json_decode(Crypt::decryptString($value), true);
        }
        return null;
    }
}

