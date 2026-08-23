<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapKeterlambatanController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) ($request->query('bulan') ?: now()->month);
        $tahun = (int) ($request->query('tahun') ?: now()->year);
        $fUnit = (int) $request->query('unit');

        if ($bulan < 1 || $bulan > 12 || $tahun < 2024 || $tahun > (int) date('Y') + 1) {
            $bulan = (int) now()->month;
            $tahun = (int) now()->year;
        }

        $baris = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->where('u.role', '!=', 'admin')
            ->when($fUnit, fn ($q) => $q->where('u.unit_kerja_id', $fUnit))
            ->groupBy('u.id', 'u.nama_lengkap', 'uk.nama', 'su.nama')
            ->orderByDesc(DB::raw('COALESCE(SUM(k.menit_telat), 0)'))
            ->orderBy('u.nama_lengkap')
            ->get([
                'u.id',
                'u.nama_lengkap',
                'uk.nama AS unit_nama',
                'su.nama AS sub_nama',
                DB::raw('COUNT(*) AS tercatat'),
                DB::raw('SUM(CASE WHEN k.menit_telat > 0 THEN 1 ELSE 0 END) AS jumlah_terlambat'),
                DB::raw('COALESCE(SUM(k.menit_telat), 0) AS total_menit_telat'),
                DB::raw('COALESCE(MAX(k.menit_telat), 0) AS terlama_menit_telat'),
                DB::raw('SUM(CASE WHEN k.menit_awal_pulang > 0 THEN 1 ELSE 0 END) AS jumlah_pulang_awal'),
                DB::raw('COALESCE(SUM(k.menit_awal_pulang), 0) AS total_menit_pulang_awal'),
                DB::raw('ROUND(AVG(k.bintang_masuk), 1) AS rata_bintang_masuk'),
                DB::raw('ROUND(AVG(k.bintang_pulang), 1) AS rata_bintang_pulang'),
                DB::raw('ROUND(AVG(k.total_bintang), 1) AS rata_bintang'),
            ]);

        $total = [
            'tercatat' => $baris->sum('tercatat'),
            'terlambat' => (int) $baris->sum('jumlah_terlambat'),
            'menit_telat' => (int) $baris->sum('total_menit_telat'),
            'pulang_awal' => (int) $baris->sum('jumlah_pulang_awal'),
            'menit_awal' => (int) $baris->sum('total_menit_pulang_awal'),
        ];

        return view('admin.rekap_keterlambatan', [
            'judulHalaman' => 'Rekap Keterlambatan',
            'menuAktif'    => 'rekap_keterlambatan',
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'fUnit'        => $fUnit,
            'unitList'     => UnitKerja::orderBy('id')->get()->all(),
            'baris'        => $baris,
            'total'        => $total,
        ]);
    }
}
