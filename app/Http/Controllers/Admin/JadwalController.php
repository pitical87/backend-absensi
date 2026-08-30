<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\User;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);
        $subUnitId = (int) $request->get('sub_unit');

        $subUnits = SubUnit::select('sub_unit.id', 'sub_unit.nama', 'uk.nama as unit_nama')
            ->join('unit_kerja as uk', 'uk.id', '=', 'sub_unit.unit_kerja_id')
            ->orderBy('uk.nama')->orderBy('sub_unit.nama')
            ->get();

        $pegawai = [];
        $jadwal = [];

        if ($subUnitId) {
            $pegawai = User::where('sub_unit_id', $subUnitId)
                ->where('role', '!=', 'admin')
                ->where('status', 'aktif')
                ->orderBy('nama_lengkap')
                ->get();

            $userIds = collect($pegawai)->pluck('id')->toArray();

            $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
            $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
                cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

            $jadwalRows = JadwalShift::whereIn('user_id', $userIds)
                ->where('tanggal_berlaku', '>=', $awal)
                ->where('tanggal_berlaku', '<=', $akhir)
                ->get();

            foreach ($jadwalRows as $j) {
                $jadwal[$j->user_id][(string) $j->tanggal_berlaku] = (int) $j->shift_id;
            }
        }

        $shiftList = Shift::where('aktif', 1)->orderBy('jam_masuk')->get();
        // dd($shiftList);
        // dd($subUnits);

        $hariDalamBulan = (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        $semuaPegawai = User::select('users.id', 'users.nama_lengkap',
                'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        // jadwal yang sudah tersimpan untuk semua pegawai aktif pada bulan terpilih
        // (dipakai tab Per Pegawai agar pengisian tidak diulang)
        $awalSemua = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhirSemua = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $jadwalPegawai = [];
        JadwalShift::join('users as u', 'u.id', '=', 'jadwal_shift.user_id')
            ->where('u.role', '!=', 'admin')
            ->where('u.status', 'aktif')
            ->where('jadwal_shift.tanggal_berlaku', '>=', $awalSemua)
            ->where('jadwal_shift.tanggal_berlaku', '<=', $akhirSemua)
            ->get(['jadwal_shift.user_id', 'jadwal_shift.tanggal_berlaku', 'jadwal_shift.shift_id'])
            ->each(function ($j) use (&$jadwalPegawai) {
                $jadwalPegawai[$j->user_id][(string) $j->tanggal_berlaku] = (int) $j->shift_id;
            });

        // pegawai yang punya jadwal tersimpan pada bulan terpilih (untuk tab Data Jadwal)
        $pegawaiBertugas = collect();
        if ($jadwalPegawai) {
            $pegawaiBertugas = User::select('users.id', 'users.nama_lengkap',
                    'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama')
                ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
                ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
                ->whereIn('users.id', array_keys($jadwalPegawai))
                ->orderBy('users.nama_lengkap')
                ->get();
        }

        return view('admin.jadwal.index', [
            'judulHalaman' => 'Jadwal Shift Pegawai',
            'menuAktif' => 'jadwal',
            'subUnits' => $subUnits,
            'subUnitId' => $subUnitId,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'pegawai' => $pegawai,
            'jadwal' => $jadwal,
            'shiftList' => $shiftList,
            'shiftById' => $shiftList->keyBy('id'),
            'hariDalamBulan' => $hariDalamBulan,
            'semuaPegawai' => $semuaPegawai,
            'jadwalPegawai' => $jadwalPegawai,
            'pegawaiBertugas' => $pegawaiBertugas,
        ]);
    }

    public function aksi(Request $request)
    {
        $subUnitId = (int) $request->input('sub_unit_id');
        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');
        $grid = $request->input('grid', []);

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $userIds = User::where('sub_unit_id', $subUnitId)
            ->where('role', '!=', 'admin')
            ->pluck('id')
            ->toArray();

        JadwalShift::whereIn('user_id', $userIds)
            ->where('tanggal_berlaku', '>=', $awal)
            ->where('tanggal_berlaku', '<=', $akhir)
            ->delete();

        $rows = [];
        foreach ($grid as $userId => $dates) {
            foreach ($dates as $tanggal => $shiftId) {
                if (! $shiftId) {
                    continue;
                }
                $rows[] = [
                    'user_id' => (int) $userId,
                    'shift_id' => (int) $shiftId,
                    'tanggal_berlaku' => $tanggal,
                    'diubah_oleh' => session('uid'),
                    'created_at' => now(),
                ];
            }
        }

        if ($rows) {
            JadwalShift::insert($rows);
        }

        $subUnitNama = SubUnit::find($subUnitId)?->nama ?? '#'.$subUnitId;
        catat_aktivitas('Atur Jadwal Shift', "Sub Unit $subUnitNama bulan ".BULAN_ID[$bulan]."/$tahun");

        return redirect()->route('admin.jadwal.index', [
            'sub_unit' => $subUnitId,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'tab' => 'unit',
        ])->with('success', 'Jadwal shift berhasil disimpan.');
    }

    /**
     * Simpan jadwal bulanan untuk banyak pegawai sekaligus.
     * Format grid sama dengan aksi(): grid[user_id][tanggal] = shift_id,
     * tiap pegawai boleh berbeda jadwalnya, disimpan dalam satu request.
     */
    public function aksiPegawai(Request $request)
    {
        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');
        $pilih = array_map('intval', (array) $request->input('users', []));
        $grid  = (array) $request->input('grid', []);

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $userIds = User::whereIn('id', $pilih)
            ->where('role', '!=', 'admin')
            ->pluck('id')
            ->all();

        if (empty($userIds)) {
            return redirect()
                ->route('admin.jadwal.index', ['tab' => 'pegawai'])
                ->with('error', 'Tambahkan minimal satu pegawai.');
        }

        $rows = [];
        foreach ($grid as $userId => $dates) {
            if (! in_array((int) $userId, $userIds, true)) {
                continue;
            }
            foreach ((array) $dates as $tanggal => $shiftId) {
                if (! $shiftId || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
                    continue;
                }
                $rows[] = [
                    'user_id' => (int) $userId,
                    'shift_id' => (int) $shiftId,
                    'tanggal_berlaku' => $tanggal,
                    'diubah_oleh' => session('uid'),
                    'created_at' => now(),
                ];
            }
        }

        JadwalShift::whereIn('user_id', $userIds)
            ->where('tanggal_berlaku', '>=', substr($awal, 0, 10))
            ->where('tanggal_berlaku', '<=', substr($akhir, 0, 10))
            ->delete();

        if ($rows) {
            foreach (array_chunk($rows, 500) as $potongan) {
                JadwalShift::insert($potongan);
            }
        }

        catat_aktivitas('Atur Jadwal Shift Pegawai',
            count($userIds).' pegawai · '.count($rows).' entri · bulan '.BULAN_ID[$bulan]."/$tahun");

        return redirect()
            ->route('admin.jadwal.index', ['tab' => 'pegawai'])
            ->with('success', 'Jadwal '.count($userIds).' pegawai berhasil disimpan ('.count($rows).' entri).');
    }
}
