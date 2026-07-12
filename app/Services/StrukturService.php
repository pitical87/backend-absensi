<?php

namespace App\Services;

use App\Models\Jabatan;
use App\Models\User;

class StrukturService
{
    public function semua(): \Illuminate\Support\Collection
    {
        return Jabatan::orderByRaw('induk_id IS NULL DESC')
            ->orderBy('induk_id')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }

    public function pilihan(): array
    {
        $peta = [];
        foreach ($this->semua() as $j) {
            $peta[$j->kategori][] = ['id' => $j->id, 'nama' => $j->nama];
        }
        return $peta;
    }

    public function resolusi(string $kategori, ?int $jabatanId, int $kecualiUserId = 0): array
    {
        $validKategori = kategori_jabatan_list();
        if (! in_array($kategori, $validKategori, true)) {
            $kategori = 'Staf/Pelaksana';
        }

        if ($kategori === 'Staf/Pelaksana') {
            return [$kategori, null, ''];
        }

        if ($kategori === 'Direktur') {
            $node = Jabatan::where('kategori', 'Direktur')->orderBy('id')->first();
            if (! $node) {
                return [$kategori, null, 'Node Direktur belum ada pada struktur. Hubungi admin.'];
            }
            $jabatanId = $node->id;
        } else {
            $node = $jabatanId ? Jabatan::find($jabatanId) : null;
            if (! $node || $node->kategori !== $kategori) {
                return [$kategori, null, 'Nama jabatan wajib dipilih sesuai kategori ' . $kategori . '.'];
            }
        }

        $pemegang = User::where('jabatan_id', $jabatanId)
            ->where('id', '!=', $kecualiUserId)
            ->where('status', 'aktif')
            ->select('nama_lengkap')
            ->first();

        if ($pemegang) {
            return [$kategori, null,
                'Jabatan ' . $node->nama . ' saat ini dijabat oleh ' . $pemegang->nama_lengkap
                . '. Kosongkan/ubah jabatan pegawai tersebut terlebih dahulu melalui menu Data Pegawai.'];
        }

        return [$kategori, $jabatanId, ''];
    }

    public function pohon(): array
    {
        $node = [];
        foreach ($this->semua() as $j) {
            $j->anak = collect();
            $j->pejabat = collect();
            $node[$j->id] = $j;
        }

        $users = User::select('id', 'nama_lengkap', 'nip', 'jabatan_id')
            ->with('profesi:id,nama')
            ->whereNotNull('jabatan_id')
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        foreach ($users as $u) {
            if (isset($node[$u->jabatan_id])) {
                $node[$u->jabatan_id]->pejabat->push($u);
            }
        }

        $akar = [];
        foreach ($node as $id => $j) {
            $induk = $j->induk_id ?? 0;
            if ($induk && isset($node[$induk])) {
                $node[$induk]->anak->push($j);
            } else {
                $akar[] = $j;
            }
        }
        return $akar;
    }

    public function keturunan(int $id): array
    {
        $peta = [];
        foreach ($this->semua() as $j) {
            $peta[$j->induk_id ?? 0][] = $j->id;
        }

        $hasil = [$id];
        $antre = [$id];
        while ($antre) {
            $kini = array_shift($antre);
            foreach ($peta[$kini] ?? [] as $anak) {
                $hasil[] = $anak;
                $antre[] = $anak;
            }
        }
        return $hasil;
    }

    public function resolusiPosisi(
        string $posisi,
        string $jabatanKategori,
        ?int $jabatanId,
        ?int $seksiPembinaId
    ): array {
        if (! in_array($posisi, posisi_list(), true)) {
            $posisi = 'Staf';
        }

        $petaSyarat = [
            'Kepala Seksi/Sub Bagian' => ['Kepala Seksi', 'Kepala Sub Bagian'],
            'Kepala Bidang/Bagian' => ['Kepala Bidang', 'Kepala Bagian'],
            'Direktur' => ['Direktur'],
        ];

        if (isset($petaSyarat[$posisi])) {
            if (! in_array($jabatanKategori, $petaSyarat[$posisi], true) || ! $jabatanId) {
                return [$posisi, null, 'Untuk posisi "' . $posisi . '", Jabatan pada Struktur Organisasi '
                    . 'wajib diatur ke salah satu: ' . implode(' / ', $petaSyarat[$posisi]) . '.'];
            }
            return [$posisi, null, ''];
        }

        if ($seksiPembinaId) {
            $node = Jabatan::find($seksiPembinaId);
            if (! $node || ! in_array($node->kategori, ['Kepala Seksi', 'Kepala Sub Bagian'], true)) {
                return [$posisi, null, 'Seksi/Sub Bagian pembina yang dipilih tidak valid.'];
            }
            return [$posisi, $seksiPembinaId, ''];
        }

        return [$posisi, null, ''];
    }

    public function unitOrganisasi(): \Illuminate\Support\Collection
    {
        return Jabatan::whereNotNull('unit_label')
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }
}
