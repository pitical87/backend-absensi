<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\AlurIzin;

/**
 * Persetujuan pengajuan Izin / Sakit / Cuti / Dinas Luar (sisi admin).
 *
 * Sakit & Dinas Luar: persetujuan satu tahap langsung oleh admin (tak berubah).
 * Izin & Cuti: berjalan melalui alur berjenjang (lihat App\Libraries\AlurIzin) yang
 * diputus oleh pejabat terkait di sisi pegawai (menu Persetujuan); admin di sini
 * hanya memiliki hak AMBIL ALIH pada tahap yang sedang berjalan — dipakai bila
 * pejabat terkait belum terdaftar (mis. akun HRD belum dibuat) atau berhalangan.
 */
class Izin extends BaseController
{
    public function index()
    {
        $status = (string) ($this->request->getGet('status') ?: 'Menunggu');
        if (! in_array($status, ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'], true)) {
            $status = 'Menunggu';
        }

        $b = $this->db->table('pengajuan_izin i')
            ->select('i.*, u.nama_lengkap, u.posisi AS posisi_pemohon,
                      uk.nama AS unit_nama, su.nama AS sub_nama,
                      adm.nama_lengkap AS admin_nama')
            ->join('users as u', 'u.id = i.user_id')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('users adm', 'adm.id = i.diproses_oleh', 'left');
        if ($status !== 'Semua') {
            $b->where('i.status', $status);
        }
        $daftar = $b->orderBy('i.id', 'DESC')->limit(200)->get()->getResultArray();

        // Jejak tahap untuk baris berjenjang (Izin/Cuti)
        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($daftar,
            static fn ($r) => in_array($r['jenis'], ['Izin', 'Cuti'], true)), 'id');
        if ($idBerjenjang) {
            foreach ($this->db->table('izin_persetujuan p')
                         ->select('p.*, o.nama_lengkap AS oleh_nama')
                         ->join('users o', 'o.id = p.oleh_user_id', 'left')
                         ->whereIn('pengajuan_id', $idBerjenjang)
                         ->orderBy('tahap')->get()->getResultArray() as $p) {
                $tahapPer[(int) $p['pengajuan_id']][] = $p;
            }
        }

        $jumlah = [];
        foreach ($this->db->table('pengajuan_izin')
                     ->select('status, COUNT(*) AS jml')->groupBy('status')
                     ->get()->getResultArray() as $r) {
            $jumlah[$r['status']] = (int) $r['jml'];
        }

        return view('admin/izin', [
            'judulHalaman' => 'Persetujuan Izin & Cuti',
            'menuAktif'    => 'izin',
            'daftar'       => $daftar,
            'tahapPer'     => $tahapPer,
            'status'       => $status,
            'jumlah'       => $jumlah,
        ]);
    }

    /** Sakit & Dinas Luar — persetujuan satu tahap (tak berubah dari versi sebelumnya). */
    public function proses()
    {
        $id      = (int) $this->request->getPost('id');
        $putusan = (string) $this->request->getPost('putusan'); // setuju|tolak
        $catatan = trim((string) $this->request->getPost('catatan')) ?: null;

        $iz = $this->db->table('pengajuan_izin i')
            ->select('i.*, u.nama_lengkap')->join('users as u', 'u.id = i.user_id')
            ->where('i.id', $id)->get()->getRowArray();

        if (! $iz || $iz['status'] !== 'Menunggu' || in_array($iz['jenis'], ['Izin', 'Cuti'], true)) {
            return redirect()->to('admin/izin')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan, sudah diproses, atau memakai alur berjenjang.');
        }

        $statusBaru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';
        $this->db->table('pengajuan_izin')->where('id', $id)->update([
            'status'        => $statusBaru,
            'diproses_oleh' => session('uid'),
            'catatan_admin' => $catatan,
            'processed_at'  => date('Y-m-d H:i:s'),
        ]);
        catat_aktivitas('Proses Izin', $iz['nama_lengkap'] . ' — ' . $iz['jenis'] . ' ('
            . $iz['tanggal_mulai'] . ' s.d. ' . $iz['tanggal_selesai'] . ') → ' . $statusBaru);

        return redirect()->to('admin/izin')->with('flash_sukses',
            'Pengajuan ' . $iz['jenis'] . ' atas nama ' . $iz['nama_lengkap'] . ' telah ' . strtolower($statusBaru) . '.');
    }

    /** Izin & Cuti — admin mengambil alih tahap yang sedang berjalan. */
    public function ambilAlih()
    {
        $id      = (int) $this->request->getPost('id');
        $putusan = (string) $this->request->getPost('putusan');
        $catatan = trim((string) $this->request->getPost('catatan')) ?: 'Diambil alih oleh admin.';

        $iz = $this->db->table('pengajuan_izin')->where('id', $id)->get()->getRowArray();
        if (! $iz || $iz['status'] !== 'Menunggu' || (int) $iz['tahap_aktif'] === 0) {
            return redirect()->to('admin/izin')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah selesai diproses.');
        }
        $pemohon = $this->db->table('users')->where('id', $iz['user_id'])->get()->getRowArray();

        $hasil = (new AlurIzin())->proses($iz, $pemohon, (int) session('uid'), $putusan, $catatan);
        catat_aktivitas('Ambil Alih Persetujuan', $pemohon['nama_lengkap'] . ' — ' . $iz['jenis']
            . ' tahap ' . label_tahap_izin((int) $iz['tahap_aktif']) . ' → ' . $hasil);

        return redirect()->to('admin/izin')->with('flash_sukses',
            'Tahap ' . label_tahap_izin((int) $iz['tahap_aktif']) . ' untuk ' . $pemohon['nama_lengkap']
            . ' telah diproses admin (' . $hasil . ').');
    }
}
