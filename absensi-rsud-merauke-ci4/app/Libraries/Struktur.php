<?php

namespace App\Libraries;

use Config\Database;

/**
 * Struktur — util pohon jabatan organisasi.
 */
class Struktur
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** Semua node, terurut hierarki-ramah. */
    public function semua(): array
    {
        return $this->db->table('jabatan')
            ->orderBy('induk_id IS NULL', 'DESC', false)
            ->orderBy('induk_id')->orderBy('urutan')->orderBy('id')
            ->get()->getResultArray();
    }

    /** Peta pilihan untuk dropdown bertingkat: kategori → [{id, nama}]. */
    public function pilihan(): array
    {
        $peta = [];
        foreach ($this->semua() as $j) {
            $peta[$j['kategori']][] = ['id' => (int) $j['id'], 'nama' => $j['nama']];
        }
        return $peta;
    }

    /**
     * Memvalidasi & menetapkan jabatan dari input formulir.
     *
     * @return array{0: string, 1: int|null, 2: string} [kategori, jabatan_id, pesanGalat('' bila sah)]
     */
    public function resolusi(string $kategori, int $jabatanId, int $kecualiUserId = 0): array
    {
        if (! in_array($kategori, kategori_jabatan_list(), true)) {
            $kategori = 'Staf/Pelaksana';
        }

        // Staf tidak memegang node struktural
        if ($kategori === 'Staf/Pelaksana') {
            return [$kategori, null, ''];
        }

        // Direktur: otomatis mengarah ke node Direktur
        if ($kategori === 'Direktur') {
            $node = $this->db->table('jabatan')->where('kategori', 'Direktur')
                        ->orderBy('id')->get(1)->getRowArray();
            if (! $node) {
                return [$kategori, null, 'Node Direktur belum ada pada struktur. Hubungi admin.'];
            }
            $jabatanId = (int) $node['id'];
        } else {
            $node = $jabatanId
                ? $this->db->table('jabatan')->where('id', $jabatanId)->get()->getRowArray()
                : null;
            if (! $node || $node['kategori'] !== $kategori) {
                return [$kategori, null, 'Nama jabatan wajib dipilih sesuai kategori ' . $kategori . '.'];
            }
        }

        // Satu jabatan struktural = satu pemegang (kecuali dirinya sendiri saat edit)
        $pemegang = $this->db->table('users')
            ->select('nama_lengkap')
            ->where('jabatan_id', $jabatanId)
            ->where('id !=', $kecualiUserId)
            ->where('status', 'aktif')
            ->get(1)->getRowArray();
        if ($pemegang) {
            return [$kategori, null,
                'Jabatan ' . $node['nama'] . ' saat ini dijabat oleh ' . $pemegang['nama_lengkap']
                . '. Kosongkan/ubah jabatan pegawai tersebut terlebih dahulu melalui menu Data Pegawai.'];
        }

        return [$kategori, $jabatanId, ''];
    }

    /** Pohon bersarang: tiap node membawa 'anak' => [] dan 'pejabat' (users aktif). */
    public function pohon(): array
    {
        $node = [];
        foreach ($this->semua() as $j) {
            $j['anak']    = [];
            $j['pejabat'] = [];
            $node[(int) $j['id']] = $j;
        }
        foreach ($this->db->table('users as u')
                     ->select('u.id, u.nama_lengkap, u.nip, u.jabatan_id, p.nama AS profesi_nama')
                     ->join('profesi as p', 'p.id = u.profesi_id', 'left')
                     ->where('u.jabatan_id IS NOT NULL')->where('u.status', 'aktif')
                     ->orderBy('u.nama_lengkap')->get()->getResultArray() as $u) {
            $id = (int) $u['jabatan_id'];
            if (isset($node[$id])) {
                $node[$id]['pejabat'][] = $u;
            }
        }

        $akar = [];
        foreach ($node as $id => $j) {
            $induk = (int) ($j['induk_id'] ?? 0);
            if ($induk && isset($node[$induk])) {
                $node[$induk]['anak'][] = &$node[$id];
            } else {
                $akar[] = &$node[$id];
            }
        }
        return $akar;
    }

    /** id node + seluruh keturunannya (untuk filter laporan per Bidang/Bagian). */
    public function keturunan(int $id): array
    {
        $peta = [];
        foreach ($this->semua() as $j) {
            $peta[(int) ($j['induk_id'] ?? 0)][] = (int) $j['id'];
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

    /**
     * Memvalidasi kaitan Posisi (peran alur kerja) dengan data struktural pegawai.
     *
     * - Staf / Koordinator: seksi_pembina_id opsional, tapi bila diisi harus merujuk
     *   node berkategori Kepala Seksi/Kepala Sub Bagian.
     * - Kepala Seksi/Sub Bagian, Kepala Bidang/Bagian, Direktur: MEMAKAI field
     *   Jabatan & Nama Jabatan yang sudah ada (tidak dobel input) — divalidasi agar
     *   kategori jabatannya konsisten dengan posisi yang dipilih.
     *
     * @return array{0: string, 1: int|null, 2: string} [posisi, seksi_pembina_id, pesanGalat]
     */
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
            'Kepala Bidang/Bagian'    => ['Kepala Bidang', 'Kepala Bagian'],
            'Direktur'                => ['Direktur'],
        ];

        if (isset($petaSyarat[$posisi])) {
            if (! in_array($jabatanKategori, $petaSyarat[$posisi], true) || ! $jabatanId) {
                return [$posisi, null, 'Untuk posisi "' . $posisi . '", Jabatan pada Struktur Organisasi '
                    . 'wajib diatur ke salah satu: ' . implode(' / ', $petaSyarat[$posisi]) . '.'];
            }
            return [$posisi, null, ''];
        }

        // Staf / Koordinator / HRD: seksi pembina opsional
        if ($seksiPembinaId) {
            $node = $this->db->table('jabatan')->where('id', $seksiPembinaId)->get()->getRowArray();
            if (! $node || ! in_array($node['kategori'], ['Kepala Seksi', 'Kepala Sub Bagian'], true)) {
                return [$posisi, null, 'Seksi/Sub Bagian pembina yang dipilih tidak valid.'];
            }
            return [$posisi, $seksiPembinaId, ''];
        }

        return [$posisi, null, ''];
    }

    /** Node ber-unit_label (Direktorat/Bidang/Bagian) untuk filter unit organisasi. */
    public function unitOrganisasi(): array
    {
        return $this->db->table('jabatan')
            ->where('unit_label IS NOT NULL')
            ->orderBy('urutan')->orderBy('id')
            ->get()->getResultArray();
    }
}
