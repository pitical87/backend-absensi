<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $bulan     = (int) $request->get('bulan', now()->month);
        $tahun     = (int) $request->get('tahun', now()->year);
        $subUnitId = (int) $request->get('sub_unit');

        $subUnits = DB::table('sub_unit as su')
            ->join('unit_kerja as uk', 'uk.id', '=', 'su.unit_kerja_id')
            ->select('su.id', 'su.nama', 'uk.nama as unit_nama')
            ->orderBy('uk.nama')->orderBy('su.nama')
            ->get();

        $pegawai  = [];
        $jadwal   = [];

        if ($subUnitId) {
            $pegawai = DB::table('users')
                ->where('sub_unit_id', $subUnitId)
                ->where('role', '!=', 'admin')
                ->where('status', 'aktif')
                ->orderBy('nama_lengkap')
                ->get();

            $userIds = collect($pegawai)->pluck('id')->toArray();

            $awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
            $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
                cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

            $jadwalRows = DB::table('jadwal_shift')
                ->whereIn('user_id', $userIds)
                ->where('tanggal_berlaku', '>=', $awal)
                ->where('tanggal_berlaku', '<=', $akhir)
                ->get();

            foreach ($jadwalRows as $j) {
                $jadwal[$j->user_id][$j->tanggal_berlaku] = (int) $j->shift_id;
            }
        }

        $shiftList = DB::table('shift')->where('aktif', 1)->orderBy('jam_masuk')->get();

        $hariDalamBulan = (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        return view('admin.jadwal', [
            'judulHalaman'   => 'Jadwal Shift per Sub Unit',
            'menuAktif'      => 'jadwal',
            'subUnits'       => $subUnits,
            'subUnitId'      => $subUnitId,
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'pegawai'        => $pegawai,
            'jadwal'         => $jadwal,
            'shiftList'      => $shiftList,
            'hariDalamBulan' => $hariDalamBulan,
        ]);
    }

    public function aksi(Request $request)
    {
        $subUnitId = (int) $request->input('sub_unit_id');
        $bulan     = (int) $request->input('bulan');
        $tahun     = (int) $request->input('tahun');
        $grid      = $request->input('grid', []);

        $awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $userIds = DB::table('users')
            ->where('sub_unit_id', $subUnitId)
            ->where('role', '!=', 'admin')
            ->pluck('id')
            ->toArray();

        DB::table('jadwal_shift')
            ->whereIn('user_id', $userIds)
            ->where('tanggal_berlaku', '>=', $awal)
            ->where('tanggal_berlaku', '<=', $akhir)
            ->delete();

        $rows = [];
        foreach ($grid as $userId => $dates) {
            foreach ($dates as $tanggal => $shiftId) {
                if (!$shiftId) continue;
                $rows[] = [
                    'user_id'         => (int) $userId,
                    'shift_id'        => (int) $shiftId,
                    'tanggal_berlaku' => $tanggal,
                    'diubah_oleh'     => session('uid'),
                    'created_at'      => now(),
                ];
            }
        }

        if ($rows) {
            DB::table('jadwal_shift')->insert($rows);
        }

        $hariDalamBulan = (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
        foreach ($grid as $userId => $dates) {
            $lastShift = null;
            for ($d = $hariDalamBulan; $d >= 1; $d--) {
                $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                if (isset($dates[$tgl]) && $dates[$tgl]) {
                    $lastShift = (int) $dates[$tgl];
                    break;
                }
            }
            if ($lastShift) {
                DB::table('users')->where('id', $userId)->update(['shift_id' => $lastShift]);
            }
        }

        $subUnitNama = DB::table('sub_unit')->where('id', $subUnitId)->value('nama') ?? '#'.$subUnitId;
        catat_aktivitas('Atur Jadwal Shift', "Sub Unit $subUnitNama bulan " . BULAN_ID[$bulan] . "/$tahun");

        return redirect(url("admin/jadwal?sub_unit=$subUnitId&bulan=$bulan&tahun=$tahun"))
            ->with('flash_sukses', 'Jadwal shift berhasil disimpan.');
    }
}
