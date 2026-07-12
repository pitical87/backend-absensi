<?php

namespace App\Controllers;

use App\Libraries\Anomali;
use DateTime;

/**
 * Absen — endpoint AJAX absensi datang/pulang & pemilihan shift.
 */
class Absen extends BaseController
{
    public function proses()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return $this->json(['sukses' => false, 'pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
        }

        $in      = $this->request->getJSON(true) ?? [];
        $tipe    = $in['tipe'] ?? '';
        $lat     = $in['lat'] ?? null;
        $lng     = $in['lng'] ?? null;
        $akurasi = isset($in['akurasi']) ? round((float) $in['akurasi'], 2) : null;
        $foto    = $in['foto'] ?? null;

        if (! in_array($tipe, ['datang', 'pulang'], true) || ! is_numeric($lat) || ! is_numeric($lng)) {
            return $this->json(['sukses' => false, 'pesan' => 'Data absensi tidak lengkap.'], 422);
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        // ---- Selfie ----
        $wajibSelfie = pengaturan('wajib_selfie', '1') === '1';
        $fileFoto    = null;
        if ($foto) {
            $fileFoto = $this->simpanSelfie((int) $u['id'], $tipe, (string) $foto);
            if ($fileFoto === null && $wajibSelfie) {
                return $this->json(['sukses' => false,
                    'pesan' => 'Foto selfie tidak valid. Ulangi pengambilan foto.']);
            }
        } elseif ($wajibSelfie) {
            return $this->json(['sukses' => false,
                'pesan' => 'Foto selfie wajib disertakan saat absensi. Izinkan akses kamera lalu coba lagi.']);
        }

        // ---- Validasi radius lokasi RSUD ----
        $rsLat  = (float) pengaturan('lokasi_lat', 0);
        $rsLng  = (float) pengaturan('lokasi_lng', 0);
        $radius = (float) pengaturan('radius_meter', 100);

        if ($rsLat === 0.0 && $rsLng === 0.0) {
            return $this->json(['sukses' => false,
                'pesan' => 'Titik lokasi RSUD belum diatur oleh admin. Hubungi administrator.']);
        }

        $jarak = hitung_jarak($lat, $lng, $rsLat, $rsLng);
        $now   = new DateTime();

        if ($jarak > $radius) {
            $this->catatLog($u['id'], null, $tipe, $lat, $lng, $akurasi, $jarak, $now, true);
            return $this->json([
                'sukses'     => false,
                'pesan'      => 'Absensi ditolak. Anda berada di luar area RSUD Merauke.',
                'keterangan' => 'Jarak Anda ' . number_format($jarak, 0, ',', '.')
                              . ' m dari titik RSUD (radius maksimal '
                              . number_format($radius, 0, ',', '.') . ' m).',
            ]);
        }

        // ---- Deteksi anomali (absen tetap diterima, hanya ditandai) ----
        [$flagAnomali, $alasanAnomali] = (new Anomali())->periksa((int) $u['id'], $lat, $lng, $akurasi);

        return $tipe === 'datang'
            ? $this->absenDatang($u, $lat, $lng, $akurasi, $jarak, $now, $fileFoto, $flagAnomali, $alasanAnomali)
            : $this->absenPulang($u, $lat, $lng, $akurasi, $jarak, $now, $fileFoto, $flagAnomali, $alasanAnomali);
    }

    // ============================================================
    private function absenDatang(array $u, float $lat, float $lng, ?float $akurasi,
                                 float $jarak, DateTime $now, ?string $foto,
                                 bool $flagAnomali, array $alasanAnomali)
    {
        // Masih ada absensi terbuka (mis. shift malam kemarin)?
        $buka = $this->db->table('absensi')
            ->where('user_id', $u['id'])->where('waktu_pulang IS NULL')
            ->get(1)->getRowArray();
        if ($buka) {
            return $this->json(['sukses' => false,
                'pesan' => 'Anda masih memiliki absensi tanggal ' . tgl_id($buka['tanggal'], false)
                         . ' yang belum ditutup. Silakan absen pulang terlebih dahulu.']);
        }

        if (! $u['shift_id']) {
            return $this->json(['sukses' => false,
                'pesan' => 'Shift kerja Anda belum diatur. Pilih shift pada dasbor atau hubungi admin.']);
        }

        // Tolak absen bila hari ini sedang berstatus izin/cuti disetujui
        $izin = $this->db->table('pengajuan_izin')
            ->where('user_id', $u['id'])->where('status', 'Disetujui')
            ->where('jenis !=', 'Dinas Luar')
            ->where('tanggal_mulai <=', $now->format('Y-m-d'))
            ->where('tanggal_selesai >=', $now->format('Y-m-d'))
            ->get(1)->getRowArray();
        if ($izin) {
            return $this->json(['sukses' => false,
                'pesan' => 'Anda tercatat ' . $izin['jenis'] . ' hari ini ('
                         . tgl_id($izin['tanggal_mulai'], false) . ' s.d. '
                         . tgl_id($izin['tanggal_selesai'], false)
                         . '). Bila tetap masuk kerja, hubungi admin untuk membatalkan izin tersebut.']);
        }

        // Jadwal masuk; shift Malam yang di-absen dini hari dihitung ke tanggal kemarin
        $jadwal = new DateTime($now->format('Y-m-d') . ' ' . $u['shift_jam_masuk']);
        if ($u['shift_kategori'] === 'Malam' && (int) $now->format('G') < 12) {
            $jadwal->modify('-1 day');
        }
        $tanggalShift = $jadwal->format('Y-m-d');

        $sudah = $this->db->table('absensi')
            ->where('user_id', $u['id'])->where('tanggal', $tanggalShift)
            ->countAllResults() > 0;
        if ($sudah) {
            return $this->json(['sukses' => false,
                'pesan' => 'Anda sudah melakukan absen datang untuk tanggal ini.']);
        }

        // Keterlambatan (dengan toleransi)
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

        $this->db->table('absensi')->insert([
            'user_id'         => $u['id'],
            'tanggal'         => $tanggalShift,
            'shift_id'        => $u['shift_id'],
            'waktu_masuk'     => $now->format('Y-m-d H:i:s'),
            'lat_masuk'       => round($lat, 7),
            'lng_masuk'       => round($lng, 7),
            'foto_masuk'      => $foto,
            'status_masuk'    => $status,
            'menit_terlambat' => $menit,
            'flag_anomali'    => $flagAnomali ? 1 : 0,
            'catatan_anomali' => $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
        ]);
        $absensiId = (int) $this->db->insertID();
        $this->catatLog($u['id'], $absensiId, 'datang', $lat, $lng, $akurasi, $jarak, $now);

        return $this->json([
            'sukses'     => true,
            'jenis'      => $jenis,
            'pesan'      => $pesan,
            'keterangan' => 'Absen datang tercatat pukul ' . $now->format('H.i')
                          . ' · jarak ' . number_format($jarak, 0, ',', '.') . ' m dari titik RSUD.',
            'status'     => $status,
            'jam'        => $now->format('H.i'),
        ]);
    }

    // ============================================================
    private function absenPulang(array $u, float $lat, float $lng, ?float $akurasi,
                                 float $jarak, DateTime $now, ?string $foto,
                                 bool $flagAnomali, array $alasanAnomali)
    {
        $rec = $this->db->table('absensi')
            ->where('user_id', $u['id'])->where('waktu_pulang IS NULL')
            ->orderBy('waktu_masuk', 'DESC')->get(1)->getRowArray();

        if (! $rec) {
            $lengkap = $this->db->table('absensi')
                ->where('user_id', $u['id'])->where('tanggal', $now->format('Y-m-d'))
                ->where('waktu_pulang IS NOT NULL')->countAllResults() > 0;
            return $this->json(['sukses' => false, 'pesan' => $lengkap
                ? 'Absensi Anda hari ini sudah lengkap. Terima kasih!'
                : 'Anda belum melakukan absen datang hari ini.']);
        }

        $totalMenit = max(0, (int) floor(($now->getTimestamp() - strtotime($rec['waktu_masuk'])) / 60));

        $this->db->table('absensi')->where('id', $rec['id'])->update([
            'waktu_pulang'      => $now->format('Y-m-d H:i:s'),
            'lat_pulang'        => round($lat, 7),
            'lng_pulang'        => round($lng, 7),
            'foto_pulang'       => $foto,
            'total_menit_kerja' => $totalMenit,
            'flag_anomali'      => ($rec['flag_anomali'] || $flagAnomali) ? 1 : 0,
            'catatan_anomali'   => trim(implode(' | ', array_filter([
                                        $rec['catatan_anomali'],
                                        $alasanAnomali ? implode(' | ', $alasanAnomali) : null,
                                    ])), ' |') ?: null,
        ]);
        $this->catatLog($u['id'], (int) $rec['id'], 'pulang', $lat, $lng, $akurasi, $jarak, $now);

        return $this->json([
            'sukses'     => true,
            'jenis'      => 'sukses',
            'pesan'      => 'Terima kasih atas dedikasi Anda hari ini',
            'keterangan' => 'Absen pulang tercatat pukul ' . $now->format('H.i')
                          . ' · total jam kerja ' . menit_ke_teks($totalMenit) . '.',
            'jam'        => $now->format('H.i'),
        ]);
    }

    // ============================================================
    public function pilihShift()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return $this->json(['sukses' => false, 'pesan' => 'Sesi berakhir.'], 401);
        }
        if (pengaturan('izinkan_pilih_shift', '1') !== '1' && $u['role'] !== 'admin') {
            return $this->json(['sukses' => false,
                'pesan' => 'Perubahan shift hanya dapat dilakukan oleh admin.'], 403);
        }

