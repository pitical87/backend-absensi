<?php

namespace App\Libraries;

use Config\Database;

/**
 * Rekap — menghitung rekapitulasi kehadiran bulanan seorang pegawai.
 *
 * Status per hari (prioritas dari atas):
 *   1. Ada absensi        → Tepat Waktu / Terlambat / Belum Pulang
 *   2. Izin disetujui     → Izin / Sakit / Cuti / Dinas Luar
 *   3. Hari libur         → Libur (dari tabel hari_libur, + hari Minggu bila diaktifkan)
 *   4. Selainnya          → Alpa (tidak hadir tanpa keterangan)
 *
 * Hari efektif  = hari berjalan yang berstatus Hadir, Dinas Luar, atau Alpa
 *                 (libur dan izin/sakit/cuti tidak menghukum persentase).
 * Kehadiran (%) = (Hadir + Dinas Luar) / Hari efektif.
 */
class Rekap
{
    public function hitung(int $userId, int $bulan, int $tahun): array
    {
        $db = Database::connect();

        $hariDalamBulan = (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
        $sekarangBulan  = (int) date('n');
        $sekarangTahun  = (int) date('Y');

        // Hari yang sudah berjalan pada bulan tsb
        if ($tahun > $sekarangTahun || ($tahun === $sekarangTahun && $bulan > $sekarangBulan)) {
            $hariBerjalan = 0;
        } elseif ($tahun === $sekarangTahun && $bulan === $sekarangBulan) {
            $hariBerjalan = (int) date('j');
        } else {
            $hariBerjalan = $hariDalamBulan;
        }

        $awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hariDalamBulan);

        pastikan_libur_tetap($tahun);

        // ---- Hari libur ----
        $liburMap = [];
        foreach ($db->table('hari_libur')
                    ->where('tanggal >=', $awal)->where('tanggal <=', $akhir)
                    ->get()->getResultArray() as $r) {
            $liburMap[$r['tanggal']] = $r['keterangan'] ?: 'Hari libur';
        }
        $mingguLibur = pengaturan('minggu_libur', '0') === '1';

        // ---- Izin disetujui (rentang di-ekspansi per hari) ----
        $izinMap = [];
        foreach ($db->table('pengajuan_izin')
                    ->where('user_id', $userId)->where('status', 'Disetujui')
                    ->where('tanggal_mulai <=', $akhir)->where('tanggal_selesai >=', $awal)
                    ->get()->getResultArray() as $iz) {
            $t = max(strtotime($iz['tanggal_mulai']), strtotime($awal));
            $u = min(strtotime($iz['tanggal_selesai']), strtotime($akhir));
            for ($d = $t; $d <= $u; $d += 86400) {
                $izinMap[date('Y-m-d', $d)] = $iz['jenis'];
            }
        }

        // ---- Absensi bulan tsb ----
        $absMap = [];
        foreach ($db->table('absensi as a')
                    ->select('a.*, s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk,
                              s.jam_pulang AS shift_pulang')
                    ->join('shift as s', 's.id = a.shift_id', 'left')
                    ->where('a.user_id', $userId)
                    ->where('a.tanggal >=', $awal)->where('a.tanggal <=', $akhir)
                    ->get()->getResultArray() as $r) {
            $absMap[$r['tanggal']] = $r;
        }

        // ---- Susun status per hari ----
        $c = ['hadir' => 0, 'tepat' => 0, 'terlambat' => 0, 'menit_terlambat' => 0,
              'alpa' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'dinas_luar' => 0,
              'libur' => 0, 'total_menit' => 0, 'anomali' => 0];
        $perTanggal = [];

        for ($d = 1; $d <= $hariBerjalan; $d++) {
            $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $rec = $absMap[$tgl] ?? null;

            if ($rec) {
                $c['hadir']++;
                if ($rec['flag_anomali']) $c['anomali']++;
                if (! $rec['waktu_pulang']) {
                    $status = 'Belum Pulang';
                } elseif ($rec['status_masuk'] === 'Terlambat') {
                    $status = 'Terlambat';
                    $c['terlambat']++;
                    $c['menit_terlambat'] += (int) $rec['menit_terlambat'];
                } else {
                    $status = 'Tepat Waktu';
                    $c['tepat']++;
                }
                if ($rec['waktu_pulang'] && $rec['status_masuk'] !== 'Terlambat') {
                    // sudah dihitung tepat di atas
                }
                $c['total_menit'] += (int) ($rec['total_menit_kerja'] ?? 0);
            } elseif (isset($izinMap[$tgl])) {
                $status = $izinMap[$tgl];
                match ($status) {
                    'Izin'       => $c['izin']++,
                    'Sakit'      => $c['sakit']++,
                    'Cuti'       => $c['cuti']++,
                    'Dinas Luar' => $c['dinas_luar']++,
                    default      => null,
                };
            } elseif (isset($liburMap[$tgl]) || ($mingguLibur && date('w', strtotime($tgl)) === '0')) {
                $status = 'Libur';
                $c['libur']++;
            } else {
                $status = 'Alpa';
                $c['alpa']++;
            }

            $perTanggal[$tgl] = [
                'status'     => $status,
                'rec'        => $rec,
                'keterangan' => $liburMap[$tgl] ?? null,
            ];
        }

        $hariEfektif = $c['hadir'] + $c['dinas_luar'] + $c['alpa'];
        $persen      = $hariEfektif > 0
            ? round(($c['hadir'] + $c['dinas_luar']) / $hariEfektif * 100, 1)
            : 0.0;

        return $c + [
            'bulan'            => $bulan,
            'tahun'            => $tahun,
            'hari_dalam_bulan' => $hariDalamBulan,
            'hari_berjalan'    => $hariBerjalan,
            'hari_efektif'     => $hariEfektif,
            'persen'           => $persen,
            'per_tanggal'      => $perTanggal,
        ];
    }
}
