<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IzinPersetujuan extends Model
{
    use HasFactory;

    protected $table = 'izin_persetujuan';

    protected $fillable = [
        'pengajuan_id', 'tahap', 'posisi_tahap', 'status',
        'oleh_user_id', 'catatan', 'waktu',
    ];

    protected function casts(): array
    {
        return [
            'tahap' => 'integer',
            'waktu' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Izin::class, 'pengajuan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh_user_id');
    }
}
