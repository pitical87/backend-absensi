<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Struktur as StrukturLib;

/**
 * Struktur Organisasi (admin): bagan, ringkasan pengelompokan pegawai,
 * dan pengelolaan node jabatan.
 */
class Struktur extends BaseController
{
    public function index()
    {
        $lib = new StrukturLib();

        // Ringkasan pegawai per kategori jabatan
        $perKategori = [];
        foreach ($this->db->table('users')
                     ->select('jabatan_kategori, COUNT(*) AS jml')
                     ->where('status', 'aktif')->where('role', 'pegawai')
                     ->groupBy('jabatan_kategori')->get()->getResultArray() as $r) {
            $perKategori[$r['jabatan_kategori']] = (int) $r['jml'];
        }

        // Ringkasan pegawai per tempat kerja (unit kerja pelayanan)
        $perUnit = $this->db->table('users as u')
            ->select('uk.nama, COUNT(*) AS jml')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id')
            ->where('u.status', 'aktif')->where('u.role', 'pegawai')
            ->groupBy('uk.id')->orderBy('uk.id')->get()->getResultArray();

        return view('admin/struktur', [
            'judulHalaman' => 'Struktur Organisasi',
            'menuAktif'    => 'struktur',
            'pohon'        => $lib->pohon(),
            'semua'        => $lib->semua(),
            'kategoriJab'  => array_slice(kategori_jabatan_list(), 0, 5), // tanpa Staf
            'perKategori'  => $perKategori,
            'perUnit'      => $perUnit,
        ]);
    }

    public function aksi()
    {
        $aksi = (string) $this->request->getPost('aksi');
        $id   = (int) $this->request->getPost('id');
        $nama = trim((string) $this->request->getPost('nama'));

        if ($aksi === 'tambah') {
            $kategori = (string) $this->request->getPost('kategori');
            $induk    = (int) $this->request->getPost('induk_id') ?: null;
            $unit     = trim((string) $this->request->getPost('unit_label')) ?: null;

            if ($nama === '' || ! in_array($kategori, array_slice(kategori_jabatan_list(), 0, 5), true)) {
                return redirect()->to('admin/struktur')
                    ->with('flash_gagal', 'Nama dan kategori jabatan wajib diisi.');
            }
            if ($induk && ! $this->db->table('jabatan')->where('id', $induk)->countAllResults()) {
                $induk = null;
            }
            $this->db->table('jabatan')->insert([
                'nama' => $nama, 'kategori' => $kategori, 'induk_id' => $induk,
                'unit_label' => $unit,
                'urutan' => 99,
            ]);
            catat_aktivitas('Tambah Jabatan', $nama . ' (' . $kategori . ')');
            return redirect()->to('admin/struktur')->with('flash_sukses', 'Jabatan baru ditambahkan ke struktur.');
        }

        if ($aksi === 'ubah') {
            if ($nama !== '' && $id) {
                $data = ['nama' => $nama];
                $unit = trim((string) $this->request->getPost('unit_label'));
                if ($this->request->getPost('unit_label') !== null) {
                    $data['unit_label'] = $unit !== '' ? $unit : null;
                }
                $this->db->table('jabatan')->where('id', $id)->update($data);
                catat_aktivitas('Ubah Jabatan', '#' . $id . ' → ' . $nama);
            }
            return redirect()->to('admin/struktur')->with('flash_sukses', 'Jabatan diperbarui.');
        }

        if ($aksi === 'hapus') {
            $punyaAnak = $this->db->table('jabatan')->where('induk_id', $id)->countAllResults() > 0;
            $dipegang  = $this->db->table('users')->where('jabatan_id', $id)->countAllResults() > 0;
            if ($punyaAnak || $dipegang) {
                return redirect()->to('admin/struktur')->with('flash_gagal',
                    'Jabatan tidak dapat dihapus karena masih memiliki '
                    . ($punyaAnak ? 'jabatan bawahan' : 'pemegang jabatan')
                    . '. Pindahkan terlebih dahulu.');
            }
            $j = $this->db->table('jabatan')->where('id', $id)->get()->getRowArray();
            $this->db->table('jabatan')->where('id', $id)->delete();
            catat_aktivitas('Hapus Jabatan', $j['nama'] ?? ('#' . $id));
            return redirect()->to('admin/struktur')->with('flash_sukses', 'Jabatan dihapus dari struktur.');
        }

        return redirect()->to('admin/struktur')->with('flash_gagal', 'Aksi tidak dikenal.');
    }
}
