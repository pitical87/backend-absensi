<?php

namespace App\Services;

use App\Models\AbsenLembur;
use App\Models\PengajuanLembur;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class AbsenLemburService
{
    /**
     * Validasi koordinat terhadap radius RSUD. Kembalikan [galat, jarak].
     */
    public function validasiKoordinat(int $userId, string $tipe, float $lat, float $lng, ?float $akurasi = null): array
    {
        $rsLat = (float) pengaturan('lokasi_lat', 0);
        $rsLng = (float) pengaturan('lokasi_lng', 0);

        if ($rsLat === 0.0 && $rsLng === 0.0) {
            return [response()->json([
                'sukses' => false,
                'pesan'  => 'Titik lokasi belum diatur oleh admin. Hubungi administrator.',
            ]), 0.0];
        }

        $jarak  = hitung_jarak($lat, $lng, $rsLat, $rsLng);
        $radius = (float) pengaturan('radius_meter', 100);

        if ($jarak > $radius) {
            app(AbsenService::class)->catatLog($userId, null, $tipe, $lat, $lng, $akurasi, $jarak, new \DateTime(), true);

            return [response()->json([
                'sukses'     => false,
                'pesan'      => 'Absen lembur ditolak. Anda berada di luar area RSUD Merauke.',
                'keterangan' => 'Jarak Anda ' . number_format($jarak, 0, ',', '.')
                    . ' m dari titik RSUD (radius maksimal '
                    . number_format($radius, 0, ',', '.').' m).',
            ]), $jarak];
        }

        return [null, $jarak];
    }

    /**
     * Catat absen masuk lembur.
     *
     * @return array{ok: bool, pesan: string, data?: array}
     */
    public function absenMasuk(User $user, float $lat, float $lng, ?float $akurasi, string $tanggal, ?string $foto = null): array
    {
        [$galat, $jarak] = $this->validasiKoordinat($user->id, 'datang', $lat, $lng, $akurasi);
        if ($galat) {
            return ['ok' => false, 'pesan' => $galat->getData()->pesan];
        }

        // Cari pengajuan lembur disetujui milik user pada tanggal tsb yang belum punya absen masuk.
        $pj = PengajuanLembur::where('user_id', $user->id)
            ->where('tanggal', $tanggal)
            ->where('status', 'Disetujui')
            ->whereDoesntHave('absenLembur', fn ($q) => $q->whereNotNull('waktu_masuk'))
            ->orderBy('jam_mulai')
            ->first();

        if (! $pj) {
            return ['ok' => false, 'pesan' => 'Tidak ada pengajuan lembur disetujui yang menunggu absen masuk untuk tanggal tersebut.'];
        }

        $toleransi = app(PengajuanLemburService::class)->toleransiMenit();
        $mulai     = Carbon::parse($pj->tanggal->format('Y-m-d') . ' ' . Carbon::parse($pj->jam_mulai)->format('H:i:s'));
        $now       = Carbon::parse(now()->format('Y-m-d H:i:s'));
        $selisih   = ($now->getTimestamp() - $mulai->getTimestamp()) / 60;

        if ($selisih > $toleransi) {
            $status    = 'Terlambat';
            $terlambat = (int) ceil($selisih);
        } else {
            $status    = 'Tepat Waktu';
            $terlambat = 0;
        }

        $bintangMasuk = app(BintangService::class)->bintangMasuk((int) ceil($selisih));

        $absen = DB::transaction(function () use ($user, $pj, $now, $lat, $lng, $status, $terlambat, $bintangMasuk, $foto) {
            return AbsenLembur::create([
                'pengajuan_lembur_id' => $pj->id,
                'user_id'             => $user->id,
                'tanggal'             => $pj->tanggal->format('Y-m-d'),
                'waktu_masuk'         => $now->format('Y-m-d H:i:s'),
                'lat_masuk'           => round($lat, 7),
                'lng_masuk'           => round($lng, 7),
                'foto_masuk'          => $foto,
                'status_masuk'        => $status,
                'menit_terlambat'     => $terlambat,
                'bintang_masuk'       => $bintangMasuk,
                'bintang_harian'      => $bintangMasuk,
                'created_at'          => $now,
            ]);
        });

        app(AbsenService::class)->catatLog($user->id, null, 'datang', $lat, $lng, $akurasi, $jarak, \DateTime::createFromInterface($now));

        return [
            'ok'    => true,
            'pesan' => $status === 'Terlambat'
                ? 'Absen masuk lembur tercatat, Anda terlambat ' . $terlambat . ' menit.'
                : 'Absen masuk lembur tercatat tepat waktu.',
            'data'  => [
                'absen_lembur_id' => $absen->id,
                'waktu'           => $now->format('H:i'),
                'status'          => $status,
                'menit_terlambat' => $terlambat,
                'bintang'         => $bintangMasuk,
            ],
        ];
    }

    /**
     * Catat absen pulang lembur.
     *
     * @return array{ok: bool, pesan: string, data?: array}
     */
    public function absenPulang(User $user, float $lat, float $lng, ?float $akurasi, string $tanggal, ?string $foto = null): array
    {
        [$galat, $jarak] = $this->validasiKoordinat($user->id, 'pulang', $lat, $lng, $akurasi);
        if ($galat) {
            return ['ok' => false, 'pesan' => $galat->getData()->pesan];
        }

        $absen = AbsenLembur::where('user_id', $user->id)
            ->where('tanggal', $tanggal)
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->first();

        if (! $absen) {
            return ['ok' => false, 'pesan' => 'Tidak ada absen masuk lembur yang menunggu absen pulang untuk tanggal tersebut.'];
        }

        $now = Carbon::parse(now()->format('Y-m-d H:i:s'));

        if ($now->lte($absen->waktu_masuk)) {
            return ['ok' => false, 'pesan' => 'Waktu pulang harus setelah waktu masuk lembur.'];
        }

        $durasiMenit = (int) floor($absen->waktu_masuk->diffInSeconds($now) / 60);

        $bintangMasuk = $absen->bintang_masuk;
        $bintangPulang = 0;
        $bintangHarian = null;

        // Bintang pulang: dinilai terhadap jam selesai yang disetujui.
        $pj = $absen->pengajuanLembur;
        if ($pj) {
            $selesai      = Carbon::parse($pj->tanggal->format('Y-m-d') . ' ' . Carbon::parse($pj->jam_selesai)->format('H:i:s'));
            $menitPulang  = ($selesai->getTimestamp() - $now->getTimestamp()) / 60; // positif = lebih awal, negatif = melewati jadwal
            $bintangPulang = app(BintangService::class)->bintangPulang((int) max(0, ceil($menitPulang)));
            if ($menitPulang < 0) {
                $bintangPulang = 5;
            }
            if ($bintangMasuk !== null) {
                $bintangHarian = app(BintangService::class)->bintangHarian((int) $bintangMasuk, $bintangPulang);
            }
        }

        $absen->update([
            'waktu_pulang'    => $now->format('Y-m-d H:i:s'),
            'lat_pulang'      => round($lat, 7),
            'lng_pulang'      => round($lng, 7),
            'foto_pulang'     => $foto,
            'durasi_menit'    => $durasiMenit,
            'bintang_pulang'  => $bintangPulang,
            'bintang_harian'  => $bintangHarian,
        ]);

        app(AbsenService::class)->catatLog($user->id, null, 'pulang', $lat, $lng, $akurasi, $jarak, \DateTime::createFromInterface($now));

        return [
            'ok'    => true,
            'pesan' => 'Absen pulang lembur tercatat. Total durasi lembur ' . menit_ke_teks($durasiMenit) . '.',
            'data'  => [
                'durasi_menit'   => $durasiMenit,
                'bintang_pulang' => $bintangPulang,
                'bintang_harian' => $bintangHarian,
            ],
        ];
    }
}
