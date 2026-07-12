<?php

namespace App\Controllers;

use App\Libraries\Rekap;

class Dashboard extends BaseController
{
    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }
        if ($u['role'] === 'admin') {
            return redirect()->to('admin');
        }

        $hariIni = date('Y-m-d');

        // Absensi terbuka (belum pulang) — termasuk shift malam kemarin
        $recBuka = $this->db->table('absensi as a')
            ->select('a.*, s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang')
            ->join('shift as s', 's.id = a.shift_id', 'left')
            ->where('a.user_id', $u['id'])->where('a.waktu_pulang IS NULL')
            ->orderBy('a.waktu_masuk', 'DESC')->get(1)->getRowArray();

        // Absensi bertanggal hari ini
        $recHariIni = $this->db->table('absensi as a')
            ->select('a.*, s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang')
            ->join('shift as s', 's.id = a.shift_id', 'left')
            ->where('a.user_id', $u['id'])->where('a.tanggal', $hariIni)
            ->get(1)->getRowArray();

        // Shift aktif dikelompokkan
        $shiftGrup = [];
        foreach ($this->db->table('shift')->where('aktif', 1)
                     ->orderBy("FIELD(kategori,'Pagi','Sore','Malam')", '', false)
                     ->orderBy('jam_masuk')->get()->getResultArray() as $s) {
            $shiftGrup[$s['kategori']][] = $s;
        }

        $bolehDatang = ! $recBuka && ! $recHariIni;

        // Izin disetujui yang berlaku hari ini (informasi di dasbor)
        $izinHariIni = $this->db->table('pengajuan_izin')
            ->where('user_id', $u['id'])->where('status', 'Disetujui')
            ->where('tanggal_mulai <=', $hariIni)->where('tanggal_selesai >=', $hariIni)
            ->get(1)->getRowArray();

        $rekap = (new Rekap())->hitung((int) $u['id'], (int) date('n'), (int) date('Y'));

        return view('pegawai/dashboard', [
            'u'               => $u,
            'recBuka'         => $recBuka,
            'recHariIni'      => $recHariIni,
            'recTampil'       => $recBuka ?: $recHariIni,
            'bolehDatang'     => $bolehDatang,
            'bolehPulang'     => (bool) $recBuka,
            'selesai'         => $recHariIni && $recHariIni['waktu_pulang'],
            'shiftGrup'       => $shiftGrup,
            'bolehPilihShift' => pengaturan('izinkan_pilih_shift', '1') === '1' && $bolehDatang,
            'wajibSelfie'     => pengaturan('wajib_selfie', '1') === '1',
            'izinHariIni'     => $izinHariIni,
            'rekap'           => $rekap,
        ]);
    }
}
