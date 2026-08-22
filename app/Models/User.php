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
    // public $timestamps = false;

    protected $fillable = [
        'nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'agama', 'email', 'no_hp', 'nip', 'unit_kerja_id', 'sub_unit_id',
        'profesi_id', 'jabatan_kategori', 'jabatan_id', 'posisi',
        'status_pegawai', 'seksi_pembina_id', 'shift_id', 'password_hash',
        'role', 'status', 'created_at',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
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
