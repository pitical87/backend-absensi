<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    public $incrementing = false;
    protected $primaryKey = 'kunci';
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['kunci', 'nilai'];

    public static function ambil(string $kunci, mixed $default = null): mixed
    {
        return Cache::remember("pengaturan_{$kunci}", 3600, function () use ($kunci, $default) {
            $row = static::where('kunci', $kunci)->first();
            return $row ? $row->nilai : $default;
        });
    }

    public static function simpan(string $kunci, mixed $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        Cache::forget("pengaturan_{$kunci}");
    }

    public static function semua(): array
    {
        return Cache::remember('pengaturan_semua', 3600, function () {
            return static::pluck('nilai', 'kunci')->toArray();
        });
    }
}
