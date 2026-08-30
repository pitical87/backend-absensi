<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\RekapService;
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
        foreach ($pegawaiAktif as $pg) {
            $r = $rekapService->hitung((int) $pg->id, (int) now()->month, (int) now()->year);
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

        $pegawaiIds = User::where('role', 'pegawai')->where('status', 'aktif')->pluck('id');
        $hadirBulan = Absensi::whereIn('user_id', $pegawaiIds)
            ->where('tanggal', '>=', $mulaiBulan)->where('tanggal', '<=', $hariIni)
            ->select('user_id', DB::raw('COUNT(*) AS jml'))
            ->groupBy('user_id')->pluck('jml', 'user_id');

        $ketaatan = ['selalu' => 0, 'sering' => 0, 'jarang' => 0, 'tidak_pernah' => 0];
        foreach ($pegawaiIds as $pid) {
            $h = (int) ($hadirBulan[$pid] ?? 0);
            if ($h === 0) {
                $ketaatan['tidak_pernah']++;
            } elseif ($hariEfektif > 0 && $h >= $hariEfektif) {
                $ketaatan['selalu']++;
            } elseif ($hariEfektif > 0 && $h >= ceil($hariEfektif * 0.75)) {
                $ketaatan['sering']++;
            } else {
                $ketaatan['jarang']++;
            }
        }
        $ketaatan['total'] = array_sum($ketaatan);
        $ketaatan['hari_efektif'] = $hariEfektif;
        $ketaatan['hari_dalam_bulan'] = (int) now()->daysInMonth;

        $irisanPie = [
            ['label' => 'Selalu Hadir', 'jml' => $ketaatan['selalu'], 'warna' => '#059669'],
            ['label' => 'Sering Hadir (≥75%)', 'jml' => $ketaatan['sering'], 'warna' => '#007AFC'],
            ['label' => 'Jarang Hadir (<75%)', 'jml' => $ketaatan['jarang'], 'warna' => '#D97706'],
            ['label' => 'Tidak Pernah Absen', 'jml' => $ketaatan['tidak_pernah'], 'warna' => '#DC2626'],
        ];
        foreach ($irisanPie as &$s) {
            $s['pct'] = $ketaatan['total'] > 0 ? round($s['jml'] / $ketaatan['total'] * 100, 1) : 0.0;
        }
        unset($s);
        $persenKetaatan = $ketaatan['total'] > 0
            ? round(($ketaatan['selalu'] + $ketaatan['sering']) / $ketaatan['total'] * 100)
            : 0;

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
        ]);
    }
}
