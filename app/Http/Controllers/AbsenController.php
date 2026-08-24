<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Services\AbsenService;
use DateTime;
use Illuminate\Http\Request;

class AbsenController extends Controller
{
    use HasPenggunaAktif;

    public function __construct(
        private AbsenService $absenService
    )
    {}

    public function proses(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response()->json(['sukses' => false, 'pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
        }

        $in      = $request->all();
        $tipe    = $in['tipe'] ?? '';
        $lat     = $in['lat'] ?? null;
        $lng     = $in['lng'] ?? null;
        $akurasi = isset($in['akurasi']) ? round((float) $in['akurasi'], 2) : null;
        $foto    = $in['foto'] ?? null;

        if (! in_array($tipe, ['datang', 'pulang'], true) || ! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['sukses' => false, 'pesan' => 'Data absensi tidak lengkap.'], 422);
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        $wajibSelfie = pengaturan('wajib_selfie', '1') === '1';
        $fileFoto    = null;
        if ($foto) {
            $fileFoto = $this->absenService->simpanSelfie((int) $u['id'], $tipe, (string) $foto);
            if ($fileFoto === null && $wajibSelfie) {
                return response()->json(['sukses' => false, 'pesan' => 'Foto selfie tidak valid. Ulangi pengambilan foto.']);
            }
        } elseif ($wajibSelfie) {
            return response()->json(['sukses' => false, 'pesan' => 'Foto selfie wajib disertakan saat absensi. Izinkan akses kamera lalu coba lagi.']);
        }

        $now = new DateTime();

        [$flagAnomali, $alasanAnomali] = app(\App\Services\AnomaliService::class)->periksa((int) $u['id'], $lat, $lng, $akurasi);

        return $tipe === 'datang'
            ? $this->absenService->absenDatang($u, $lat, $lng, $akurasi, $now, $fileFoto, $flagAnomali, $alasanAnomali)
            : $this->absenService->absenPulang($u, $lat, $lng, $akurasi, $now, $fileFoto, $flagAnomali, $alasanAnomali);
    }
}
