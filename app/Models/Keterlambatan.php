<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keterlambatan extends Model
{
    protected $table = 'keterlambatan';

    protected $fillable = [
        'absensi_id',
        'menit_telat',
        'bintang_masuk',
        'menit_awal_pulang',
        'bintang_pulang',
        'total_bintang',
    ];

    protected function casts(): array
    {
        return [
            'menit_telat' => 'integer',
            'bintang_masuk' => 'integer',
            'menit_awal_pulang' => 'integer',
            'bintang_pulang' => 'integer',
            'total_bintang' => 'float',
        ];
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }
}
