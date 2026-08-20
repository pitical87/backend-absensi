<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
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
            ->leftJoin('shift as s', 's.id', '=', 'absensi.shift_id')
            ->where('absensi.tanggal', $hariIni)
            ->orderBy('absensi.waktu_masuk', 'DESC')->limit(12)
            ->get()->all();

        $perTanggal = [];
        foreach (Absensi::select('tanggal', DB::raw('COUNT(*) AS jml'))
                     ->where('tanggal', '>=', now()->subDays(29)->format('Y-m-d'))
                     ->where('tanggal', '<=', $hariIni)
                     ->groupBy('tanggal')->get() as $r) {
            $perTanggal[$r->tanggal] = (int) $r->jml;
        }
        $grafik30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $tgl        = now()->subDays($i)->format('Y-m-d');
            $grafik30[] = ['tgl' => $tgl, 'jml' => $perTanggal[$tgl] ?? 0];
        }
        $maks = max(1, $totalPegawai, ...array_column($grafik30, 'jml'));

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

        return view('admin.dashboard', [
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
            'teladan'      => $teladan,
        ]);
    }
}
