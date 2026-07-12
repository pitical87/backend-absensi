<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Absensi extends Model
{
    use HasFactory;

    protected $table = 'absensi';
    public $timestamps = false; 

    protected $fillable = [
        'user_id', 'tanggal', 'shift_id', 'waktu_masuk', 'waktu_pulang',
        'lat_masuk', 'lng_masuk', 'lat_pulang', 'lng_pulang',
        'foto_masuk', 'foto_pulang', 'status_masuk', 'menit_terlambat',
        'total_menit_kerja', 'flag_anomali', 'catatan_anomali',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu_masuk' => 'datetime',
            'waktu_pulang' => 'datetime',
            'lat_masuk' => 'decimal:7',
            'lng_masuk' => 'decimal:7',
            'lat_pulang' => 'decimal:7',
            'lng_pulang' => 'decimal:7',
            'menit_terlambat' => 'integer',
            'total_menit_kerja' => 'integer',
            'flag_anomali' => 'boolean',
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

    public function logLokasi(): HasMany
    {
        return $this->hasMany(LogLokasi::class);
    }
}
