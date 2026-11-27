<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanLupaAbsen extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_lupa_absen';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tanggal',
        'shift_id',
        'alasan',
        'lampiran',
        'status',
        'diproses_oleh',
        'catatan_keputusan',
        'diproses_pada',
        'absensi_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'diproses_pada' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function diprosesOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }
}
