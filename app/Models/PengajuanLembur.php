<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PengajuanLembur extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_lembur';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'durasi_jam',
        'keterangan', 'status', 'diproses_oleh', 'catatan_keputusan',
        'diproses_pada', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date:Y-m-d',
            'jam_mulai'      => 'datetime:H:i',
            'jam_selesai'    => 'datetime:H:i',
            'durasi_jam'     => 'decimal:1',
            'diproses_pada'  => 'datetime',
            'created_at'     => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diprosesOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function absenLembur(): HasOne
    {
        return $this->hasOne(AbsenLembur::class);
    }
}
