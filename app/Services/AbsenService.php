<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\LogLokasi;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;

class AbsenService
{
    public function absenDatang(
        array $u,
        float $lat,
        float $lng,
        ?float $akurasi,
        DateTime $now,
        ?string $foto,
        bool $flagAnomali,
        array $alasanAnomali
    ): JsonResponse {
        [$galat, $jarak] = $this->validasiKoordinat((int) $u['id'], 'datang', $lat, $lng, $akurasi, $now);
        if ($galat) {
            return $galat;
        }

        $isDokter = ($u['profesi_nama'] ?? '') === 'Dokter';

        $hariSebelumnya = $this->hariKerjaSebelumnya($now);
        $belumPulang = Absensi::where('user_id', $u['id'])
            ->whereDate('tanggal', $hariSebelumnya->format('Y-m-d'))
            ->whereNull('waktu_pulang')
            ->exists();
        if ($belumPulang) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Anda belum absen pulang',
            ]);
        }

        $buka = Absensi::where('user_id', $u['id'])->whereNull('waktu_pulang')
            ->first();
        if ($buka) {
            return response()->json(['sukses' => false,
                'pesan' => 'Anda masih memiliki absensi tanggal '.tgl_id($buka->tanggal, false)
                         .' yang belum ditutup. Silakan absen pulang terlebih dahulu.']);
        }

        if (! $isDokter && ! $u['shift_id']) {
            return response()->json(['sukses' => false,
                'pesan' => 'Shift kerja Anda belum diatur. Pilih shift pada dasbor atau hubungi admin.']);
        }

        $izin = Izin::where('user_id', $u['id'])->where('status', 'Disetujui')
            ->where('jenis', '!=', 'Dinas Luar')
            ->whereDate('tanggal_mulai', '<=', $now->format('Y-m-d'))
            ->whereDate('tanggal_selesai', '>=', $now->format('Y-m-d'))
            ->first();
        if ($izin) {
            return response()->json(['sukses' => false,
                'pesan' => 'Anda tercatat '.$izin->jenis.' hari ini ('
                         .tgl_id($izin->tanggal_mulai, false).' s.d. '
                         .tgl_id($izin->tanggal_selesai, false)
                         .'). Bila tetap masuk kerja, hubungi admin untuk membatalkan izin tersebut.']);
        }

        if ($isDokter) {
            $tanggalShift = $now->format('Y-m-d');
        } else {
            $jadwal = new DateTime($now->format('Y-m-d').' '.$u['shift_jam_masuk']);
            if ($u['shift_kategori'] === 'Malam' && (int) $now->format('G') < 12) {
                $jadwal->modify('-1 day');
            }
            $tanggalShift = $jadwal->format('Y-m-d');
        }

        $sudah = Absensi::where('user_id', $u['id'])->whereDate('tanggal', $tanggalShift)
            ->count() > 0;
        if (! $isDokter && $sudah) {
            return response()->json(['sukses' => false, 'pesan' => 'Anda sudah melakukan absen datang untuk tanggal ini.']);
        }

        if ($isDokter) {
            $status = null;
            $menit = 0;
            $jenis = 'sukses';
            $pesan = 'Terima kasih sudah hadir hari ini';
            $bintangMasuk = null;
            $sesi = (Absensi::where('user_id', $u['id'])
                ->where('tanggal', $tanggalShift)->max('sesi') ?? 0) + 1;
        } else {
            $toleransi = (int) pengaturan('toleransi_menit', 5);
            $jadwal = new DateTime($now->format('Y-m-d').' '.$u['shift_jam_masuk']);
            if ($u['shift_kategori'] === 'Malam' && (int) $now->format('G') < 12) {
                $jadwal->modify('-1 day');
            }
            $selisih = ($now->getTimestamp() - $jadwal->getTimestamp()) / 60;

            if ($selisih <= $toleransi) {
                $status = 'Tepat Waktu';
                $menit = 0;
                $jenis = 'sukses';
                $pesan = 'Terima kasih sudah datang Tepat Waktu';
            } else {
                $status = 'Terlambat';
                $menit = (int) ceil($selisih);
                $jenis = 'telat';
                $pesan = 'Anda terlambat datang sebanyak '.$menit.' menit';
            }

            $bintang = app(BintangService::class);
            $bintangMasuk = $bintang->bintangMasuk((int) ceil($selisih));
        }

        $absensiId = Absensi::insertGetId([
            'user_id' => $u['id'],
            'sesi' => $isDokter ? $sesi : 1,
            'tanggal' => $tanggalShift,
            'waktu_masuk' => $now->format('Y-m-d H:i:s'),
            'lat_masuk' => round($lat, 7),
            'lng_masuk' => round($lng, 7),
            'foto_masuk' => $foto,
            'status_masuk' => $status,
            'menit_terlambat' => $menit,
            'bintang_masuk' => $bintangMasuk,
            'bintang_harian' => $bintangMasuk,
            'flag_anomali' => $flagAnomali ? 1 : 0,
            'catatan_anomali' => $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
        ]);
        $this->catatLog($u['id'], $absensiId, 'datang', $lat, $lng, $akurasi, $jarak, $now);
        $ket = $isDokter ? null : app(KeterlambatanService::class)->catatDatang($absensiId, (int) ceil($selisih));

        return response()->json([
            'sukses' => true,
            'jenis' => $jenis,
            'pesan' => $pesan,
            'keterangan' => 'Absen datang tercatat pukul '.$now->format('H.i')
                          .' · jarak '.number_format($jarak, 0, ',', '.').' m dari titik RSUD.',
            'status' => $status,
            'menit' => $menit,
            'bintang' => $bintangMasuk,
            'keterlambatan' => $isDokter ? null : [
                'menit_telat' => $ket->menit_telat,
                'bintang_masuk' => $ket->bintang_masuk,
                'total_bintang' => $ket->bintang_masuk,
            ],
            'jam' => $now->format('H.i'),
        ]);
    }

    private function hariKerjaSebelumnya(DateTime $now): DateTime
    {
        $cek = (clone $now)->modify('-1 day');
        $batas = (clone $cek)->modify('-60 days');
        $mingguLibur = pengaturan('minggu_libur', '0') === '1';

        $libur = [];
        foreach (HariLibur::whereBetween('tanggal', [
            $batas->format('Y-m-d'), $now->format('Y-m-d'),
        ])->get() as $h) {
            $libur[$h->tanggal->format('Y-m-d')] = true;
        }

        while ($cek >= $batas) {
            $tgl = $cek->format('Y-m-d');
            if (! isset($libur[$tgl]) && ! ($mingguLibur && (int) $cek->format('w') === 0)) {
                break;
            }
            $cek->modify('-1 day');
        }

        return $cek;
    }

    public function absenPulang(array $u, float $lat, float $lng, ?float $akurasi, DateTime $now, ?string $foto, bool $flagAnomali, array $alasanAnomali): JsonResponse
    {
        [$galat, $jarak] = $this->validasiKoordinat((int) $u['id'], 'pulang', $lat, $lng, $akurasi, $now);
        if ($galat) {
            return $galat;
        }

        $isDokter = ($u['profesi_nama'] ?? '') === 'Dokter';

        $rec = Absensi::where('user_id', $u['id'])->whereNull('waktu_pulang')
            ->orderBy('waktu_masuk', 'DESC')->first();

        $masukOtomatis = false;

        if (! $rec) {
            if ($isDokter) {
                return response()->json(['sukses' => false,
                    'pesan' => 'Belum absen masuk hari ini. Silakan absen datang terlebih dahulu.']);
            }

            $lengkap = Absensi::where('user_id', $u['id'])->where('tanggal', $now->format('Y-m-d'))
                ->whereNotNull('waktu_pulang')->count() > 0;

            if ($lengkap) {
                return response()->json(['sukses' => false,
                    'pesan' => 'Absensi Anda hari ini sudah lengkap. Terima kasih!']);
            }

            if (! $u['shift_id'] || empty($u['shift_jam_masuk'])) {
                return response()->json(['sukses' => false,
                    'pesan' => 'Anda belum melakukan absen datang hari dan shift kerja Anda belum diatur, '
                             .'sehingga absen masuk otomatis tidak dapat dibuat.']);
            }

            $jadwalMasuk = new DateTime($now->format('Y-m-d').' '.$u['shift_jam_masuk']);
            $tanggalShift = $jadwalMasuk->format('Y-m-d');
            if (($u['shift_kategori'] ?? '') === 'Malam' && (int) $now->format('G') < 12) {
                $jadwalMasuk->modify('-1 day');
                $tanggalShift = $jadwalMasuk->format('Y-m-d');
            }

            $sudahLengkapShift = Absensi::where('user_id', $u['id'])
                ->whereDate('tanggal', $tanggalShift)
                ->whereNotNull('waktu_pulang')->count() > 0;
            if ($sudahLengkapShift) {
                return response()->json(['sukses' => false,
                    'pesan' => 'Absensi Anda untuk shift ini sudah lengkap. Terima kasih!']);
            }

            $absensiIdBaru = (int) Absensi::insertGetId([
                'user_id' => $u['id'],
                'sesi' => 1,
                'tanggal' => $tanggalShift,
                'waktu_masuk' => $jadwalMasuk->format('Y-m-d H:i:s'),
                'status_masuk' => 'Tepat Waktu',
                'menit_terlambat' => 0,
                'bintang_masuk' => 1,
                'bintang_harian' => 1,
            ]);
            app(KeterlambatanService::class)->catatDatang($absensiIdBaru, 0)
                ->update(['bintang_masuk' => 1]);

            $rec = Absensi::find($absensiIdBaru);
            $masukOtomatis = true;
        }

        $totalMenit = max(0, (int) floor(($now->getTimestamp() - strtotime($rec->waktu_masuk)) / 60));

        $menitAwal = 0;
        $statusPulang = null;
        $bintangPulang = null;
        $bintangHarian = null;

        if ($isDokter) {
            $totalMenit = null;
        } else {
            $bintang = app(BintangService::class);
            $jamMasuk = $u['shift_jam_masuk'] ?? null;
            $jamPulang = $u['shift_jam_pulang'] ?? null;

            if ($jamPulang && $jamMasuk) {
                $menitAwal = $bintang->selisihMenitPulang(
                    $jamMasuk, $jamPulang, $rec->tanggal->format('Y-m-d'), $now
                );
                $bintangPulang = $bintang->bintangPulang($menitAwal);
                $statusPulang = $menitAwal > 0 ? 'Lebih Awal' : 'Tepat Waktu';

                if ($rec->bintang_masuk !== null) {
                    $bintangHarian = $bintang->bintangHarian((int) $rec->bintang_masuk, $bintangPulang);
                }
            }
        }

        Absensi::where('id', $rec->id)->update([
            'waktu_pulang' => $now->format('Y-m-d H:i:s'),
            'lat_pulang' => round($lat, 7),
            'lng_pulang' => round($lng, 7),
            'foto_pulang' => $foto,
            'total_menit_kerja' => $totalMenit,
            'status_pulang' => $statusPulang,
            'menit_awal_pulang' => $menitAwal,
            'bintang_pulang' => $bintangPulang,
            'bintang_harian' => $bintangHarian,
            'flag_anomali' => ($rec->flag_anomali || $flagAnomali) ? 1 : 0,
            'catatan_anomali' => trim(implode(' | ', array_filter([
                $rec->catatan_anomali,
                $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
            ])), ' |') ?: null,
        ]);
        $this->catatLog($u['id'], (int) $rec->id, 'pulang', $lat, $lng, $akurasi, $jarak, $now);
        if (! $isDokter) {
            app(KeterlambatanService::class)->catatPulang((int) $rec->id, (int) $menitAwal);
        }

        $jenis = 'sukses';
        $pesan = $isDokter
            ? 'Absen pulang tercatat. Terima kasih!'
            : 'Terima kasih atas dedikasi Anda hari ini';
        if (! $isDokter && $menitAwal > 0) {
            $jenis = 'awal';
            $pesan = 'Anda pulang lebih awal sebanyak '.$menitAwal.' menit';
        }
        if ($masukOtomatis) {
            $pesan = 'Anda belum absen datang, sehingga absen masuk dicatat otomatis '
                   .'Tepat Waktu sesuai jadwal (1 bintang). Terima kasih atas dedikasi Anda hari ini';
        }

        return response()->json([
            'sukses' => true,
            'jenis' => $jenis,
            'pesan' => $pesan,
            'keterangan' => 'Absen pulang tercatat pukul '.$now->format('H.i')
                          .($isDokter ? '.' : ' · total jam kerja '.menit_ke_teks($totalMenit).'.'),
            'status' => $statusPulang,
            'menit' => $menitAwal,
            'bintang' => $bintangHarian,
            'keterlambatan' => $isDokter ? null : [
                'menit_pulang_awal' => $ket->menit_awal_pulang,
                'bintang_pulang' => $ket->bintang_pulang,
                'total_bintang' => $ket->total_bintang,
            ],
            'jam' => $now->format('H.i'),
        ]);
    }

    private function validasiKoordinat(
        int $userId,
        string $tipe,
        float $lat,
        float $lng,
        ?float $akurasi,
        DateTime $now
    ): array {
        $rsLat = (float) pengaturan('lokasi_lat', 0);
        $rsLng = (float) pengaturan('lokasi_lng', 0);

        if ($rsLat === 0.0 && $rsLng === 0.0) {
            return [response()->json(['sukses' => false,
                'pesan' => 'Titik lokasi belum diatur oleh admin. Hubungi administrator.']), 0.0];
        }

        $jarak = hitung_jarak($lat, $lng, $rsLat, $rsLng);
        $radius = (float) pengaturan('radius_meter', 100);

        if ($jarak > $radius) {
            $this->catatLog($userId, null, $tipe, $lat, $lng, $akurasi, $jarak, $now, true);

            return [response()->json([
                'sukses' => false,
                'pesan' => 'Absensi ditolak. Anda berada di luar area RSUD Merauke.',
                'keterangan' => 'Jarak Anda '.number_format($jarak, 0, ',', '.')
                              .' m dari titik RSUD (radius maksimal '
                              .number_format($radius, 0, ',', '.').' m).',
            ]), $jarak];
        }

        return [null, $jarak];
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
            'user_id' => $userId,
            'absensi_id' => $absensiId,
            'tipe' => $tipe,
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
            'akurasi' => $akurasi,
            'jarak_meter' => round($jarak, 2),
            'ditolak' => $ditolak ? 1 : 0,
            'waktu' => $now->format('Y-m-d H:i:s'),
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
        $png = str_starts_with($bin, "\x89PNG");
        if (! $jpeg && ! $png) {
            return null;
        }

        $dir = storage_path('app/public/selfie/'.now()->format('Ym'));
        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            return null;
        }
        $nama = $userId.'_'.$tipe.'_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3))
              .($jpeg ? '.jpg' : '.png');

        return file_put_contents($dir.'/'.$nama, $bin) !== false
            ? 'selfie/'.now()->format('Ym').'/'.$nama
            : null;
    }
}
