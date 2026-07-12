<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Shift extends BaseController
{
    public function index()
    {
        $shiftList = $this->db->table('shift as s')
            ->select("s.*, (SELECT COUNT(*) FROM users u WHERE u.shift_id = s.id AND u.status = 'aktif') AS jml")
            ->orderBy("FIELD(kategori,'Pagi','Sore','Malam')", '', false)
            ->orderBy('jam_masuk')->get()->getResultArray();

        $q     = trim((string) $this->request->getGet('q'));
        $fUnit = (int) $this->request->getGet('unit');

        $b = $this->db->table('users as u')
            ->select('u.id, u.nama_lengkap, u.shift_id, uk.nama AS unit_nama, su.nama AS sub_nama,
                      p.nama AS profesi_nama')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->where('u.role', 'pegawai')->where('u.status', 'aktif');
        if ($q !== '') $b->like('u.nama_lengkap', $q);
        if ($fUnit)    $b->where('u.unit_kerja_id', $fUnit);
        $pegawai = $b->orderBy('u.nama_lengkap')->get()->getResultArray();

        $grup = [];
        foreach ($shiftList as $s) {
            if ($s['aktif']) $grup[$s['kategori']][] = $s;
        }

        return view('admin/shift', [
            'judulHalaman'  => 'Pengaturan Shift',
            'menuAktif'     => 'shift',
            'shiftList'     => $shiftList,
            'shiftGrup'     => $grup,
            'pegawai'       => $pegawai,
            'unitList'      => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'q'             => $q,
            'fUnit'         => $fUnit,
            'izin'          => pengaturan('izinkan_pilih_shift', '1') === '1',
            'qs'            => http_build_query(array_filter(['q' => $q, 'unit' => $fUnit ?: null])),
        ]);
    }

    public function aksi()
    {
        $aksi = (string) $this->request->getPost('aksi');
        $id   = (int) $this->request->getPost('id');
        $qs   = (string) $this->request->getPost('qs');
        $ke   = 'admin/shift' . ($qs ? '?' . $qs : '');

        switch ($aksi) {
            case 'tambah_shift':
                $kategori = (string) $this->request->getPost('kategori');
                $masuk    = (string) $this->request->getPost('jam_masuk');
                $pulang   = (string) $this->request->getPost('jam_pulang');
                if (! in_array($kategori, ['Pagi', 'Sore', 'Malam'], true) || ! $masuk || ! $pulang) {
                    return redirect()->to($ke)->with('flash_gagal', 'Kategori dan jam shift wajib diisi.');
                }
                $this->db->table('shift')->insert([
                    'kategori'    => $kategori,
                    'jam_masuk'   => $masuk,
                    'jam_pulang'  => $pulang,
                    'lintas_hari' => ($pulang <= $masuk) ? 1 : 0,
                    'aktif'       => 1,
                ]);
                catat_aktivitas('Tambah Shift', "$kategori $masuk-$pulang");
                return redirect()->to($ke)->with('flash_sukses', 'Shift baru ditambahkan.');

            case 'toggle_shift':
                $this->db->query('UPDATE shift SET aktif = 1 - aktif WHERE id = ?', [$id]);
                return redirect()->to($ke)->with('flash_sukses', 'Status shift diperbarui.');

            case 'hapus_shift':
                $dipakai = $this->db->table('users')->where('shift_id', $id)->countAllResults()
                         + $this->db->table('absensi')->where('shift_id', $id)->countAllResults();
                if ($dipakai > 0) {
                    return redirect()->to($ke)->with('flash_gagal',
                        'Shift tidak dapat dihapus karena masih dipakai pegawai atau data absensi. Gunakan Nonaktifkan.');
                }
                $this->db->table('shift')->where('id', $id)->delete();
                catat_aktivitas('Hapus Shift', '#' . $id);
                return redirect()->to($ke)->with('flash_sukses', 'Shift dihapus.');

            case 'atur_pegawai':
                $userId  = (int) $this->request->getPost('user_id');
                $shiftId = (int) $this->request->getPost('shift_id') ?: null;

                $lama = $this->db->table('users')->select('shift_id, nama_lengkap')
                             ->where('id', $userId)->get()->getRowArray();
                $this->db->table('users')->where('id', $userId)->update(['shift_id' => $shiftId]);
                if ($shiftId && (int) ($lama['shift_id'] ?? 0) !== $shiftId) {
                    $this->db->table('jadwal_shift')->insert([
                        'user_id' => $userId, 'shift_id' => $shiftId,
                        'tanggal_berlaku' => date('Y-m-d'),
                        'diubah_oleh' => session('uid'), 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    catat_aktivitas('Atur Shift Pegawai', ($lama['nama_lengkap'] ?? '#' . $userId));
                }
                return redirect()->to($ke)->with('flash_sukses',
                    'Shift pegawai diperbarui. Shift ini berlaku setiap hari sampai diubah kembali.');

            case 'izin_pilih':
                simpan_pengaturan('izinkan_pilih_shift', $this->request->getPost('izinkan') ? '1' : '0');
                catat_aktivitas('Pengaturan', 'Izin pemilihan shift mandiri diubah');
                return redirect()->to($ke)->with('flash_sukses', 'Pengaturan izin pemilihan shift diperbarui.');
        }
        return redirect()->to($ke)->with('flash_gagal', 'Aksi tidak dikenal.');
    }
}
