<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $table = 'api_tokens';
    protected $fillable = ['user_id','token','expires_at','perangkat','ip','user_agent','last_aktivitas'];
    public $timestamps = false;
    protected function casts():array{
        return[
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'last_aktivitas' => 'datetime',
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

    /**
     * Label perangkat untuk ditampilkan: memakai kolom perangkat bila ada,
     * sisanya disimpulkan dari user agent.
     */
    public function namaPerangkat(): string
    {
        if ($this->perangkat) {
            return $this->perangkat;
        }
        $ua = strtolower((string) $this->user_agent);
        if (str_contains($ua, 'okhttp') || str_contains($ua, 'android')) {
            return 'Android';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
            return 'iOS';
        }
        if (str_contains($ua, 'windows')) {
            return 'Windows';
        }
        if (str_contains($ua, 'macintosh')) {
            return 'macOS';
        }
        return $this->user_agent ? 'Perangkat' : '-';
    }


}
