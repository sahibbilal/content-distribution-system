<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named series of posts (e.g. "GKMBA series", "Personal announcements") */
class PostSeries extends Model
{
    protected $table = 'post_series';

    protected $fillable = ['user_id', 'name'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'series_id');
    }
}
