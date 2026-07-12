<?php

namespace App\Http\Controllers;

use App\Services\AbsenService;
use App\Services\AnomaliService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsenController extends Controller
{
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

        $rsLat  = (float) pengaturan('lokasi_lat', 0);
        $rsLng  = (float) pengaturan('lokasi_lng', 0);
        $radius = (float) pengaturan('radius_meter', 100);

        if ($rsLat === 0.0 && $rsLng === 0.0) {
            return response()->json(['sukses' => false, 'pesan' => 'Titik lokasi RSUD belum diatur oleh admin. Hubungi administrator.']);
        }

        $jarak = hitung_jarak($lat, $lng, $rsLat, $rsLng);
        $now   = new DateTime();

        if ($jarak > $radius) {
            $this->absenService->catatLog($u['id'], null, $tipe, $lat, $lng, $akurasi, $jarak, $now, true);
            return response()->json([
                'sukses'     => false,
                'pesan'      => 'Absensi ditolak. Anda berada di luar area RSUD Merauke.',
                'keterangan' => 'Jarak Anda ' . number_format($jarak, 0, ',', '.')
                              . ' m dari titik RSUD (radius maksimal '
                              . number_format($radius, 0, ',', '.') . ' m).',
            ]);
        }

        [$flagAnomali, $alasanAnomali] = app(AnomaliService::class)->periksa((int) $u['id'], $lat, $lng, $akurasi);

        return $tipe === 'datang'
            ? $this->absenService->absenDatang($u, $lat, $lng, $akurasi, $jarak, $now, $fileFoto, $flagAnomali, $alasanAnomali)
            : $this->absenService->absenPulang($u, $lat, $lng, $akurasi, $jarak, $now, $fileFoto, $flagAnomali, $alasanAnomali);
    }


    private function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }
        $u = DB::table('users as u')
            ->select('u.*', 'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang',
                     'j.nama AS jabatan_nama',
                     DB::raw('COALESCE(j.unit_label, ji.unit_label) AS jabatan_unit'),
                     'sp.nama AS seksi_pembina_nama', 'spi.unit_label AS bidang_pembina_label')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'u.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id')
            ->leftJoin('jabatan ji', 'ji.id', '=', 'j.induk_id')
            ->leftJoin('jabatan sp', 'sp.id', '=', 'u.seksi_pembina_id')
            ->leftJoin('jabatan spi', 'spi.id', '=', 'sp.induk_id')
            ->where('u.id', $uid)
            ->first();

        if (! $u || $u->status !== 'aktif') {
            session()->flush();
            return $cache = null;
        }
        return $cache = (array) $u;
    }
}
