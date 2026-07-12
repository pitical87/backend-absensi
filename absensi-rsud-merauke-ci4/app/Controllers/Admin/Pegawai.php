<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Struktur;

class Pegawai extends BaseController
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $fUnit = (int) $this->request->getGet('unit');

        $b = $this->db->table('users as u')
            ->select('u.*, uk.nama AS unit_nama, su.nama AS sub_nama, p.nama AS profesi_nama,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang,
                      j.nama AS jabatan_nama')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->join('shift as s', 's.id = u.shift_id', 'left')
            ->join('jabatan as j', 'j.id = u.jabatan_id', 'left');
        if ($q !== '') {
            $b->groupStart()->like('u.nama_lengkap', $q)->orLike('u.email', $q)->groupEnd();
        }
        if ($fUnit) {
            $b->where('u.unit_kerja_id', $fUnit);
        }
        $pegawai = $b->orderBy('u.nama_lengkap')->get()->getResultArray();

        return view('admin/pegawai_index', [
            'judulHalaman' => 'Data Pegawai',
            'menuAktif'    => 'pegawai',
            'pegawai'      => $pegawai,
            'unitList'     => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'q'            => $q,
            'fUnit'        => $fUnit,
        ]);
    }

    public function form(int $id = 0)
    {
        $edit = null;
        if ($id) {
            $edit = $this->db->table('users')->where('id', $id)->get()->getRowArray();
            if (! $edit) {
                return redirect()->to('admin/pegawai')->with('flash_gagal', 'Pegawai tidak ditemukan.');
            }
        }

        $sub = [];
        foreach ($this->db->table('sub_unit')->orderBy('unit_kerja_id')->orderBy('id')
                     ->get()->getResultArray() as $s) {
            $sub[(int) $s['unit_kerja_id']][] = ['id' => (int) $s['id'], 'nama' => $s['nama']];
        }
        $shiftGrup = [];
        foreach ($this->db->table('shift')->where('aktif', 1)
                     ->orderBy("FIELD(kategori,'Pagi','Sore','Malam')", '', false)
                     ->orderBy('jam_masuk')->get()->getResultArray() as $s) {
            $shiftGrup[$s['kategori']][] = $s;
        }

        return view('admin/pegawai_form', [
            'judulHalaman' => $edit ? 'Ubah Data Pegawai' : 'Tambah Pegawai',
            'menuAktif'    => 'pegawai',
            'edit'         => $edit,
            'unitList'     => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'profList'     => $this->db->table('profesi')->orderBy('id')->get()->getResultArray(),
            'subPerUnit'   => $sub,
            'shiftGrup'    => $shiftGrup,
            'agamaList'    => self::AGAMA,
            'jabPilihan'   => (new Struktur())->pilihan(),
            'kategoriJab'  => kategori_jabatan_list(),
            'posisiList'   => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                (new Struktur())->pilihan()['Kepala Seksi'] ?? [],
                (new Struktur())->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ]);
    }

    public function simpan()
    {
        $id = (int) $this->request->getPost('id');
        $d  = [
            'nama_lengkap'  => trim((string) $this->request->getPost('nama_lengkap')),
            'tempat_lahir'  => trim((string) $this->request->getPost('tempat_lahir')) ?: null,
            'tanggal_lahir' => $this->request->getPost('tanggal_lahir') ?: null,
            'jenis_kelamin' => $this->request->getPost('jenis_kelamin') ?: null,
            'agama'         => $this->request->getPost('agama') ?: null,
            'email'         => trim((string) $this->request->getPost('email')),
            'no_hp'         => trim((string) $this->request->getPost('no_hp')) ?: null,
            'nip'           => trim((string) $this->request->getPost('nip')) ?: null,
            'unit_kerja_id' => (int) $this->request->getPost('unit_kerja_id') ?: null,
            'sub_unit_id'   => (int) $this->request->getPost('sub_unit_id') ?: null,
            'profesi_id'    => (int) $this->request->getPost('profesi_id') ?: null,
            'shift_id'      => (int) $this->request->getPost('shift_id') ?: null,
            'role'          => in_array($this->request->getPost('role'), ['admin', 'pegawai'], true)
                               ? $this->request->getPost('role') : 'pegawai',
            'status'        => in_array($this->request->getPost('status'), ['aktif', 'nonaktif'], true)
                               ? $this->request->getPost('status') : 'aktif',
        ];
        $pass = (string) $this->request->getPost('password');

        $galat = [];
        if ($d['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Email tidak valid.';
        if (! $id && strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter untuk pegawai baru.';
        if ($id && $pass !== '' && strlen($pass) < 6) $galat[] = 'Password baru minimal 6 karakter.';

        $dupe = $this->db->table('users')->where('email', $d['email'])->where('id !=', $id)
                     ->countAllResults() > 0;
        if ($dupe) $galat[] = 'Email sudah digunakan pegawai lain.';

        [$kategoriJab, $jabatanId, $galatJab] = (new Struktur())->resolusi(
            (string) $this->request->getPost('jabatan_kategori'),
            (int) $this->request->getPost('jabatan_id'),
            $id
        );
        if ($galatJab !== '') $galat[] = $galatJab;
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id']       = $jabatanId;

        $statusPegawai = (string) $this->request->getPost('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = (new Struktur())->resolusiPosisi(
            (string) $this->request->getPost('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $this->request->getPost('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') $galat[] = $galatPosisi;
        $d['posisi']           = $posisi;
        $d['status_pegawai']   = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($d['sub_unit_id']) {
            $sah = $this->db->table('sub_unit')->where('id', $d['sub_unit_id'])
                        ->where('unit_kerja_id', $d['unit_kerja_id'])->countAllResults() > 0;
            if (! $sah) $d['sub_unit_id'] = null;
        }

        if ($galat) {
            return redirect()->to('admin/pegawai/form' . ($id ? '/' . $id : ''))
                ->with('flash_gagal', implode(' ', $galat));
        }

        if ($id) {
            $lama = $this->db->table('users')->select('shift_id')->where('id', $id)
                         ->get()->getRowArray();
            if ($pass !== '') {
                $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            }
            $this->db->table('users')->where('id', $id)->update($d);

            if ($d['shift_id'] && (int) ($lama['shift_id'] ?? 0) !== (int) $d['shift_id']) {
                $this->db->table('jadwal_shift')->insert([
                    'user_id' => $id, 'shift_id' => $d['shift_id'],
                    'tanggal_berlaku' => date('Y-m-d'),
                    'diubah_oleh' => session('uid'), 'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            catat_aktivitas('Ubah Pegawai', $d['nama_lengkap'] . ' (' . $d['email'] . ')');
            $pesan = 'Data pegawai diperbarui.';
        } else {
            $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            $d['created_at']    = date('Y-m-d H:i:s');
            $this->db->table('users')->insert($d);
            catat_aktivitas('Tambah Pegawai', $d['nama_lengkap'] . ' (' . $d['email'] . ')');
            $pesan = 'Pegawai baru berhasil ditambahkan.';
        }

        return redirect()->to('admin/pegawai')->with('flash_sukses', $pesan);
    }

    public function ubahStatus()
    {
        $id = (int) $this->request->getPost('id');
        if ($id === (int) session('uid')) {
            return redirect()->to('admin/pegawai')
                ->with('flash_gagal', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $u = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        if ($u) {
            $baru = $u['status'] === 'aktif' ? 'nonaktif' : 'aktif';
            $this->db->table('users')->where('id', $id)->update(['status' => $baru]);
            catat_aktivitas('Status Pegawai', $u['nama_lengkap'] . ' → ' . $baru);
        }
        return redirect()->to('admin/pegawai')->with('flash_sukses', 'Status pegawai diperbarui.');
    }

    public function hapus()
    {
        $id = (int) $this->request->getPost('id');
        if ($id === (int) session('uid')) {
            return redirect()->to('admin/pegawai')
                ->with('flash_gagal', 'Anda tidak dapat menghapus akun sendiri.');
        }
        $u = $this->db->table('users')->where('id', $id)->get()->getRowArray();
        if ($u) {
            $this->db->table('users')->where('id', $id)->delete();
            catat_aktivitas('Hapus Pegawai',
                $u['nama_lengkap'] . ' (' . $u['email'] . ') beserta seluruh data absensinya');
        }
        return redirect()->to('admin/pegawai')
            ->with('flash_sukses', 'Pegawai beserta seluruh data absensinya telah dihapus.');
    }
}
