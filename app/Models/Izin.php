<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Izin extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'user_id', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai',
        'lama_hari', 'keterangan', 'alamat_izin', 'lampiran', 'status',
        'tahap_aktif', 'diproses_oleh', 'catatan_admin', 'nomor_surat',
        'kode_verifikasi', 'ttd_digital', 'ttd_oleh', 'ttd_waktu',
        'created_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'lama_hari' => 'integer',
            'tahap_aktif' => 'integer',
            'ttd_digital' => 'boolean',
            'ttd_waktu' => 'datetime',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persetujuan(): HasMany
    {
        return $this->hasMany(IzinPersetujuan::class, 'pengajuan_id');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function ttdOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ttd_oleh');
    }
}
