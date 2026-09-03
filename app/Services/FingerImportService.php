<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\FingerPegawai;
use App\Models\JadwalShift;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Http;

class FingerImportService
{
    /**
     * Baca file CSV/teks mesin FingerSpot dan normalisasi menjadi baris scan:
     * [ ['finger_id'=>..,'tanggal'=>'Y-m-d','waktu'=>'H:i','tipe'=>'datang|pulang'], ... ]
     */
    public function parseCsv(string $path, ?string $delimiter = null, ?string $encoding = null): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        if (! $encoding) {
            $encoding = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        }
        if (strtoupper((string) $encoding) !== 'UTF-8') {
            $raw = @mb_convert_encoding($raw, 'UTF-8', $encoding);
        }
        $raw = str_replace("\xEF\xBB\xBF", '', $raw);
        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        if (! $delimiter) {
            $delimiter = $this->deteksiDelimiter($raw);
        }

        $baris = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            // perkecil risiko baris header multi-kolom dengan tanda kutip
            $baris[] = str_getcsv($line, $delimiter, '"', '');
        }
        if ($baris === []) {
            return [];
        }

        $header = $this->normalisasiHeader(array_shift($baris));
        $kolom = $this->petakanKolom($header);

        $scan = [];
        foreach ($baris as $r) {
            if ($this->barisKosong($r)) {
                continue;
            }
            $finger = trim((string) ($kolom['finger'] !== null ? ($r[$kolom['finger']] ?? '') : ''));
            $tanggal = $this->parseTanggal((string) ($kolom['tanggal'] !== null ? ($r[$kolom['tanggal']] ?? '') : ''));
            $waktu = $this->parseWaktu((string) ($kolom['waktu'] !== null ? ($r[$kolom['waktu']] ?? '') : ''));
            if ($finger === '' || ! $tanggal || ! $waktu) {
                continue;
            }

            $tipe = 'scan';
            if ($kolom['tipe'] !== null) {
                $rawTipe = strtolower((string) ($r[$kolom['tipe']] ?? ''));
                if (str_contains($rawTipe, 'pulang') || str_contains($rawTipe, 'out')
                    || str_contains($rawTipe, 'keluar') || str_contains($rawTipe, 'pulang')
                    || str_contains($rawTipe, 'checkout')) {
                    $tipe = 'pulang';
                } elseif (str_contains($rawTipe, 'datang') || str_contains($rawTipe, 'in')
                    || str_contains($rawTipe, 'masuk') || str_contains($rawTipe, 'checkin')) {
                    $tipe = 'datang';
                }
            }

            $scan[] = [
                'finger_id' => mb_strtolower($finger),
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'tipe' => $tipe,
            ];
        }

        return $scan;
    }

    public function ambilDariMesin(string $url, string $tanggalMulai, string $tanggalSelesai, array $extra = []): array
    {
        $response = Http::timeout(30)->withOptions(['verify' => false])
            ->get($url, array_merge([
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'startdate' => $tanggalMulai,
                'enddate' => $tanggalSelesai,
            ], $extra));

        if (! $response->ok()) {
            return [];
        }

        // Terima JSON ataupun CSV dari mesin.
        $isi = $response->body();
        $data = json_decode($isi, true);
        if (is_array($data)) {
            return $this->normalisasiJson($data);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fp');
        file_put_contents($tmp, $isi);
        $scan = $this->parseCsv($tmp);
        @unlink($tmp);

        return $scan;
    }

    /**
     * Proses baris scan ke tabel absensi (GPS priority, FingerSpot melengkapi).
     * Mengembalikan statistik impor.
     */
    public function impor(array $scan): array
    {
        // Termuat mapping finger -> user sekali saja.
        $mapping = FingerPegawai::pluck('user_id', 'finger_id');

        $perOrang = [];
        $tanpaMapping = 0;
        foreach ($scan as $s) {
            $userId = $mapping->get(strtolower($s['finger_id']));
            if (! $userId) {
                $tanpaMapping++;
                continue;
            }
            $perOrang[$userId][$s['tanggal']][] = $s['waktu'];
        }

        $hasil = [
            'ditambah' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'tanpa_mapping' => $tanpaMapping,
        ];

        foreach ($perOrang as $userId => $hari) {
            $user = User::find($userId);
            if (! $user || $user->role === 'admin') {
                continue;
            }
            foreach ($hari as $tanggal => $waktuArr) {
                sort($waktuArr);
                $waktuMasuk = $waktuArr[0] ?? null;
                $waktuPulang = count($waktuArr) > 1 ? $waktuArr[count($waktuArr) - 1] : null;

                $hasilSatu = $this->tulisSatu($user, $tanggal, $waktuMasuk, $waktuPulang);
                $hasil['ditambah'] += $hasilSatu['ditambah'];
                $hasil['diperbarui'] += $hasilSatu['diperbarui'];
                $hasil['dilewati'] += $hasilSatu['dilewati'];
            }
        }

        return $hasil;
    }

    private function tulisSatu(User $user, string $tanggal, ?string $waktuMasuk, ?string $waktuPulang): array
    {
        $out = ['ditambah' => 0, 'diperbarui' => 0, 'dilewati' => 0];

        $rec = Absensi::where('user_id', $user->id)->whereDate('tanggal', $tanggal)->where('sesi', 1)->first();

        if (! $rec) {
            if (! $waktuMasuk) {
                return $out;
            }
            Absensi::create($this->bangunData($user, $tanggal, $waktuMasuk, $waktuPulang));
            $out['ditambah']++;

            return $out;
        }

        // GPS priority: hanya isi bagian yang masih kosong.
        $masukLama = $rec->waktu_masuk?->format('Y-m-d H:i:s');
        $pulangLama = $rec->waktu_pulang?->format('Y-m-d H:i:s');
        if ($masukLama && $pulangLama) {
            $out['dilewati']++;

            return $out;
        }

        // Bangun data berdasarkan jam FingerSpot.
        $dataBaru = $this->bangunData($user, $tanggal, $waktuMasuk, $waktuPulang);

        // Pertahankan isi lama yang tidak diisi FingerSpot (GPS/manual tetap menang).
        $dataBaru['waktu_masuk'] = $masukLama ?: $dataBaru['waktu_masuk'];
        $dataBaru['waktu_pulang'] = $pulangLama ?: $dataBaru['waktu_pulang'];

        $simpan = array_merge([
            'lat_masuk' => $rec->lat_masuk,
            'lng_masuk' => $rec->lng_masuk,
            'lat_pulang' => $rec->lat_pulang,
            'lng_pulang' => $rec->lng_pulang,
            'foto_masuk' => $rec->foto_masuk,
            'foto_pulang' => $rec->foto_pulang,
            'flag_anomali' => $rec->flag_anomali,
            'catatan_anomali' => $rec->catatan_anomali,
        ], $dataBaru);

        $rec->fill($simpan)->save();
        $out['diperbarui']++;

        return $out;
    }

    private function bangunData(User $user, string $tanggal, ?string $waktuMasuk, ?string $waktuPulang): array
    {
        $isDokter = $user->profesi?->nama === 'Dokter';
        $js = JadwalShift::where('user_id', $user->id)->whereDate('tanggal_berlaku', $tanggal)->first();
        $shift = $js?->shift;

        $waktuMasukDt = $waktuMasuk ? new DateTime($tanggal.' '.$waktuMasuk) : null;
        $waktuPulangDt = $waktuPulang ? new DateTime($tanggal.' '.$waktuPulang) : null;

        $toleransi = (int) pengaturan('toleransi_menit', 5);
        $statusMasuk = null;
        $menitTerlambat = 0;
        $statusPulang = null;
        $menitAwal = 0;
        $bintangMasuk = null;
        $bintangPulang = null;
        $bintangHarian = null;
        $totalMenit = null;

        if (! $isDokter && $waktuMasukDt && $shift) {
            $jadwalMasuk = new DateTime($tanggal.' '.date('H:i', strtotime($shift->jam_masuk)));
            $selisih = ceil(($waktuMasukDt->getTimestamp() - $jadwalMasuk->getTimestamp()) / 60);
            $statusMasuk = $selisih <= $toleransi ? 'Tepat Waktu' : 'Terlambat';
            $menitTerlambat = max(0, (int) $selisih);
            $bintangMasuk = BintangService::bintangMasuk((int) $selisih);
        }

        if ($waktuMasukDt && $waktuPulangDt && ! $isDokter) {
            $totalMenit = max(0, (int) floor(($waktuPulangDt->getTimestamp() - $waktuMasukDt->getTimestamp()) / 60));
            if ($shift) {
                $jamShiftMasuk = date('H:i', strtotime($shift->jam_masuk));
                $jamShiftPulang = date('H:i', strtotime($shift->jam_pulang));
                $menitAwal = app(BintangService::class)->selisihMenitPulang(
                    $jamShiftMasuk, $jamShiftPulang, $tanggal, $waktuPulangDt
                );
                $bintangPulang = BintangService::bintangPulang($menitAwal);
                $statusPulang = $menitAwal > 0 ? 'Lebih Awal' : 'Tepat Waktu';
                $bintangHarian = $bintangMasuk !== null
                    ? app(BintangService::class)->bintangHarian($bintangMasuk, $bintangPulang)
                    : null;
            }
        }

        return [
            'user_id' => $user->id,
            'tanggal' => $tanggal,
            'waktu_masuk' => $waktuMasukDt?->format('Y-m-d H:i:s'),
            'waktu_pulang' => $waktuPulangDt?->format('Y-m-d H:i:s'),
            'status_masuk' => $isDokter ? null : ($statusMasuk ?? 'Tepat Waktu'),
            'menit_terlambat' => $menitTerlambat,
            'total_menit_kerja' => $totalMenit,
            'status_pulang' => $statusPulang,
            'menit_awal_pulang' => $menitAwal,
            'bintang_masuk' => $bintangMasuk,
            'bintang_pulang' => $bintangPulang,
            'bintang_harian' => $bintangHarian,
            'sumber' => 'finger',
        ];
    }

    // ---- utilitas parsing ----

    private function deteksiDelimiter(string $raw): string
    {
        $semicolon = substr_count($raw, ';');
        $comma = substr_count(str_replace(['";', '";'], '', $raw), ',');

        return $semicolon > $comma ? ';' : ',';
    }

    private function normalisasiHeader(array $header): array
    {
        return array_map(fn ($h) => mb_strtolower(trim((string) $h, "\"\t ")), $header);
    }

    private function petakanKolom(array $header): array
    {
        $kolom = ['finger' => null, 'tanggal' => null, 'waktu' => null, 'tipe' => null];

        // Pass 1: kolom spesifik.
        foreach ($header as $i => $h) {
            $h0 = trim((string) $h);
            $hKompak = str_replace(['_', '-', ' ', '.'], '', mb_strtolower($h0));
            if ($kolom['finger'] === null && preg_match('/^(enrollid|userid|fingerprintid|fingerid|pegawaiid|iduser|idpegawai|badgenumber|badge)$/', $hKompak)) {
                $kolom['finger'] = $i;
            } elseif ($kolom['tanggal'] === null && preg_match('/tanggal|date/', $hKompak)) {
                $kolom['tanggal'] = $i;
            } elseif ($kolom['waktu'] === null && preg_match('/^jam$|^waktu$|^time$|^attr_time$/', $hKompak)) {
                $kolom['waktu'] = $i;
            } elseif ($kolom['tipe'] === null && preg_match('/status|tipe|verifikasi|keterangan|inout|onofflin/', $hKompak)) {
                $kolom['tipe'] = $i;
            }
        }

        // Pass 2: fallback.
        foreach ($header as $i => $h) {
            $hKompak = str_replace(['_', '-', ' ', '.'], '', mb_strtolower(trim((string) $h)));
            if ($kolom['finger'] === null && preg_match('/id|enroll/i', $hKompak)) {
                $kolom['finger'] = $i;
            }
            if ($kolom['waktu'] === null && (str_contains($hKompak, 'jam') || str_contains($hKompak, 'waktu') || str_contains($hKompak, 'time'))) {
                $kolom['waktu'] = $i;
            }
        }

        return $kolom;
    }

    private function barisKosong(array $r): bool
    {
        foreach ($r as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseTanggal(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        // yyyy-mm-dd
        if (preg_match('/^(\d{4})[-.\/](\d{1,2})[-.\/](\d{1,2})/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        // dd/mm/yyyy atau dd-mm-yyyy
        if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})/', $v, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function parseWaktu(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/(\d{1,2}):(\d{2})(?::\d{2})?/', $v, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h > 23) {
                $h -= 24;
            }

            return sprintf('%02d:%02d', $h, $min);
        }

        return null;
    }

    private function normalisasiJson(array $data): array
    {
        // Kumpulan baris bisa berada di berbagai key; urai flatten sedalam mungkin.
        $baris = $this->flatten($data);
        $scan = [];
        foreach ($baris as $r) {
            if (! is_array($r)) {
                continue;
            }
            $kv = [];
            foreach ($r as $k => $v) {
                if (is_scalar($v)) {
                    $kv[mb_strtolower((string) $k)] = (string) $v;
                }
            }
            $finger = '';
            foreach (['userid', 'enrollid', 'fingerid', 'id', 'no', 'user_id', 'enroll_id'] as $k) {
                if (isset($kv[$k]) && trim($kv[$k]) !== '') {
                    $finger = trim($kv[$k]);
                    break;
                }
            }
            $tanggal = '';
            foreach (['date', 'tanggal', 'on_time'] as $k) {
                if (isset($kv[$k]) && trim($kv[$k]) !== '') {
                    $tanggal = trim($kv[$k]);
                    break;
                }
            }
            $waktu = '';
            foreach (['time', 'jam', 'waktu'] as $k) {
                if (isset($kv[$k]) && trim($kv[$k]) !== '') {
                    $waktu = trim($kv[$k]);
                    break;
                }
            }
            $parsedTanggal = $this->parseTanggal($tanggal);
            $parsedWaktu = $this->parseWaktu($waktu);
            if ($finger === '' || ! $parsedTanggal || ! $parsedWaktu) {
                continue;
            }
            $scan[] = [
                'finger_id' => mb_strtolower($finger),
                'tanggal' => $parsedTanggal,
                'waktu' => $parsedWaktu,
                'tipe' => 'scan',
            ];
        }

        return $scan;
    }

    private function flatten(array $data): array
    {
        $hasil = [];
        foreach ($data as $v) {
            if (is_array($v)) {
                if (isset($v[0]) && is_array($v[0])) {
                    $hasil = array_merge($hasil, $this->flatten($v));
                } else {
                    $hasil[] = $v;
                }
            }
        }

        return $hasil;
    }
}
