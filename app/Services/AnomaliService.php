<?php

namespace App\Services;

use App\Models\LogLokasi;

class AnomaliService
{
    public function periksa(int $userId, float $lat, float $lng, ?float $akurasi): array
    {
        $alasan = [];

        if ($akurasi !== null) {
            if ($akurasi > 0 && $akurasi < 3) {
                $alasan[] = 'Akurasi GPS dilaporkan ±' . round($akurasi, 1)
                          . ' m — terlalu presisi untuk ponsel umum (indikasi lokasi tiruan).';
            } elseif ($akurasi > 500) {
                $alasan[] = 'Akurasi GPS sangat rendah (±' . round($akurasi)
                          . ' m) — posisi sebenarnya bisa jauh dari titik yang tercatat.';
            }
        }

        $latFormatted = number_format($lat, 7, '.', '');
        $lngFormatted = number_format($lng, 7, '.', '');
        $identik = LogLokasi::where('user_id', $userId)
            ->where('latitude', $latFormatted)
            ->where('longitude', $lngFormatted)
            ->count();

        if ($identik >= 3) {
            $alasan[] = 'Koordinat identik persis dengan ' . $identik
                      . ' aksi absensi sebelumnya (GPS asli selalu sedikit bergeser).';
        }

        $terakhir = LogLokasi::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->first();

        if ($terakhir) {
            $jarak = hitung_jarak($lat, $lng, (float) $terakhir->latitude, (float) $terakhir->longitude);
            $detik = max(1, now()->diffInSeconds(\Carbon\Carbon::parse($terakhir->waktu)));
            $kmJam = $jarak / $detik * 3.6;
            if ($jarak > 500 && $kmJam > 150) {
                $alasan[] = 'Berpindah ' . number_format($jarak / 1000, 1, ',', '.')
                          . ' km dalam ' . $this->teksDurasi($detik)
                          . ' (≈' . number_format($kmJam, 0, ',', '.')
                          . ' km/jam) sejak aksi lokasi terakhir.';
            }
        }

        return [$alasan !== [], $alasan];
    }

    private function teksDurasi(int $detik): string
    {
        if ($detik < 90) return $detik . ' detik';
        if ($detik < 5400) return round($detik / 60) . ' menit';
        return round($detik / 3600, 1) . ' jam';
    }
}
