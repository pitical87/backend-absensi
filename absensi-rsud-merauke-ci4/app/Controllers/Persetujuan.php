<?php

namespace App\Controllers;

use App\Libraries\AlurIzin;

/**
 * Persetujuan — halaman kerja bagi pegawai berposisi Koordinator/Kepala Unit,
 * Kepala Seksi/Sub Bagian, Kepala Bidang/Bagian, atau HRD untuk memutus
 * pengajuan Izin/Cuti yang sedang berada di tahap mereka.
 *
 * Ini BUKAN halaman admin — pejabat tetap login sebagai pegawai biasa,
 * menu ini hanya tampil bila posisi mereka memenuhi syarat.
 */
class Persetujuan extends BaseController
{
    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }
        if ($u['posisi'] === 'Staf') {
            return redirect()->to('dashboard')
                ->with('flash_gagal', 'Menu ini hanya tersedia bagi pejabat dalam alur persetujuan.');
        }

        $lib = new AlurIzin();

        // Ambil seluruh pengajuan Menunggu tahap manapun, saring yang menjadi wewenang $u
        $kandidat = $this->db->table('pengajuan_izin i')
            ->select('i.*, p.nama_lengkap, p.jabatan_kategori, p.jabatan_id, p.seksi_pembina_id,
                      p.posisi AS posisi_pemohon, p.unit_kerja_id, p.sub_unit_id, p.nip,
                      uk.nama AS unit_nama, su.nama AS sub_nama')
            ->join('users p', 'p.id = i.user_id')
            ->join('unit_kerja as uk', 'uk.id = p.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = p.sub_unit_id', 'left')
            ->where('i.status', 'Menunggu')->where('i.tahap_aktif >', 0)
            ->orderBy('i.tahap_aktif')->orderBy('i.id')
            ->get()->getResultArray();

        $tugasSaya = [];
        foreach ($kandidat as $r) {
            $pemohon = [
                'id' => $r['user_id'], 'posisi' => $r['posisi_pemohon'],
                'jabatan_id' => $r['jabatan_id'], 'seksi_pembina_id' => $r['seksi_pembina_id'],
                'unit_kerja_id' => $r['unit_kerja_id'], 'sub_unit_id' => $r['sub_unit_id'],
            ];
            $pengajuanRingkas = ['id' => $r['id'], 'tahap_aktif' => $r['tahap_aktif']];
            if ($lib->bolehBertindak($pengajuanRingkas, $pemohon, $u) && $u['role'] !== 'admin') {
                $tugasSaya[] = $r;
            }
        }

        // Riwayat yang sudah pernah saya putuskan
        $riwayatSaya = $this->db->table('izin_persetujuan p')
            ->select('p.*, i.jenis, i.jenis_cuti, i.tanggal_mulai, i.tanggal_selesai, u.nama_lengkap')
            ->join('pengajuan_izin i', 'i.id = p.pengajuan_id')
            ->join('users as u', 'u.id = i.user_id')
            ->where('p.oleh_user_id', $u['id'])
            ->orderBy('p.waktu', 'DESC')->limit(30)->get()->getResultArray();

        return view('pegawai/persetujuan', [
            'u' => $u, 'tugasSaya' => $tugasSaya, 'riwayatSaya' => $riwayatSaya,
        ]);
    }

    public function proses()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }

        $id      = (int) $this->request->getPost('id');
        $putusan = (string) $this->request->getPost('putusan');
        $catatan = trim((string) $this->request->getPost('catatan')) ?: null;

        $iz = $this->db->table('pengajuan_izin')->where('id', $id)->get()->getRowArray();
        if (! $iz || $iz['status'] !== 'Menunggu' || (int) $iz['tahap_aktif'] === 0) {
            return redirect()->to('persetujuan')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $pemohon = $this->db->table('users')->where('id', $iz['user_id'])->get()->getRowArray();
        $lib = new AlurIzin();
        if (! $lib->bolehBertindak($iz, $pemohon, $u)) {
            return redirect()->to('persetujuan')
                ->with('flash_gagal', 'Anda tidak berwenang memutus pengajuan ini pada tahap saat ini.');
        }

        $hasil = $lib->proses($iz, $pemohon, (int) $u['id'], $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz['jenis'], $pemohon['nama_lengkap'] . ' — tahap '
            . label_tahap_izin((int) $iz['tahap_aktif']) . ' oleh ' . $u['nama_lengkap'] . ' → ' . $hasil);

        $pesan = match ($hasil) {
            'Ditolak'   => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui penuh — dokumen resmi kini tersedia untuk pemohon.',
            default     => 'Persetujuan Anda tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };
        return redirect()->to('persetujuan')->with('flash_sukses', $pesan);
    }
}
