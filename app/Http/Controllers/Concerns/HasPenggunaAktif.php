<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait HasPenggunaAktif
{
    private function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }

        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }

        $user = User::with([
            'unitKerja:id,nama',
            'subUnit:id,nama',
            'profesi:id,nama',
            'jabatan:id,nama,induk_id,unit_label',
            'jabatan.induk:id,unit_label',
            'seksiPembina:id,nama,induk_id',
            'seksiPembina.induk:id,unit_label',
        ])
            ->where('id', $uid)
            ->first();

        if (! $user || ! $user->isActive()) {
            session()->flush();
            return $cache = null;
        }

        return $cache = [
            'id'                    => $user->id,
            'nama_lengkap'          => $user->nama_lengkap,
            'nip'                   => $user->nip,
            'email'                 => $user->email,
            'no_hp'                 => $user->no_hp,
            'role'                  => $user->role,
            'status'                => $user->status,
            'unit_kerja_id'         => $user->unit_kerja_id,
            'sub_unit_id'           => $user->sub_unit_id,
            'profesi_id'            => $user->profesi_id,
            'jabatan_id'            => $user->jabatan_id,
            'jabatan_kategori'      => $user->jabatan_kategori,
            'posisi'                => $user->posisi,
            'seksi_pembina_id'      => $user->seksi_pembina_id,
            'shift_id'              => $user->shift?->id,
            'status_pegawai'        => $user->status_pegawai,
            'unit_nama'             => $user->unitKerja?->nama,
            'sub_unit_nama'         => $user->subUnit?->nama,
            'profesi_nama'          => $user->profesi?->nama,
            'shift_kategori'        => $user->shift?->kategori,
            'shift_jam_masuk'       => $user->shift?->jam_masuk?->format('H:i'),
            'shift_jam_pulang'      => $user->shift?->jam_pulang?->format('H:i'),
            'jabatan_nama'          => $user->jabatan?->nama,
            'jabatan_unit'          => $user->jabatanUnit,
            'seksi_pembina_nama'    => $user->seksiPembina?->nama,
            'bidang_pembina_label'  => $user->seksiPembina?->induk?->unit_label,
        ];
    }
}
