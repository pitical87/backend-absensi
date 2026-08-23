<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\LogLokasi;
use App\Services\AnomaliService;
use DateTime;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class AbsenService
{
    public function absenDatang(
        array $u,
        float $lat,
        float $lng,
        ?float $akurasi,
        float $jarak,
        DateTime $now,
        ?string $foto,
        bool $flagAnomali,
        array $alasanAnomali
    ):JsonResponse
    {
         $buka = Absensi::where('user_id', $u['id'])->whereNull('waktu_pulang')
            ->first();
        if ($buka) {
            return response()->json(['sukses' => false,
                'pesan' => 'Anda masih memiliki absensi tanggal ' . tgl_id($buka->tanggal, false)
                         . ' yang belum ditutup. Silakan absen pulang terlebih dahulu.']);
        }

        if (! $u['shift_id']) {
            return response()->json(['sukses' => false,
                'pesan' => 'Shift kerja Anda belum diatur. Pilih shift pada dasbor atau hubungi admin.']);
        }

        $izin = Izin::where('user_id', $u['id'])->where('status', 'Disetujui')
            ->where('jenis', '!=', 'Dinas Luar')
            ->where('tanggal_mulai', '<=', $now->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $now->format('Y-m-d'))
            ->first();
        if ($izin) {
            return response()->json(['sukses' => false,
                'pesan' => 'Anda tercatat ' . $izin->jenis . ' hari ini ('
                         . tgl_id($izin->tanggal_mulai, false) . ' s.d. '
                         . tgl_id($izin->tanggal_selesai, false)
                         . '). Bila tetap masuk kerja, hubungi admin untuk membatalkan izin tersebut.']);
        }

        $jadwal = new DateTime($now->format('Y-m-d') . ' ' . $u['shift_jam_masuk']);
        if ($u['shift_kategori'] === 'Malam' && (int) $now->format('G') < 12) {
            $jadwal->modify('-1 day');
        }
        $tanggalShift = $jadwal->format('Y-m-d');

        $sudah = Absensi::where('user_id', $u['id'])->where('tanggal', $tanggalShift)
            ->count() > 0;
        if ($sudah) {
            return response()->json(['sukses' => false, 'pesan' => 'Anda sudah melakukan absen datang untuk tanggal ini.']);
        }

        $toleransi = (int) pengaturan('toleransi_menit', 5);
        $selisih   = ($now->getTimestamp() - $jadwal->getTimestamp()) / 60;

        if ($selisih <= $toleransi) {
            $status = 'Tepat Waktu';
            $menit  = 0;
            $jenis  = 'sukses';
            $pesan  = 'Terima kasih sudah datang Tepat Waktu';
        } else {
            $status = 'Terlambat';
            $menit  = (int) ceil($selisih);
            $jenis  = 'telat';
            $pesan  = 'Anda terlambat datang sebanyak ' . $menit . ' menit';
        }

        $bintang     = app(BintangService::class);
        $bintangMasuk = $bintang->bintangMasuk((int) ceil($selisih));

        $absensiId = Absensi::insertGetId([
            'user_id'         => $u['id'],
            'tanggal'         => $tanggalShift,
            'shift_id'        => $u['shift_id'],
            'waktu_masuk'     => $now->format('Y-m-d H:i:s'),
            'lat_masuk'       => round($lat, 7),
            'lng_masuk'       => round($lng, 7),
            'foto_masuk'      => $foto,
            'status_masuk'    => $status,
            'menit_terlambat' => $menit,
            'bintang_masuk'   => $bintangMasuk,
            'bintang_harian'  => $bintangMasuk,
            'flag_anomali'    => $flagAnomali ? 1 : 0,
            'catatan_anomali' => $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
        ]);
        $this->catatLog($u['id'], $absensiId, 'datang', $lat, $lng, $akurasi, $jarak, $now);
        $ket = app(KeterlambatanService::class)->catatDatang($absensiId, (int) ceil($selisih));

        return response()->json([
            'sukses'     => true,
            'jenis'      => $jenis,
            'pesan'      => $pesan,
            'keterangan' => 'Absen datang tercatat pukul ' . $now->format('H.i')
                          . ' · jarak ' . number_format($jarak, 0, ',', '.') . ' m dari titik RSUD.',
            'status'     => $status,
            'menit'      => $menit,
            'bintang'    => $bintangMasuk,
            'keterlambatan' => [
                'menit_telat'   => $ket->menit_telat,
                'bintang_masuk' => $ket->bintang_masuk,
            ],
            'jam'        => $now->format('H.i'),
        ]);
    }

    public function absenPulang(array $u, float $lat, float $lng, ?float $akurasi, float $jarak, DateTime $now, ?string $foto, bool $flagAnomali, array $alasanAnomali) : JsonResponse
    {
        $rec =Absensi::where('user_id', $u['id'])->whereNull('waktu_pulang')
            ->orderBy('waktu_masuk', 'DESC')->first();

        if (! $rec) {
            $lengkap = Absensi::where('user_id', $u['id'])->where('tanggal', $now->format('Y-m-d'))
                ->whereNotNull('waktu_pulang')->count() > 0;
            return response()->json(['sukses' => false, 'pesan' => $lengkap
                ? 'Absensi Anda hari ini sudah lengkap. Terima kasih!'
                : 'Anda belum melakukan absen datang hari ini.']);
        }

        $totalMenit = max(0, (int) floor(($now->getTimestamp() - strtotime($rec->waktu_masuk)) / 60));

        $bintang      = app(BintangService::class);
        $jamMasuk     = $u['shift_jam_masuk'] ?? null;
        $jamPulang    = $u['shift_jam_pulang'] ?? null;
        $menitAwal    = 0;
        $statusPulang = null;
        $bintangPulang = null;
        $bintangHarian = null;

        if ($jamPulang && $jamMasuk) {
            $menitAwal    = $bintang->selisihMenitPulang(
                $jamMasuk, $jamPulang, $rec->tanggal->format('Y-m-d'), $now
            );
            $bintangPulang = $bintang->bintangPulang($menitAwal);
            $statusPulang  = $menitAwal > 0 ? 'Lebih Awal' : 'Tepat Waktu';

            if ($rec->bintang_masuk !== null) {
                $bintangHarian = $bintang->bintangHarian((int) $rec->bintang_masuk, $bintangPulang);
            }
        }

        Absensi::where('id', $rec->id)->update([
            'waktu_pulang'      => $now->format('Y-m-d H:i:s'),
            'lat_pulang'        => round($lat, 7),
            'lng_pulang'        => round($lng, 7),
            'foto_pulang'       => $foto,
            'total_menit_kerja' => $totalMenit,
            'status_pulang'     => $statusPulang,
            'menit_awal_pulang' => $menitAwal,
            'bintang_pulang'    => $bintangPulang,
            'bintang_harian'    => $bintangHarian,
            'flag_anomali'      => ($rec->flag_anomali || $flagAnomali) ? 1 : 0,
            'catatan_anomali'   => trim(implode(' | ', array_filter([
                                        $rec->catatan_anomali,
                                        $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
                                    ])), ' |') ?: null,
        ]);
        $this->catatLog($u['id'], (int) $rec->id, 'pulang', $lat, $lng, $akurasi, $jarak, $now);
        $ket = app(KeterlambatanService::class)->catatPulang((int) $rec->id, (int) $menitAwal);

        $jenis = 'sukses';
        $pesan = 'Terima kasih atas dedikasi Anda hari ini';
        if ($menitAwal > 0) {
            $jenis = 'awal';
            $pesan = 'Anda pulang lebih awal sebanyak ' . $menitAwal . ' menit';
        }

        return response()->json([
            'sukses'     => true,
            'jenis'      => $jenis,
            'pesan'      => $pesan,
            'keterangan' => 'Absen pulang tercatat pukul ' . $now->format('H.i')
                          . ' · total jam kerja ' . menit_ke_teks($totalMenit) . '.',
            'status'     => $statusPulang,
            'menit'      => $menitAwal,
            'bintang'    => $bintangHarian,
            'keterlambatan' => [
                'menit_pulang_awal' => $ket->menit_awal_pulang,
                'bintang_pulang'    => $ket->bintang_pulang,
                'total_bintang'     => $ket->total_bintang,
            ],
            'jam'        => $now->format('H.i'),
        ]);
    }

    public function catatLog(
        int $userId,
        ?int $absensiId,
        string $tipe,
        float $lat,
        float $lng,
        ?float $akurasi,
        float $jarak,
        DateTime $now,
        bool $ditolak = false
    ): void {
        LogLokasi::insert([
            'user_id'     => $userId,
            'absensi_id'  => $absensiId,
            'tipe'        => $tipe,
            'latitude'    => round($lat, 7),
            'longitude'   => round($lng, 7),
            'akurasi'     => $akurasi,
            'jarak_meter' => round($jarak, 2),
            'ditolak'     => $ditolak ? 1 : 0,
            'waktu'       => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function simpanSelfie(
        int $userId,
        string $tipe,
        string $dataUrl
    ): ?string {
       if (! preg_match('#^data:image/(jpeg|png);base64,#', $dataUrl, $m)) {
            return null;
        }
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) < 1024 || strlen($bin) > 3 * 1024 * 1024) {
            return null;
        }
        $jpeg = str_starts_with($bin, "\xFF\xD8\xFF");
        $png  = str_starts_with($bin, "\x89PNG");
        if (! $jpeg && ! $png) {
            return null;
        }

        $dir = storage_path('app/public/selfie/' . now()->format('Ym'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            return null;
        }
        $nama = $userId . '_' . $tipe . '_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3))
              . ($jpeg ? '.jpg' : '.png');
        return file_put_contents($dir . '/' . $nama, $bin) !== false
            ? 'selfie/' . now()->format('Ym') . '/' . $nama
            : null;
    }
}