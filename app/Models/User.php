<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function profesi(): BelongsTo
    {
        return $this->belongsTo(Profesi::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function seksiPembina(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'seksi_pembina_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function pengajuanIzin(): HasMany
    {
        return $this->hasMany(Izin::class, 'user_id');
    }

    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class);
    }

    /**
     * Shift aktif pegawai diambil dari tabel jadwal_shift:
     * baris dengan tanggal_berlaku = hari ini; bila belum jam 12 siang
     * dan hari ini tidak ada jadwal, cek jadwal kemarin (lanjutan shift malam).
     */
    protected function shift(): Attribute
    {
        return Attribute::get(function () {
            $hariIni = now()->toDateString();

            $query = JadwalShift::with('shift:id,kategori,jam_masuk,jam_pulang,lintas_hari,aktif')
                ->where('user_id', $this->id);

            $jadwal = (clone $query)->where('tanggal_berlaku', $hariIni)->first();

            if (! $jadwal && (int) now()->format('G') < 12) {
                $jadwal = (clone $query)
                    ->where('tanggal_berlaku', now()->subDay()->toDateString())->first();
            }

            return $jadwal?->shift;
        });
    }

    public function logLokasi(): HasMany
    {
        return $this->hasMany(LogLokasi::class);
    }

    public function aktivitasLog(): HasMany
    {
        return $this->hasMany(AktivitasLog::class);
    }

    public function rekapBulanan(): HasMany
    {
        return $this->hasMany(RekapBulanan::class);
    }

    public function mappingSimrs(): HasOne
    {
        return $this->hasOne(MappingSIMRSAccount::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }

    protected function jabatanUnit(): Attribute
    {
        return Attribute::get(
            fn () => $this->jabatan?->unit_label
                ?? $this->jabatan?->induk?->unit_label
        );
    }
}
