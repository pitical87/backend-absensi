<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapBulanan extends Model
{
    use HasFactory;

    protected $table = 'rekap_bulanan';

    protected $fillable = [
        'user_id', 'bulan', 'tahun', 'total_hari_efektif', 'total_hadir',
        'total_tepat_waktu', 'total_terlambat', 'total_alpa', 'total_izin',
        'total_sakit', 'total_cuti', 'total_dinas_luar', 'total_libur',
        'total_menit_kerja', 'persentase', 'generated_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'total_hari_efektif' => 'integer',
            'total_hadir' => 'integer',
            'total_tepat_waktu' => 'integer',
            'total_terlambat' => 'integer',
            'total_alpa' => 'integer',
            'total_izin' => 'integer',
            'total_sakit' => 'integer',
            'total_cuti' => 'integer',
            'total_dinas_luar' => 'integer',
            'total_libur' => 'integer',
            'total_menit_kerja' => 'integer',
            'persentase' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
