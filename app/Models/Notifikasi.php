<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
