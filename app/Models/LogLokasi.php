<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogLokasi extends Model
{
    use HasFactory;

    protected $table = 'log_lokasi';

    protected $fillable = [
        'user_id', 'absensi_id', 'tipe', 'latitude', 'longitude',
        'akurasi', 'jarak_meter', 'ditolak', 'waktu',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'akurasi' => 'decimal:2',
            'jarak_meter' => 'decimal:2',
            'ditolak' => 'boolean',
            'waktu' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }
}
