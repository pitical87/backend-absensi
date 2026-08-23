<?php

namespace App\Services;

use App\Models\AtasanLangsung;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;

class AtasanLangsungService
{
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
