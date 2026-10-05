<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\RekapService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $hariIni = now()->format('Y-m-d');

        $totalPegawai = User::where('role', 'pegawai')->where('status', 'aktif')->count();

        $stat = Absensi::select(DB::raw("COUNT(*) AS hadir"),
                     DB::raw("SUM(CASE WHEN \"absensi\".\"status_masuk\" = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat"),
                     DB::raw("SUM(CASE WHEN \"absensi\".\"flag_anomali\" = 1 THEN 1 ELSE 0 END) AS anomali"))
            ->join('users as u', function ($q) {
                $q->on('u.id', '=', 'absensi.user_id')->where('u.role', '=', 'pegawai');
            })
            ->where('absensi.tanggal', '=', $hariIni)
            ->first();

        $hadir     = (int) ($stat->hadir ?? 0);
        $terlambat = (int) ($stat->terlambat ?? 0);
        $anomali   = (int) ($stat->anomali ?? 0);

        $izinHariIni = (int) Izin::where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $hariIni)
            ->where('tanggal_selesai', '>=', $hariIni)
            ->count();

        $menunggu = (int) Izin::where('status', 'Menunggu')->count();

        $belum = max(0, $totalPegawai - $hadir - $izinHariIni);

        $terbaru = Absensi::select('absensi.*', 'u.nama_lengkap', 'uk.nama AS unit_nama', 'su.nama AS sub_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang')
            ->join('users as u', 'u.id', '=', 'absensi.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('jadwal_shift as js', function ($join) {
                $join->on('js.user_id', '=', 'absensi.user_id')
                    ->on(DB::raw('DATE(js.tanggal_berlaku)'), '=', DB::raw('DATE(absensi.tanggal)'));
            })
            ->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->whereDate('absensi.tanggal', $hariIni)
            ->orderBy('absensi.waktu_masuk', 'DESC')->limit(12)
            ->get()->all();

        $mulaiBulan = now()->startOfMonth()->toDateString();

        $perTanggal = [];
        foreach (Absensi::select('tanggal', DB::raw('COUNT(*) AS jml'))
                     ->where('tanggal', '>=', $mulaiBulan)
                     ->where('tanggal', '<=', $hariIni)
                     ->groupBy('tanggal')->get() as $r) {
            $perTanggal[$r->tanggal->toDateString()] = (int) $r->jml;
        }
        $grafikBulan = [];
        foreach (range(1, (int) now()->format('j')) as $d) {
            $tgl = now()->startOfMonth()->copy()->addDays($d - 1)->toDateString();
            $grafikBulan[] = ['tgl' => $tgl, 'jml' => $perTanggal[$tgl] ?? 0];
        }
        $maks = max(1, $totalPegawai, ...array_column($grafikBulan, 'jml'));

        $statMap = [];
        foreach (Absensi::select('tanggal',
                     DB::raw('COUNT(*) AS hadir'),
                     DB::raw("SUM(CASE WHEN \"absensi\".\"status_masuk\" = 'Tepat Waktu' THEN 1 ELSE 0 END) AS tepat"),
                     DB::raw("SUM(CASE WHEN \"absensi\".\"status_masuk\" = 'Terlambat' THEN 1 ELSE 0 END) AS telat"))
                 ->where('tanggal', '>=', $mulaiBulan)
                 ->where('tanggal', '<=', $hariIni)
                 ->groupBy('tanggal')->get() as $r) {
            $statMap[$r->tanggal->toDateString()] = [
                'hadir' => (int) $r->hadir,
                'tepat' => (int) $r->tepat,
                'telat' => (int) $r->telat,
            ];
        }
        $grafikGaris = [];
        foreach ($grafikBulan as $g) {
            $s = $statMap[$g['tgl']] ?? [];
            $hadirHari = $s['hadir'] ?? 0;
            $grafikGaris[] = [
                'tgl'   => $g['tgl'],
                'hadir' => $hadirHari,
                'tepat' => $s['tepat'] ?? 0,
                'telat' => $s['telat'] ?? 0,
                'tidak' => max(0, $totalPegawai - $hadirHari),
            ];
        }
        $maksGaris = max(1, $totalPegawai,
            ...array_column($grafikGaris, 'hadir'), ...array_column($grafikGaris, 'tidak'));

        $teladan = [];
        $rekapService = app(RekapService::class);
        $pegawaiAktif = User::select('users.id', 'users.nama_lengkap', 'uk.nama AS unit_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->where('users.role', 'pegawai')->where('users.status', 'aktif')
            ->get()->all();
        $rekapBulanIni = [];
        foreach ($pegawaiAktif as $pg) {
            $r = $rekapService->hitung((int) $pg->id, (int) now()->month, (int) now()->year);
            $rekapBulanIni[(int) $pg->id] = $r;
            if (($r['bintang_bulanan'] ?? 0) >= 4.5) {
                $teladan[] = [
                    'id'      => (int) $pg->id,
                    'nama'    => $pg->nama_lengkap,
                    'unit'    => $pg->unit_nama ?? '—',
                    'bintang' => $r['bintang_bulanan'],
                    'hadir'   => $r['hadir'],
                ];
            }
        }
        usort($teladan, fn ($a, $b) => $b['bintang'] <=> $a['bintang']);
        $teladan = array_slice($teladan, 0, 5);

        pastikan_libur_tetap((int) now()->year);
        $mingguLibur = pengaturan('minggu_libur', '0') === '1';
        $liburSet = [];
        foreach (HariLibur::whereBetween('tanggal', [$mulaiBulan, $hariIni])->get() as $h) {
            $liburSet[$h->tanggal->format('Y-m-d')] = true;
        }
        $hariEfektif = hari_kerja_antara($mulaiBulan, $hariIni, $liburSet, $mingguLibur);

        // Ketaatan memakai pembagi yang sama dengan RekapService, yaitu hari
        // kerja yang benar-benar jatuh tempo (hadir + dinas luar + alpa).
        // Izin, sakit, cuti, dan hari libur bukan hari alpa, jadi tidak boleh
        // ikut dihitung sebagai tidak hadir -- kalau tidak, ketaatan selalu 0%.
        $ketaatan = ['selalu' => 0, 'sering' => 0, 'jarang' => 0, 'tidak_pernah' => 0];
        foreach ($rekapBulanIni as $r) {
            $terhitung = (int) $r['hadir'] + (int) $r['dinas_luar'];
            $efektif   = (int) $r['hari_efektif'];

            if ($terhitung === 0) {
                $ketaatan['tidak_pernah']++;
            } elseif ($efektif > 0 && $terhitung >= $efektif) {
                $ketaatan['selalu']++;
            } elseif ($efektif > 0 && $terhitung * 100 >= $efektif * 75) {
                $ketaatan['sering']++;
            } else {
                $ketaatan['jarang']++;
            }
        }
        $ketaatan['total'] = $ketaatan['selalu'] + $ketaatan['sering']
            + $ketaatan['jarang'] + $ketaatan['tidak_pernah'];
        $ketaatan['hari_efektif'] = $hariEfektif;
        $ketaatan['hari_dalam_bulan'] = (int) now()->daysInMonth;

        $irisanPie = [
            ['label' => 'Selalu Hadir', 'jml' => $ketaatan['selalu'], 'warna' => '#059669'],
            ['label' => 'Sering Hadir (≥75%)', 'jml' => $ketaatan['sering'], 'warna' => '#007AFC'],
            ['label' => 'Jarang Hadir (<75%)', 'jml' => $ketaatan['jarang'], 'warna' => '#D97706'],
            ['label' => 'Tidak Pernah Absen', 'jml' => $ketaatan['tidak_pernah'], 'warna' => '#DC2626'],
        ];
        foreach ($irisanPie as &$s) {
            $s['pct'] = $ketaatan['total'] > 0 ? round($s['jml'] / $ketaatan['total'] * 100, 2) : 0.0;
        }
        unset($s);
        $persenKetaatan = $ketaatan['total'] > 0
            ? round(($ketaatan['selalu'] + $ketaatan['sering']) / $ketaatan['total'] * 100, 2)
            : 0.0;

        $lewatJadwal = $this->rekapLewatJadwal($mulaiBulan, $hariIni, $totalPegawai);

        return view('admin.dashboard.index', [
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
            'grafikBulan'  => $grafikBulan,
            'maks'         => $maks,
            'grafikGaris'  => $grafikGaris,
            'maksGaris'    => $maksGaris,
            'teladan'      => $teladan,
            'ketaatan'     => $ketaatan,
            'irisanPie'    => $irisanPie,
            'persenKetaatan' => $persenKetaatan,
            'lewatJadwal'  => $lewatJadwal,
        ]);
    }

    /**
     * Rekap pegawai aktif bulan ini menurut posisinya terhadap jadwal shift:
     * datang sebelum jam masuk (lebih awal), pulang melewati jam pulang (lebih
     * akhir), dan yang sesuai jam -- yaitu datang paling cepat $toleransi menit
     * sebelum jam masuk dan pulang paling lambat $toleransi menit setelah jam
     * pulang.
     *
     * Memakai aturan waktu yang sama dengan AbsenService (shift Malam yang
     * absennya selepas tengah malam dihitung untuk tanggal sebelumnya, jam
     * pulang yang <= jam masuk berarti lintas hari). Setiap kategori dihitung
     * "pernah" -- satu pegawai bisa masuk lebih dari satu irisan bila dalam
     * bulan ini ada hari ia datang lebih awal dan hari lain ia sesuai jam.
     * Pembagi persentase memakai jumlah pegawai aktif, sama seperti card
     * Ketaatan Absen.
     */
    private function rekapLewatJadwal(string $mulaiBulan, string $hariIni, int $totalPegawai): array
    {
        $baris = Absensi::select('absensi.user_id', 'absensi.tanggal',
                     'absensi.waktu_masuk', 'absensi.waktu_pulang',
                     's.kategori AS shift_kategori',
                     's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang')
            ->join('users as u', function ($q) {
                $q->on('u.id', '=', 'absensi.user_id')
                    ->where('u.role', '=', 'pegawai')->where('u.status', '=', 'aktif');
            })
            ->leftJoin('jadwal_shift as js', function ($join) {
                $join->on('js.user_id', '=', 'absensi.user_id')
                    ->on(DB::raw('DATE(js.tanggal_berlaku)'), '=', DB::raw('DATE(absensi.tanggal)'));
            })
            ->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->where('absensi.tanggal', '>=', $mulaiBulan)
            ->where('absensi.tanggal', '<=', $hariIni)
            ->get();

        $toleransi = 20;
        $datangAwal  = [];
        $pulangAkhir = [];
        $sesuaiJam   = [];

        foreach ($baris as $b) {
            $jamMasuk  = substr((string) $b->shift_masuk, 0, 5);
            $jamPulang = substr((string) $b->shift_pulang, 0, 5);
            if ($jamMasuk === '' || $jamPulang === '') {
                continue;
            }
            $tanggal = $b->tanggal->format('Y-m-d');
            $userId  = (int) $b->user_id;

            // Selisih bertanda: positif berarti keluar dari jadwal melebihi
            // toleransi — datang lebih awal / pulang lebih akhir.
            $selisihMasuk  = null;
            $selisihPulang = null;

            if ($b->waktu_masuk) {
                $jadwalMasuk = Carbon::parse($tanggal.' '.$jamMasuk);
                if ($b->shift_kategori === 'Malam' && $b->waktu_masuk->hour < 12) {
                    $jadwalMasuk->subDay();
                }
                $selisihMasuk = ($jadwalMasuk->getTimestamp() - $b->waktu_masuk->getTimestamp()) / 60;
            }

            if ($b->waktu_pulang) {
                $jadwalPulang = Carbon::parse($tanggal.' '.$jamPulang);
                if ($jamPulang <= $jamMasuk) {
                    $jadwalPulang->addDay();
                }
                $selisihPulang = ($b->waktu_pulang->getTimestamp() - $jadwalPulang->getTimestamp()) / 60;
            }

            if ($selisihMasuk !== null && $selisihMasuk >= $toleransi) {
                $datangAwal[$userId] = true;
            }
            if ($selisihPulang !== null && $selisihPulang >= $toleransi) {
                $pulangAkhir[$userId] = true;
            }
            if (($selisihMasuk === null || $selisihMasuk < $toleransi)
                && ($selisihPulang === null || $selisihPulang < $toleransi)) {
                $sesuaiJam[$userId] = true;
            }
        }

        $irisan = [
            ['label' => 'Datang Lebih Awal', 'jml' => count($datangAwal), 'warna' => '#059669'],
            ['label' => 'Pulang Lebih Akhir', 'jml' => count($pulangAkhir), 'warna' => '#007AFC'],
            ['label' => 'Sesuai Jam', 'jml' => count($sesuaiJam), 'warna' => '#64748B'],
        ];
        foreach ($irisan as &$s) {
            $s['pct'] = $totalPegawai > 0 ? round($s['jml'] / $totalPegawai * 100, 2) : 0.0;
        }
        unset($s);

        $total = count($datangAwal + $pulangAkhir);

        return [
            'datang_awal'  => count($datangAwal),
            'pulang_akhir' => count($pulangAkhir),
            'sesuai_jam'   => count($sesuaiJam),
            'total'        => $total,
            'toleransi'    => $toleransi,
            'ada_data'     => count($datangAwal + $pulangAkhir + $sesuaiJam) > 0,
            'irisan'       => $irisan,
            'persen'       => $totalPegawai > 0 ? round($total / $totalPegawai * 100, 2) : 0.0,
        ];
    }
}