        $shiftId = (int) (($this->request->getJSON(true) ?? [])['shift_id'] ?? 0);
        $shift   = $this->db->table('shift')->where('id', $shiftId)->where('aktif', 1)
                        ->get()->getRowArray();
        if (! $shift) {
            return $this->json(['sukses' => false, 'pesan' => 'Shift tidak ditemukan.'], 422);
        }

        $terkunci = $this->db->table('absensi')
            ->where('user_id', $u['id'])
            ->groupStart()->where('tanggal', date('Y-m-d'))->orWhere('waktu_pulang IS NULL')->groupEnd()
            ->countAllResults() > 0;
        if ($terkunci) {
            return $this->json(['sukses' => false,
                'pesan' => 'Shift tidak dapat diubah karena absensi hari ini sudah berjalan.']);
        }

        $this->db->table('users')->where('id', $u['id'])->update(['shift_id' => $shiftId]);
        $this->db->table('jadwal_shift')->insert([
            'user_id'         => $u['id'],
            'shift_id'        => $shiftId,
            'tanggal_berlaku' => date('Y-m-d'),
            'diubah_oleh'     => $u['id'],
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->json(['sukses' => true, 'label' => label_shift($shift)]);
    }

    // ============================================================
    private function catatLog(int $userId, ?int $absensiId, string $tipe, float $lat,
                              float $lng, ?float $akurasi, float $jarak, DateTime $now,
                              bool $ditolak = false): void
    {
        $this->db->table('log_lokasi')->insert([
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

    /**
     * Menyimpan selfie base64 (jpeg/png) ke writable/uploads/selfie.
     * Klien sudah memperkecil ke maks 640 px; server memvalidasi ukuran & tipe.
     *
     * @return string|null jalur relatif berkas, atau null bila tidak valid
     */
    private function simpanSelfie(int $userId, string $tipe, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(jpeg|png);base64,#', $dataUrl, $m)) {
            return null;
        }
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($bin === false || strlen($bin) < 1024 || strlen($bin) > 3 * 1024 * 1024) {
            return null;
        }
        // Verifikasi tanda tangan berkas (magic bytes)
        $jpeg = str_starts_with($bin, "\xFF\xD8\xFF");
        $png  = str_starts_with($bin, "\x89PNG");
        if (! $jpeg && ! $png) {
            return null;
        }

        $dir = WRITEPATH . 'uploads/selfie/' . date('Ym');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true)) {
            return null;
        }
        $nama = $userId . '_' . $tipe . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3))
              . ($jpeg ? '.jpg' : '.png');
        return file_put_contents($dir . '/' . $nama, $bin) !== false
            ? 'selfie/' . date('Ym') . '/' . $nama
            : null;
    }
}
