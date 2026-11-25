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

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Cari sesi berdasarkan nilai mentah cookie auth_token.
     * Masa transisi otomatis: token plaintext lama tetap dikenali lalu langsung
     * di-upgrade ke SHA-256 di database agar user aktif tidak ter-logout.
     */
    public static function cariDenganToken(string $rawToken): ?self
    {
        $hashed = self::hashToken($rawToken);
        $record = self::where('token', $hashed)->first();
        if ($record) {
            return $record;
        }

        $legacy = self::where('token', $rawToken)->first();
        if ($legacy) {
            $legacy->forceFill(['token' => $hashed])->save();

            return $legacy;
        }

        return null;
    }

    public static function hapusDenganToken(string $rawToken): int
    {
        return self::whereIn('token', [self::hashToken($rawToken), $rawToken])->delete();
    }

    public function user():BelongsTo{
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

        return self::namaPerangkatDariUserAgent($this->user_agent);
    }

    /**
     * Simpul user agent menjadi label perangkat, dipakai juga oleh tracker
     * login gagal dan email peringatan yang tidak punya kolom perangkat.
     */
    public static function namaPerangkatDariUserAgent(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return '-';
        }
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

        return 'Perangkat lain';
    }


}
