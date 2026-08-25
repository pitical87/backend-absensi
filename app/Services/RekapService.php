<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\Pengaturan;
use App\Models\User;
use Carbon\Carbon;

class RekapService
{
    public function hitung(int $userId, int $bulan, int $tahun): array
    {
        $hariDalamBulan = (int) Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $sekarang = Carbon::now();

        if ($tahun > $sekarang->year || ($tahun === $sekarang->year && $bulan > $sekarang->month)) {
            $hariBerjalan = 0;
        } elseif ($tahun === $sekarang->year && $bulan === $sekarang->month) {
            $hariBerjalan = (int) $sekarang->format('j');
        } else {
            $hariBerjalan = $hariDalamBulan;
        }

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hariDalamBulan);

        pastikan_libur_tetap($tahun);

        $liburMap = [];
        foreach (HariLibur::where('tanggal', '>=', $awal)->where('tanggal', '<=', $akhir)->get() as $r) {
            $liburMap[$r->tanggal->format('Y-m-d')] = $r->keterangan ?: 'Hari libur';
        }
        $mingguLibur = Pengaturan::ambil('minggu_libur', '0') === '1';

        $izinMap = [];
        foreach (Izin::where('user_id', $userId)
            ->where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $akhir)
            ->where('tanggal_selesai', '>=', $awal)
            ->get() as $iz) {
            $t = max($iz->tanggal_mulai->timestamp, strtotime($awal));
            $u = min($iz->tanggal_selesai->timestamp, strtotime($akhir));
            for ($d = $t; $d <= $u; $d += 86400) {
                $izinMap[date('Y-m-d', $d)] = $iz->jenis;
            }
        }

        $isDokter = User::find($userId)?->profesi?->nama === 'Dokter';

        $absCollection = Absensi::where('user_id', $userId)
            ->where('tanggal', '>=', $awal)
            ->where('tanggal', '<=', $akhir)
            ->get();

        if ($isDokter) {
            $absPerTanggal = $absCollection->groupBy(fn ($r) => $r->tanggal->format('Y-m-d'));
        } else {
            $absMap = [];
            foreach ($absCollection as $r) {
                $absMap[$r->tanggal->format('Y-m-d')] = $r;
            }
        }

        $c = ['hadir' => 0, 'tepat' => 0, 'terlambat' => 0, 'menit_terlambat' => 0,
            'alpa' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'dinas_luar' => 0,
            'libur' => 0, 'total_menit' => 0, 'anomali' => 0,
            'tepat_masuk' => 0, 'tepat_pulang' => 0,
            'pulang_awal' => 0, 'menit_pulang_awal' => 0,
            'bintang_sum' => 0.0, 'bintang_terhitung' => 0];
        $perTanggal = [];

        for ($d = 1; $d <= $hariBerjalan; $d++) {
            $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);

            if ($isDokter) {
                $recs = $absPerTanggal[$tgl] ?? collect();
                $rec = $recs->first();
                $jumlahSesi = $recs->count();
            } else {
                $rec = $absMap[$tgl] ?? null;
                $jumlahSesi = $rec ? 1 : 0;
            }

            if ($rec) {
                $c['hadir']++;
                if ($rec->flag_anomali) {
                    $c['anomali']++;
                }

                if (! $isDokter) {
                    $bintangMasuk = $rec->bintang_masuk !== null ? (int) $rec->bintang_masuk : null;
                    $bintangPulang = $rec->bintang_pulang !== null ? (int) $rec->bintang_pulang : null;

                    if ($bintangMasuk === 5 || ($bintangMasuk === null && $rec->status_masuk === 'Tepat Waktu')) {
                        $c['tepat_masuk']++;
                    }
                    if ($bintangPulang === 5 || ($bintangPulang === null && $rec->waktu_pulang !== null && $rec->menit_awal_pulang === 0)) {
                        $c['tepat_pulang']++;
                    }

                    $menitAwal = (int) ($rec->menit_awal_pulang ?? 0);
                    if ($menitAwal > 0) {
                        $c['pulang_awal']++;
                        $c['menit_pulang_awal'] += $menitAwal;
                    }

                    if ($rec->bintang_harian !== null) {
                        $c['bintang_sum'] += (float) $rec->bintang_harian;
                        $c['bintang_terhitung']++;
                    }

                    if (! $rec->waktu_pulang) {
                        $status = 'Belum Pulang';
                    } elseif ($rec->status_masuk === 'Terlambat') {
                        $status = 'Terlambat';
                        $c['terlambat']++;
                        $c['menit_terlambat'] += (int) $rec->menit_terlambat;
                    } else {
                        $status = 'Tepat Waktu';
                        $c['tepat']++;
                    }
                    $c['total_menit'] += (int) ($rec->total_menit_kerja ?? 0);
                } else {
                    $status = ! $rec->waktu_pulang ? 'Belum Pulang' : 'Hadir';
                }
            } elseif (isset($izinMap[$tgl])) {
                $status = $izinMap[$tgl];
                match ($status) {
                    'Izin' => $c['izin']++,
                    'Sakit' => $c['sakit']++,
                    'Cuti' => $c['cuti']++,
                    'Dinas Luar' => $c['dinas_luar']++,
                    default => null,
                };
            } elseif (isset($liburMap[$tgl]) || ($mingguLibur && (int) Carbon::parse($tgl)->format('w') === 0)) {
                $status = 'Libur';
                $c['libur']++;
            } else {
                $status = 'Alpa';
                $c['alpa']++;
                if (! $isDokter) {
                    $c['bintang_terhitung']++;
                }
            }

            $perTanggal[$tgl] = [
                'status' => $status,
                'rec' => $rec,
                'keterangan' => $liburMap[$tgl] ?? null,
                'jumlah_sesi' => $jumlahSesi,
            ];
        }

        $hariEfektif = $c['hadir'] + $c['dinas_luar'] + $c['alpa'];
        $persen = $hariEfektif > 0
            ? round(($c['hadir'] + $c['dinas_luar']) / $hariEfektif * 100, 1)
            : 0.0;

        $bintangBulanan = $c['bintang_terhitung'] > 0
            ? round($c['bintang_sum'] / $c['bintang_terhitung'], 1)
            : null;

        return $c + [
            'is_dokter' => $isDokter,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'hari_dalam_bulan' => $hariDalamBulan,
            'hari_berjalan' => $hariBerjalan,
            'hari_efektif' => $hariEfektif,
            'persen' => $persen,
            'bintang_bulanan' => $bintangBulanan,
            'persen_tepat_masuk' => $c['hadir'] > 0 ? round($c['tepat_masuk'] / $c['hadir'] * 100, 1) : 0.0,
            'persen_tepat_pulang' => $c['hadir'] > 0 ? round($c['tepat_pulang'] / $c['hadir'] * 100, 1) : 0.0,
            'per_tanggal' => $perTanggal,
        ];
    }
}
