<?php

namespace App\Models;

use App\Services\PasswordService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasFactory, SoftDeletes, Notifiable, HasPushSubscriptions;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
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

    public function pengajuanLembur(): HasMany
    {
        return $this->hasMany(PengajuanLembur::class);
    }

    public function absenLembur(): HasMany
    {
        return $this->hasMany(AbsenLembur::class);
    }

    /**
     * Jadwal per tanggal mengalahkan template mingguan sub unit. Bila belum
     * jam 12 siang dan hari ini tidak ada shift, cek kemarin untuk shift malam.
     */
    protected function shift(): Attribute
    {
        return Attribute::get(function () {
            $jadwal = app(\App\Services\JadwalEfektifService::class);
            $hariIni = now()->toDateString();
            $shift = $jadwal->shiftUntukTanggal($this, $hariIni);

            if (! $shift && (int) now()->format('G') < 12) {
                $kemarin = $jadwal->shiftUntukTanggal($this, now()->subDay()->toDateString());
                $lintasHari = $kemarin && (
                    $kemarin->lintas_hari
                    || $kemarin->jam_pulang->format('H:i') <= $kemarin->jam_masuk->format('H:i')
                    || $kemarin->kategori === 'Malam'
                );
                $shift = $lintasHari ? $kemarin : null;
            }

            return $shift;
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

    public function noteDeleted(): HasMany
    {
        return $this->hasMany(NoteDeleted::class, 'user_id');
    }

    public function rekapBulanan(): HasMany
    {
        return $this->hasMany(RekapBulanan::class);
    }

    public function mappingSimrs(): HasOne
    {
        return $this->hasOne(MappingSIMRSAccount::class);
    }

    public function fingerMapping(): HasOne
    {
        return $this->hasOne(FingerPegawai::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }

    /** Sudah pernah mengganti password sejak akun dibuat? */
    public function pernahGantiPassword(): bool
    {
        return $this->password_changed_at !== null;
    }

    /**
     * Status password lengkap untuk panel admin dan aplikasi mobile.
     *
     * @return array{pernah: bool, terakhir: ?string, umur_hari: ?int, level: string, label: string, perlu_ganti: bool}
     *
     * @see PasswordService::status()
     */
    public function statusPassword(): array
    {
        return app(PasswordService::class)->status($this);
    }

    protected function jabatanUnit(): Attribute
    {
        return Attribute::get(
            fn () => $this->jabatan?->unit_label
                ?? $this->jabatan?->induk?->unit_label
        );
    }
}
