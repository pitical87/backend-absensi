<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapLogbookController extends Controller
{
    public function index(Request $request)
    {
        $bulan   = (int) ($request->get('bulan') ?: now()->month);
        $tahun   = (int) ($request->get('tahun') ?: now()->year);
        if ($bulan < 1 || $bulan > 12) $bulan = now()->month;
        if ($tahun < 2000 || $tahun > 2100) $tahun = now()->year;

        $q       = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per     = 20;

        // jumlah hari kerja = banyaknya tanggal berbeda yang punya aktivitas logbook
        $rekap = Logbook::query()
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->selectRaw('user_id, COUNT(DISTINCT tanggal) AS jumlah_hari')
            ->groupBy('user_id');

        $b = User::query()
            ->leftJoinSub($rekap, 'r', 'r.user_id', '=', 'users.id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->when($q !== '', fn ($w) => $w->where('users.nama_lengkap', 'like', "%{$q}%"))
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.nip',
                'users.status',
                DB::raw("COALESCE(uk.nama, '-') AS unit_nama"),
                DB::raw("COALESCE(su.nama, '') AS sub_nama"),
                DB::raw('COALESCE(r.jumlah_hari, 0) AS jumlah_hari'),
            );

        $total  = (clone $b)->count();
        $daftar = $b->orderBy('users.nama_lengkap')
                    ->skip(($halaman - 1) * $per)
                    ->take($per)
                    ->get()
                    ->all();

        return view('admin.rekap_logbook', [
            'judulHalaman' => 'Rekap Logbook per Pegawai',
            'menuAktif'    => 'rekap_logbook',
            'daftar'       => $daftar,
            'total'        => $total,
            'halaman'      => $halaman,
            'totalHal'     => max(1, (int) ceil($total / $per)),
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'q'            => $q,
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }

    public function cetak(Request $request)
    {
        $bulan = (int) ($request->get('bulan') ?: now()->month);
        $tahun = (int) ($request->get('tahun') ?: now()->year);
        if ($bulan < 1 || $bulan > 12) $bulan = now()->month;
        if ($tahun < 2000 || $tahun > 2100) $tahun = now()->year;

        $q      = trim((string) $request->get('q'));
        $rekap  = Logbook::query()
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->selectRaw('user_id, COUNT(DISTINCT tanggal) AS jumlah_hari')
            ->groupBy('user_id');

        $daftar = User::query()
            ->leftJoinSub($rekap, 'r', 'r.user_id', '=', 'users.id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->when($q !== '', fn ($w) => $w->where('users.nama_lengkap', 'like', "%{$q}%"))
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.nip',
                DB::raw("COALESCE(uk.nama, '-') AS unit_nama"),
                DB::raw("COALESCE(su.nama, '') AS sub_nama"),
                DB::raw('COALESCE(r.jumlah_hari, 0) AS jumlah_hari'),
            )
            ->orderBy('users.nama_lengkap')
            ->get()
            ->all();

        catat_aktivitas('Cetak Rekap Logbook', BULAN_ID[$bulan].' '.$tahun);

        return view('admin.rekap_logbook_cetak', [
            'daftar'       => $daftar,
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'q'            => $q,
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }

    public function detail(Request $request)
    {
        $f = $request->validate([
            'user_id' => ['required', 'integer'],
            'bulan'   => ['required', 'integer', 'between:1,12'],
            'tahun'   => ['required', 'integer', 'between:2000,2100'],
        ]);

        $user = User::query()
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->where('users.id', (int) $f['user_id'])
            ->selectRaw("users.nama_lengkap,
                COALESCE(uk.nama, '-') AS unit_nama,
                COALESCE(su.nama, '') AS sub_nama")
            ->first();

        if (! $user) {
            return response()->json(['sukses' => false, 'pesan' => 'Pegawai tidak ditemukan.'], 404);
        }

        $entri = Logbook::query()
            ->where('user_id', (int) $f['user_id'])
            ->whereMonth('tanggal', (int) $f['bulan'])
            ->whereYear('tanggal', (int) $f['tahun'])
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->get();

        // kelompokkan per tanggal
        $grup = [];
        foreach ($entri as $e) {
            $tgl = $e->tanggal->format('Y-m-d');
            $grup[$tgl][] = [
                'jam'       => substr((string) $e->jam, 0, 5),
                'isi'       => (string) $e->isi,
                'verified'  => (bool) $e->is_verified,
            ];
        }

        return response()->json([
            'sukses'      => true,
            'nama'        => $user->nama_lengkap,
            'unit'        => trim($user->unit_nama.($user->sub_nama ? ' — '.$user->sub_nama : '')),
            'total_hari'  => count($grup),
            'total_entri' => $entri->count(),
            'data'        => $grup,
        ]);
    }
}
