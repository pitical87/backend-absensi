<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenLembur extends Model
{
    use HasFactory;

    protected $table = 'absen_lembur';

    public $timestamps = false;

    protected $fillable = [
        'pengajuan_lembur_id', 'user_id', 'tanggal', 'waktu_masuk', 'waktu_pulang',
        'lat_masuk', 'lng_masuk', 'lat_pulang', 'lng_pulang', 'foto_masuk', 'foto_pulang',
        'durasi_menit', 'status_masuk', 'menit_terlambat', 'bintang_masuk',
        'bintang_pulang', 'bintang_harian', 'flag_anomali', 'catatan_anomali', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date:Y-m-d',
            'waktu_masuk'    => 'datetime',
            'waktu_pulang'   => 'datetime',
            'lat_masuk'      => 'decimal:7',
            'lng_masuk'      => 'decimal:7',
            'lat_pulang'     => 'decimal:7',
            'lng_pulang'     => 'decimal:7',
            'durasi_menit'   => 'integer',
            'menit_terlambat' => 'integer',
            'bintang_masuk'  => 'integer',
            'bintang_pulang' => 'integer',
            'bintang_harian' => 'float',
            'flag_anomali'   => 'boolean',
        ];
    }

    public function pengajuanLembur(): BelongsTo
    {
        return $this->belongsTo(PengajuanLembur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
