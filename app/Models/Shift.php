<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'shift';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jam_masuk' => 'datetime:H:i',
            'jam_pulang' => 'datetime:H:i',
            'lintas_hari' => 'boolean',
            'aktif' => 'boolean',
        ];
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class);
    }

    public function label(): string
    {
        $masuk = Carbon::parse($this->jam_masuk)->format('H.i');
        $pulang = Carbon::parse($this->jam_pulang)->format('H.i');

        return "{$this->kategori} ({$masuk} - {$pulang})";
    }
}
