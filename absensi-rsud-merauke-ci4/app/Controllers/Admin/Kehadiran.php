<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Kehadiran extends BaseController
{
    public function index()
    {
        $tanggal = (string) $this->request->getGet('tanggal');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        $fUnit       = (int) $this->request->getGet('unit');
        $hanyaAnomali = $this->request->getGet('anomali') === '1';

        $b = $this->db->table('absensi as a')
            ->select("a.*, u.nama_lengkap, uk.nama AS unit_nama, su.nama AS sub_nama,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang,
                      (SELECT jarak_meter FROM log_lokasi l
                        WHERE l.absensi_id = a.id AND l.tipe = 'datang'
                     ORDER BY l.id DESC LIMIT 1) AS jarak_datang")
            ->join('users as u', 'u.id = a.user_id')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('shift as s', 's.id = a.shift_id', 'left')
            ->where('a.tanggal', $tanggal);
        if ($fUnit)        $b->where('u.unit_kerja_id', $fUnit);
        if ($hanyaAnomali) $b->where('a.flag_anomali', 1);
        $rows = $b->orderBy('a.waktu_masuk')->get()->getResultArray();

        // Percobaan absen yang DITOLAK (di luar radius) pada tanggal tsb — bahan audit
        $ditolak = $this->db->table('log_lokasi l')
            ->select('l.*, u.nama_lengkap')
            ->join('users as u', 'u.id = l.user_id')
            ->where('l.ditolak', 1)
            ->where('DATE(l.waktu)', $tanggal)
            ->orderBy('l.waktu')->get()->getResultArray();

        // Titik peta
        $titik = [];
        foreach ($rows as $r) {
            if ($r['lat_masuk'] !== null) {
                $titik[] = ['nama' => $r['nama_lengkap'], 'tipe' => 'Datang',
                    'lat' => (float) $r['lat_masuk'], 'lng' => (float) $r['lng_masuk'],
                    'jam' => jam_id($r['waktu_masuk']), 'anomali' => (bool) $r['flag_anomali']];
            }
            if ($r['lat_pulang'] !== null) {
                $titik[] = ['nama' => $r['nama_lengkap'], 'tipe' => 'Pulang',
                    'lat' => (float) $r['lat_pulang'], 'lng' => (float) $r['lng_pulang'],
                    'jam' => jam_id($r['waktu_pulang']), 'anomali' => (bool) $r['flag_anomali']];
            }
        }

        return view('admin/kehadiran', [
            'judulHalaman' => 'Data Kehadiran',
            'menuAktif'    => 'kehadiran',
            'tanggal'      => $tanggal,
            'fUnit'        => $fUnit,
            'hanyaAnomali' => $hanyaAnomali,
            'rows'         => $rows,
            'ditolak'      => $ditolak,
            'titik'        => $titik,
            'unitList'     => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'rsLat'        => (float) pengaturan('lokasi_lat', 0),
            'rsLng'        => (float) pengaturan('lokasi_lng', 0),
            'radius'       => (float) pengaturan('radius_meter', 100),
        ]);
    }
}
