<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $table = 'api_tokens';
    protected $fillable = ['user_id','token','expires_at'];
    public $timestamps = false;
    protected function casts():array{
        return[
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected function user():BelongsTo{
        return $this->belongsTo(User::class);
    }

    protected function scopeValid($query){
        return $query->where('expires_at','>',now());
    }

    protected function isExpired():bool{
        return $this->expires_at->isPast();
    }


}
