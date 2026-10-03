<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi in-app milik seorang pengguna.
 *
 * Dibuat lewat helper buat_notifikasi() supaya semua pemanggil memakai aturan
 * yang sama: isi wajib, tipe dibatasi, url opsional, dan kegagalan simpan tidak
 * pernah menggagalkan aksi utama.
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    protected $guarded = [];

    /** Warna notifikasi, dipakai badge di panel admin dan ikon di aplikasi. */
    public const TIPE = ['info', 'warning', 'danger', 'success'];

    /** Kelompok notifikasi supaya aplikasi bisa memfilter, bukan hanya menampilkan. */
    public const KATEGORI = [
        'password' => 'Password',
        'keamanan' => 'Keamanan',
        'jadwal' => 'Jadwal',
        'izin' => 'Izin',
        'lembur' => 'Lembur',
        'sistem' => 'Sistem',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeKategori(Builder $query, ?string $kategori): Builder
    {
        return $kategori === null || $kategori === ''
            ? $query
            : $query->where('kategori', $kategori);
    }

    /** Label kategori yang ramah tampilan; kategori tak dikenal dipakai apa adanya. */
    public function labelKategori(): string
    {
        return self::KATEGORI[(string) $this->kategori] ?? (string) ($this->kategori ?: 'Umum');
    }
}
