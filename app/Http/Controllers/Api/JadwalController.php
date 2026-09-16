<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function jadwal(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $shift = $user->shift;

        return response()->json([
            'sukses' => true,
            'shift' => $shift ? [
                'id' => $shift->id,
                'kategori' => $shift->kategori,
                'jam_masuk' => Carbon::parse($shift->jam_masuk)->format('H:i'),
                'jam_pulang' => Carbon::parse($shift->jam_pulang)->format('H:i'),
            ] : null,
            'izinkan_pilih' => pengaturan('izinkan_pilih_shift', '1') === '1',
        ]);
    }

    private function barisJadwal(string $tgl, ?JadwalShift $jadwal): array
    {
        return [
            'tanggal' => $tgl,
            'hari' => Carbon::parse($tgl)->locale('id')->translatedFormat('l'),
            'shift' => $jadwal?->shift ? [
                'id' => $jadwal->shift->id,
                'kategori' => $jadwal->shift->kategori,
                'jam_masuk' => Carbon::parse($jadwal->shift->jam_masuk)->format('H:i'),
                'jam_pulang' => Carbon::parse($jadwal->shift->jam_pulang)->format('H:i'),
            ] : null,
        ];
    }

    private function jadwalRentang(User $user, string $mulai, string $sampai): array
    {
        $peta = JadwalShift::with('shift:id,kategori,jam_masuk,jam_pulang')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal_berlaku', [$mulai, $sampai])
            ->orderBy('tanggal_berlaku')
            ->get()
            ->keyBy('tanggal_berlaku');

        $hasil = [];
        for ($t = strtotime($mulai); $t <= strtotime($sampai); $t += 86400) {
            $tgl = date('Y-m-d', $t);
            $hasil[] = $this->barisJadwal($tgl, $peta[$tgl] ?? null);
        }

        return $hasil;
    }

    public function jadwalHariIni(Request $req): JsonResponse
    {
        $user = $req->get('user');

        return response()->json([
            'sukses' => true,
            'data' => $this->jadwalRentang($user, now()->toDateString(), now()->toDateString()),
        ]);
    }

    public function jadwalMingguan(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $mulai = now()->startOfWeek(Carbon::MONDAY);

        if ($req->query('mulai')) {
            try {
                $mulai = Carbon::createFromFormat('Y-m-d', $req->query('mulai'));
            } catch (\Throwable) {
                return response()->json(['sukses' => false, 'pesan' => 'Parameter mulai harus format Y-m-d.'], 422);
            }
            if (! $mulai) {
                return response()->json(['sukses' => false, 'pesan' => 'Parameter mulai harus format Y-m-d.'], 422);
            }
        }

        $sampai = (clone $mulai)->addDays(6);

        return response()->json([
            'sukses' => true,
            'periode' => ['mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString()],
            'data' => $this->jadwalRentang($user, $mulai->toDateString(), $sampai->toDateString()),
        ]);
    }

    public function jadwalBulanan(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth);

        return response()->json([
            'sukses' => true,
            'periode' => ['bulan' => $bulan, 'tahun' => $tahun, 'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun],
            'data' => $this->jadwalRentang($user, $awal, $akhir),
        ]);
    }

    /**
     * Rekapitulasi pengaturan jadwal shift pegawai — serupa halaman admin/jadwal.
     * Menyediakan daftar sub unit, shift aktif, pegawai per sub unit beserta grid
     * jadwal bulanan, serta seluruh pegawai aktif dan karyawan yang punya jadwal
     * tersimpan pada bulan terpilih.
     */
    public function kelola(Request $req): JsonResponse
    {
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);
        $subUnitId = (int) $req->query('sub_unit');

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $subUnits = SubUnit::select('sub_unit.id', 'sub_unit.nama', 'uk.nama as unit_nama')
            ->join('unit_kerja as uk', 'uk.id', '=', 'sub_unit.unit_kerja_id')
            ->orderBy('uk.nama')->orderBy('sub_unit.nama')
            ->get()
            ->map(fn ($su) => [
                'id' => (int) $su->id,
                'nama' => $su->nama,
                'unit_nama' => $su->unit_nama,
            ])
            ->values();

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $pegawai = collect();
        $jadwal = [];

        if ($subUnitId) {
            $pegawai = User::where('sub_unit_id', $subUnitId)
                ->where('role', '!=', 'admin')
                ->where('status', 'aktif')
                ->orderBy('nama_lengkap')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'nama_lengkap' => $p->nama_lengkap,
                ])
                ->values();

            $userIds = User::where('sub_unit_id', $subUnitId)
                ->where('role', '!=', 'admin')
                ->pluck('id')
                ->toArray();

            JadwalShift::whereIn('user_id', $userIds)
                ->where('tanggal_berlaku', '>=', $awal)
                ->where('tanggal_berlaku', '<=', $akhir)
                ->get()
                ->each(function ($j) use (&$jadwal) {
                    $jadwal[(string) $j->user_id][(string) $j->tanggal_berlaku] = (int) $j->shift_id;
                });
        }

        $shiftList = Shift::where('aktif', 1)->orderBy('jam_masuk')->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'kategori' => $s->kategori,
                'jam_masuk' => Carbon::parse($s->jam_masuk)->format('H:i'),
                'jam_pulang' => Carbon::parse($s->jam_pulang)->format('H:i'),
            ])
            ->values();

        $semuaPegawai = User::select('users.id', 'users.nama_lengkap',
                'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'unit_nama' => $p->unit_nama,
                'sub_unit_nama' => $p->sub_unit_nama,
            ])
            ->values();

        $jadwalPegawai = [];
        JadwalShift::join('users as u', 'u.id', '=', 'jadwal_shift.user_id')
            ->where('u.role', '!=', 'admin')
            ->where('u.status', 'aktif')
            ->where('jadwal_shift.tanggal_berlaku', '>=', $awal)
            ->where('jadwal_shift.tanggal_berlaku', '<=', $akhir)
            ->get(['jadwal_shift.user_id', 'jadwal_shift.tanggal_berlaku', 'jadwal_shift.shift_id'])
            ->each(function ($j) use (&$jadwalPegawai) {
                $jadwalPegawai[(string) $j->user_id][(string) $j->tanggal_berlaku] = (int) $j->shift_id;
            });

        $pegawaiBertugas = collect();
        if ($jadwalPegawai) {
            $pegawaiBertugas = User::select('users.id', 'users.nama_lengkap',
                    'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama')
                ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
                ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
                ->whereIn('users.id', array_keys($jadwalPegawai))
                ->orderBy('users.nama_lengkap')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'nama_lengkap' => $p->nama_lengkap,
                    'unit_nama' => $p->unit_nama,
                    'sub_unit_nama' => $p->sub_unit_nama,
                ])
                ->values();
        }

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
                'hari_dalam_bulan' => (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun),
            ],
            'sub_unit_dipilih' => $subUnitId ? $subUnits->firstWhere('id', $subUnitId) : null,
            'sub_units' => $subUnits,
            'shift' => $shiftList,
            'pegawai' => $pegawai,
            'jadwal' => $jadwal,
            'semua_pegawai' => $semuaPegawai,
            'jadwal_pegawai' => $jadwalPegawai,
            'pegawai_bertugas' => $pegawaiBertugas,
        ]);
    }

    /**
     * Simpan jadwal shift satu sub unit untuk satu bulan — serupa admin/jadwal aksi().
     * Body: sub_unit_id, bulan, tahun, grid[user_id][YYYY-MM-DD] = shift_id.
     * Jadwal lama milih pegawai sub unit pada bulan tsb diganti total.
     */
    public function simpanUnit(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $subUnitId = (int) $req->input('sub_unit_id');
        $bulan = (int) $req->input('bulan', now()->month);
        $tahun = (int) $req->input('tahun', now()->year);
        $grid = $req->input('grid', []);

        if (! $subUnitId) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter sub_unit_id wajib diisi.'], 422);
        }
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $userIds = User::where('sub_unit_id', $subUnitId)
            ->where('role', '!=', 'admin')
            ->pluck('id')
            ->toArray();

        $terhapus = JadwalShift::whereIn('user_id', $userIds)
            ->where('tanggal_berlaku', '>=', $awal)
            ->where('tanggal_berlaku', '<=', $akhir)
            ->delete();

        $rows = [];
        foreach ((array) $grid as $userId => $dates) {
            foreach ((array) $dates as $tanggal => $shiftId) {
                if (! $shiftId || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
                    continue;
                }
                $rows[] = [
                    'user_id' => (int) $userId,
                    'shift_id' => (int) $shiftId,
                    'tanggal_berlaku' => $tanggal,
                    'diubah_oleh' => $user->id,
                    'created_at' => now(),
                ];
            }
        }

        if ($rows) {
            JadwalShift::insert($rows);
        }

        $subUnitNama = SubUnit::find($subUnitId)?->nama ?? '#'.$subUnitId;
        catat_aktivitas('Atur Jadwal Shift (Mobile)', $user->nama_lengkap
            . " · Sub Unit $subUnitNama bulan ".BULAN_ID[$bulan]."/$tahun · ".count($rows).' entri');

        return response()->json([
            'sukses' => true,
            'pesan' => 'Jadwal shift '.$subUnitNama.' berhasil disimpan ('.count($rows).' entri).',
            'sub_unit_id' => $subUnitId,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'terhapus' => $terhapus,
            'disimpan' => count($rows),
        ]);
    }

    /**
     * Simpan jadwal bulanan untuk banyak pegawai terpilih sekaligus —
     * serupa admin/jadwal aksiPegawai(). Body: bulan, tahun, users[], grid.
     */
    public function simpanPegawai(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $bulan = (int) $req->input('bulan', now()->month);
        $tahun = (int) $req->input('tahun', now()->year);
        $pilih = array_map('intval', (array) $req->input('users', []));
        $grid = $req->input('grid', []);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $userIds = User::whereIn('id', $pilih)
            ->where('role', '!=', 'admin')
            ->pluck('id')
            ->all();

        if (empty($userIds)) {
            return response()->json(['sukses' => false, 'pesan' => 'Tambahkan minimal satu pegawai.'], 422);
        }

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan,
            cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun));

        $rows = [];
        foreach ((array) $grid as $userId => $dates) {
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
                    'diubah_oleh' => $user->id,
                    'created_at' => now(),
                ];
            }
        }

        JadwalShift::whereIn('user_id', $userIds)
            ->where('tanggal_berlaku', '>=', $awal)
            ->where('tanggal_berlaku', '<=', $akhir)
            ->delete();

        if ($rows) {
            foreach (array_chunk($rows, 500) as $potongan) {
                JadwalShift::insert($potongan);
            }
        }

        catat_aktivitas('Atur Jadwal Shift Pegawai (Mobile)', $user->nama_lengkap
            . ' · '.count($userIds).' pegawai · '.count($rows).' entri · bulan '.BULAN_ID[$bulan]."/$tahun");

        return response()->json([
            'sukses' => true,
            'pesan' => 'Jadwal '.count($userIds).' pegawai berhasil disimpan ('.count($rows).' entri).',
            'bulan' => $bulan,
            'tahun' => $tahun,
            'pegawai' => count($userIds),
            'disimpan' => count($rows),
        ]);
    }
}
