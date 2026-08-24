<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Services\AbsenService;
use App\Services\AnomaliService;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenController extends Controller
{
    public function __construct(
        private AbsenService $absenService
    ) {}

    public function absen(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $tipe = $req->input('tipe');
        $lat = (float) $req->input('lat');
        $lng = (float) $req->input('lng');
        $akurasi = $req->has('akurasi') ? round((float) $req->input(
            'akurasi'
        ), 2) : null;
        $foto = $req->input('foto');
        if (! in_array($tipe, ['datang', 'pulang']) || ! $lat || ! $lng) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'data tidak lengkap!!!',
            ], 422);
        }

        $shift = $user->shift;

        $u = [
            'id' => $user->id,
            'shift_id' => $shift?->id,
            'shift_kategori' => $shift?->kategori ?? null,
            'shift_jam_masuk' => $shift?->jam_masuk?->format('H:i'),
            'shift_jam_pulang' => $shift?->jam_pulang?->format('H:i'),
            'role' => $user->role,
        ];

        $wajibSelfie = pengaturan('wajib_selfie', '1') === '1';
        $fileFoto = null;
        if ($foto) {
            $fileFoto = $this->absenService->simpanSelfie($user->id, $tipe, $foto);
            if ($fileFoto === null && $wajibSelfie) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Foto selfie tidak valid!!!',
                ]);
            }
        } elseif ($wajibSelfie) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Foto selfie wajib disertakan',
            ]);
        }

        $now = new DateTime;

        [$flagAnomali, $alasanAnomali] = app(AnomaliService::class)->periksa($user->id, $lat, $lng, $akurasi);

        return $tipe === 'datang' ?
            $this->absenService->absenDatang($u, $lat, $lng, $akurasi, $now, $fileFoto, $flagAnomali, $alasanAnomali)
            : $this->absenService->absenPulang($u, $lat, $lng, $akurasi, $now, $fileFoto, $flagAnomali, $alasanAnomali);
    }

    public function status(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $absen = Absensi::where('user_id', $user->id)
            ->where('tanggal', now()->toDateString())
            ->first();

        return response()->json([
            'sukses' => true,
            'absen_masuk' => $absen ? [
                'waktu' => substr($absen->waktu_masuk, 11, 5),
                'status' => $absen->status_masuk,
                'menit_terlambat' => (int) $absen->menit_terlambat,
                'bintang' => $absen->bintang_masuk,
            ] : null,
            'absen_pulang' => $absen?->waktu_pulang ? [
                'waktu' => substr($absen->waktu_pulang, 11, 5),
                'status' => $absen->status_pulang,
                'menit_awal' => (int) $absen->menit_awal_pulang,
                'bintang' => $absen->bintang_pulang,
            ] : null,
            'bintang_harian' => $absen?->bintang_harian,
        ]);
    }

    public function riwayatAbsensi(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $records = Absensi::where('user_id', $user->id)
            ->orderByDesc('tanggal')->limit(7)->get()
            ->map(fn ($a) => [
                'tanggal' => $a->tanggal->format('Y-m-d'),
                'hari' => $a->tanggal->locale('id')->translatedFormat('l'),
                'tanggal_label' => $a->tanggal->format('j M Y'),
                'jam_masuk' => substr($a->waktu_masuk, 11, 5),  // "08:05"
                'jam_pulang' => $a->waktu_pulang ? substr($a->waktu_pulang, 11, 5) : null,
                'status' => $a->status_masuk,                 // "Tepat Waktu" / "Terlambat"
                'status_pulang' => $a->status_pulang,
                'menit_terlambat' => (int) $a->menit_terlambat,
                'menit_awal_pulang' => (int) $a->menit_awal_pulang,
                'bintang_masuk' => $a->bintang_masuk,
                'bintang_pulang' => $a->bintang_pulang,
                'bintang_harian' => $a->bintang_harian,
            ]);

        return response()->json([
            'sukses' => true,
            'riwayat' => $records,
        ]);
    }
}
