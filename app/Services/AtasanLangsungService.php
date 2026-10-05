<?php

namespace App\Services;

use App\Models\AtasanLangsung;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;

class AtasanLangsungService
{
    /**
     * Ringkasan hak akses yang bergantung pada relasi atasan langsung.
     *
     * Kelima flag bernilai true bila user punya minimal satu bawahan langsung,
     * yaitu ada baris atasan_langsung dengan atasan_id = user ini. Flag
     * sengaja dipisah-pisah supaya klien bisa menyembunyikan tiap menu secara
     * mandiri, dan tiap menu bisa dibatasi berbeda saat aturan bisnisnya berubah.
     *
     * @return array<string, bool>
     */
    public function hakAkses(User $user): array
    {
        $punyaBawahan = $this->punyaBawahan($user);

        return [
            'verifikasi_logbook' => $punyaBawahan,
            'buat_jadwal' => $punyaBawahan,
            'verifikasi_ijin' => $punyaBawahan,
            'verifikasi_lembur' => $punyaBawahan,
            'verifikasi_perubahan_jadwal' => $punyaBawahan,
        ];
    }

    /**
     * Apakah user ini tercatat sebagai atasan langsung minimal satu pegawai.
     */
    public function punyaBawahan(User $user): bool
    {
        return AtasanLangsung::where('atasan_id', $user->id)
            ->where('user_id', '!=', $user->id)
            ->exists();
    }

    /**
     * Bawahan langsung user: id, nama, dan email saja. Relasi atasan
     * dicerminkan apa adanya, termasuk bila pegawainya sudah nonaktif, supaya
     * klien tetap tahu menyeluruh siapa yang berada di bawah kewenangannya.
     */
    public function daftarBawahan(User $atasan)
    {
        return User::query()
            ->whereIn('users.id', AtasanLangsung::where('atasan_id', $atasan->id)
                ->where('user_id', '!=', $atasan->id)
                ->select('user_id'))
            ->orderBy('users.nama_lengkap')
            ->get(['users.id', 'users.nama_lengkap', 'users.email'])
            ->map(fn ($b) => [
                'id' => (int) $b->id,
                'nama' => $b->nama_lengkap,
                'email' => $b->email,
            ])
            ->values()
            ->all();
    }

    /**
     * Kandidat atasan: semua pegawai non-admin, opsional kecuali seseorang.
     */
    public function pilihan(?int $kecualiId = null)
    {
        return User::where('role', '!=', 'admin')
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);
    }

    /**
     * Cari kandidat atasan untuk pencarian di modal, dipakai endpoint asinkron.
     */
    public static function cariPilihan(string $kata, int $batas = 50)
    {
        $kata = trim($kata);

        return User::where('role', '!=', 'admin')
            ->when($kata !== '', fn ($b) => $b->where(function ($w) use ($kata) {
                $w->where('nama_lengkap', 'like', "%{$kata}%")
                    ->orWhere('email', 'like', "%{$kata}%");
            }))
            ->orderBy('nama_lengkap')
            ->limit($batas)
            ->get(['id', 'nama_lengkap', 'email']);
    }

    /**
     * Ganti seluruh daftar atasan langsung seorang pegawai.
     * Id tidak valid, id admin, dan id pegawai sendiri dibuang otomatis.
     */
    public function sinkron(int $userId, array $atasanIds): int
    {
        $bersih = collect($atasanIds)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0 && $v !== $userId)
            ->unique()
            ->values();

        AtasanLangsung::where('user_id', $userId)->delete();

        $sah = User::whereIn('id', $bersih)->where('role', '!=', 'admin')->pluck('id')->all();

        foreach ($sah as $idAtasan) {
            AtasanLangsung::create(['user_id' => $userId, 'atasan_id' => $idAtasan]);
        }

        return count($sah);
    }

    /**
     * Terapkan daftar atasan yang sama ke beberapa pegawai sekaligus, karena
     * satu atasan bisa memiliki banyak bawahan. Mode ganti menimpa seluruh
     * atasan lama, mode tambah hanya melengkapi atasan yang sudah ada.
     *
     * Id atasan yang tidak sah (admin atau pegawai itu sendiri) dibuang per
     * pegawai, jadi antar target boleh saling menjadi atasan.
     *
     * @return array{diproses: int, diterapkan: int}
     */
    public function sinkronBanyak(array $userIds, array $atasanIds, string $mode = 'ganti'): array
    {
        $target = collect($userIds)->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->values();
        $diminta = collect($atasanIds)->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique();

        if ($target->isEmpty() || $diminta->isEmpty()) {
            return ['diproses' => 0, 'diterapkan' => 0];
        }

        $sahUmum = User::whereIn('id', $diminta->all())->where('role', '!=', 'admin')->pluck('id')->all();
        $pegawai = User::whereIn('id', $target->all())->where('role', '!=', 'admin')->pluck('id')->all();

        $diproses = 0;
        $diterapkan = 0;

        foreach ($pegawai as $userId) {
            $sah = array_values(array_filter($sahUmum, fn ($v) => $v !== $userId));

            if ($sah === []) {
                continue;
            }

            if ($mode === 'tambah') {
                $lama = AtasanLangsung::where('user_id', $userId)->pluck('atasan_id')->all();
                $sah = array_values(array_unique(array_merge($lama, $sah)));
            }

            $this->sinkron($userId, $sah);

            $diproses++;
            $diterapkan += count($sah);
        }

        return ['diproses' => $diproses, 'diterapkan' => $diterapkan];
    }

    /**
     * Isi atasan otomatis dari sub_unit.atasan_id, bila kosong dari unit_kerja.atasan_id.
     * Tidak menimpa bila pegawai sudah memiliki pengaturan atasan.
     */
    public function warisiOtomatis(User $user): void
    {
        if (AtasanLangsung::where('user_id', $user->id)->exists()) {
            return;
        }

        $ids = [];

        if ($user->sub_unit_id) {
            $a = SubUnit::find($user->sub_unit_id)?->atasan_id;
            if ($a && (int) $a !== $user->id) {
                $ids[] = (int) $a;
            }
        }

        if (! $ids && $user->unit_kerja_id) {
            $a = UnitKerja::find($user->unit_kerja_id)?->atasan_id;
            if ($a && (int) $a !== $user->id) {
                $ids[] = (int) $a;
            }
        }

        if ($ids) {
            $this->sinkron($user->id, $ids);
        }
    }
}
