<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
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
}
