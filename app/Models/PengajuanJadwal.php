<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanJadwal extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_jadwal';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tanggal', 'jadwal_shift_id', 'shift_lama_id', 'shift_baru_id',
        'alasan', 'status', 'diproses_oleh', 'catatan_keputusan',
        'diproses_pada', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'       => 'date:Y-m-d',
            'diproses_pada' => 'datetime',
            'created_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwalShift(): BelongsTo
    {
        return $this->belongsTo(JadwalShift::class);
    }

    public function shiftLama(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_lama_id');
    }

    public function shiftBaru(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_baru_id');
    }

    public function diprosesOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
