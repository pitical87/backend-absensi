<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $hariIni = date('Y-m-d');

        $totalPegawai = $this->db->table('users')
            ->where('role', 'pegawai')->where('status', 'aktif')->countAllResults();

        $stat = $this->db->table('absensi as a')
            ->select("COUNT(*) AS hadir,
                      SUM(a.status_masuk = 'Terlambat') AS terlambat,
                      SUM(a.flag_anomali = 1) AS anomali")
            ->join('users as u', "u.id = a.user_id AND u.role = 'pegawai'")
            ->where('a.tanggal', $hariIni)
            ->get()->getRowArray();

        $hadir     = (int) ($stat['hadir'] ?? 0);
        $terlambat = (int) ($stat['terlambat'] ?? 0);
        $anomali   = (int) ($stat['anomali'] ?? 0);

        // Pegawai yang hari ini berstatus izin disetujui
        $izinHariIni = (int) $this->db->table('pengajuan_izin')
            ->where('status', 'Disetujui')
            ->where('tanggal_mulai <=', $hariIni)->where('tanggal_selesai >=', $hariIni)
            ->countAllResults();

        $menunggu = (int) $this->db->table('pengajuan_izin')
            ->where('status', 'Menunggu')->countAllResults();

        $belum = max(0, $totalPegawai - $hadir - $izinHariIni);

        // Absensi terbaru hari ini
        $terbaru = $this->db->table('absensi as a')
            ->select('a.*, u.nama_lengkap, uk.nama AS unit_nama, su.nama AS sub_nama,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang')
            ->join('users as u', 'u.id = a.user_id')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('shift as s', 's.id = a.shift_id', 'left')
            ->where('a.tanggal', $hariIni)
            ->orderBy('a.waktu_masuk', 'DESC')->limit(12)
            ->get()->getResultArray();

        // Grafik hadir per hari, 30 hari terakhir
        $perTanggal = [];
        foreach ($this->db->table('absensi')
                     ->select('tanggal, COUNT(*) AS jml')
                     ->where('tanggal >=', date('Y-m-d', strtotime('-29 days')))
                     ->where('tanggal <=', $hariIni)
                     ->groupBy('tanggal')->get()->getResultArray() as $r) {
            $perTanggal[$r['tanggal']] = (int) $r['jml'];
        }
        $grafik30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $tgl        = date('Y-m-d', strtotime("-{$i} days"));
            $grafik30[] = ['tgl' => $tgl, 'jml' => $perTanggal[$tgl] ?? 0];
        }
        $maks = max(1, $totalPegawai, ...array_column($grafik30, 'jml'));

        return view('admin/dashboard', [
            'judulHalaman' => 'Dashboard',
            'menuAktif'    => 'dashboard',
            'totalPegawai' => $totalPegawai,
            'hadir'        => $hadir,
            'terlambat'    => $terlambat,
            'belum'        => $belum,
            'izinHariIni'  => $izinHariIni,
            'anomali'      => $anomali,
            'menunggu'     => $menunggu,
            'terbaru'      => $terbaru,
            'grafik30'     => $grafik30,
            'maks'         => $maks,
        ]);
    }
}
