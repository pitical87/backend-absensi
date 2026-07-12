<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/** Kalender hari libur (tanggal merah / cuti bersama / libur internal). */
class Libur extends BaseController
{
    public function index()
    {
        $tahun = (int) ($this->request->getGet('tahun') ?: date('Y'));
        $tahun = min(2100, max(2024, $tahun));
        pastikan_libur_tetap($tahun);

        $daftar = $this->db->table('hari_libur')
            ->where('YEAR(tanggal)', $tahun)
            ->orderBy('tanggal')->get()->getResultArray();

        return view('admin/libur', [
            'judulHalaman' => 'Hari Libur',
            'menuAktif'    => 'libur',
            'daftar'       => $daftar,
            'tahun'        => $tahun,
            'mingguLibur'  => pengaturan('minggu_libur', '0') === '1',
        ]);
    }

    public function aksi()
    {
        $aksi = (string) $this->request->getPost('aksi');

        if ($aksi === 'tambah') {
            $tanggal = (string) $this->request->getPost('tanggal');
            $ket     = trim((string) $this->request->getPost('keterangan'));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return redirect()->to('admin/libur')
                    ->with('flash_gagal', 'Tanggal dan keterangan wajib diisi.');
            }
            $ada = $this->db->table('hari_libur')->where('tanggal', $tanggal)->countAllResults() > 0;
            if ($ada) {
                return redirect()->to('admin/libur')
                    ->with('flash_gagal', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');
            }
            $this->db->table('hari_libur')->insert(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Tambah Hari Libur', $tanggal . ' — ' . $ket);
            return redirect()->to('admin/libur')->with('flash_sukses', 'Hari libur ditambahkan.');
        }

        if ($aksi === 'hapus') {
            $id = (int) $this->request->getPost('id');
            $h  = $this->db->table('hari_libur')->where('id', $id)->get()->getRowArray();
            if ($h) {
                $this->db->table('hari_libur')->where('id', $id)->delete();
                catat_aktivitas('Hapus Hari Libur', $h['tanggal'] . ' — ' . $h['keterangan']);
            }
            return redirect()->to('admin/libur')->with('flash_sukses', 'Hari libur dihapus.');
        }

        if ($aksi === 'minggu') {
            simpan_pengaturan('minggu_libur', $this->request->getPost('minggu_libur') ? '1' : '0');
            catat_aktivitas('Pengaturan', 'Status hari Minggu sebagai libur diubah');
            return redirect()->to('admin/libur')->with('flash_sukses', 'Pengaturan hari Minggu diperbarui.');
        }

        return redirect()->to('admin/libur')->with('flash_gagal', 'Aksi tidak dikenal.');
    }
}
