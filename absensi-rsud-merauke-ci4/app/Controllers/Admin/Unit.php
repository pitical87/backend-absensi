<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Unit extends BaseController
{
    public function index()
    {
        $unitList = $this->db->table('unit_kerja as uk')
            ->select('uk.*, (SELECT COUNT(*) FROM users u WHERE u.unit_kerja_id = uk.id) AS jml_pegawai')
            ->orderBy('uk.id')->get()->getResultArray();

        $subPerUnit = [];
        foreach ($this->db->table('sub_unit as su')
                     ->select('su.*, (SELECT COUNT(*) FROM users u WHERE u.sub_unit_id = su.id) AS jml_pegawai')
                     ->orderBy('su.unit_kerja_id')->orderBy('su.id')
                     ->get()->getResultArray() as $s) {
            $subPerUnit[(int) $s['unit_kerja_id']][] = $s;
        }

        return view('admin/unit', [
            'judulHalaman' => 'Data Unit Kerja',
            'menuAktif'    => 'unit',
            'unitList'     => $unitList,
            'subPerUnit'   => $subPerUnit,
        ]);
    }

    public function aksi()
    {
        $aksi = (string) $this->request->getPost('aksi');
        $id   = (int) $this->request->getPost('id');
        $nama = trim((string) $this->request->getPost('nama'));

        switch ($aksi) {
            case 'tambah_unit':
                if ($nama === '') {
                    return $this->kembali('flash_gagal', 'Nama unit wajib diisi.');
                }
                $this->db->table('unit_kerja')->insert([
                    'nama' => $nama,
                    'punya_sub' => $this->request->getPost('punya_sub') ? 1 : 0,
                ]);
                catat_aktivitas('Tambah Unit', $nama);
                return $this->kembali('flash_sukses', 'Unit kerja ditambahkan.');

            case 'ubah_unit':
                if ($nama !== '') {
                    $this->db->table('unit_kerja')->where('id', $id)->update([
                        'nama' => $nama,
                        'punya_sub' => $this->request->getPost('punya_sub') ? 1 : 0,
                    ]);
                    catat_aktivitas('Ubah Unit', $nama);
                }
                return $this->kembali('flash_sukses', 'Unit kerja diperbarui.');

            case 'hapus_unit':
                $dipakai = $this->db->table('users')->where('unit_kerja_id', $id)->countAllResults() > 0;
                if ($dipakai) {
                    return $this->kembali('flash_gagal',
                        'Unit tidak dapat dihapus karena masih memiliki pegawai. Pindahkan pegawainya terlebih dahulu.');
                }
                $u = $this->db->table('unit_kerja')->where('id', $id)->get()->getRowArray();
                $this->db->table('unit_kerja')->where('id', $id)->delete();
                catat_aktivitas('Hapus Unit', $u['nama'] ?? ('#' . $id));
                return $this->kembali('flash_sukses', 'Unit kerja beserta sub unitnya dihapus.');

            case 'tambah_sub':
                $unitId = (int) $this->request->getPost('unit_kerja_id');
                if ($unitId && $nama !== '') {
                    $this->db->table('sub_unit')->insert(['unit_kerja_id' => $unitId, 'nama' => $nama]);
                    $this->db->table('unit_kerja')->where('id', $unitId)->update(['punya_sub' => 1]);
                    catat_aktivitas('Tambah Sub Unit', $nama);
                }
                return $this->kembali('flash_sukses', 'Sub unit ditambahkan.');

            case 'hapus_sub':
                $dipakai = $this->db->table('users')->where('sub_unit_id', $id)->countAllResults() > 0;
                if ($dipakai) {
                    return $this->kembali('flash_gagal',
                        'Sub unit tidak dapat dihapus karena masih memiliki pegawai.');
                }
                $s = $this->db->table('sub_unit')->where('id', $id)->get()->getRowArray();
                $this->db->table('sub_unit')->where('id', $id)->delete();
                catat_aktivitas('Hapus Sub Unit', $s['nama'] ?? ('#' . $id));
                return $this->kembali('flash_sukses', 'Sub unit dihapus.');
        }
        return $this->kembali('flash_gagal', 'Aksi tidak dikenal.');
    }

    private function kembali(string $kunci, string $pesan)
    {
        return redirect()->to('admin/unit')->with($kunci, $pesan);
    }
}
