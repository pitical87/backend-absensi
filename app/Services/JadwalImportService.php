<?php

namespace App\Services;

use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Import jadwal shift dari Excel berformat grid:
 *
 *   NIP | Email | Nama Lengkap | 01 | 02 | 03 | ... | 31
 *
 * Satu baris = satu pegawai, satu kolom = satu tanggal dalam bulan tujuan.
 * Sel berisi nama shift (pakai dropdown di template), sel kosong = libur.
 *
 * Pegawai dicocokkan lewat NIP, atau lewat email bila NIP kosong/tidak
 * ditemukan. Bila keduanya terisi dan menunjuk pegawai berbeda, baris
 * ditolak sebagai ambigu.
 *
 * Jadwal bulan tujuan untuk pegawai pada baris tersebut diganti penuh oleh
 * isi file — sama seperti menyimpan lewat tab Per Unit / Per Pegawai. Baris
 * yang tidak ada di file tidak ikut tersentuh.
 *
 * Validasi bersifat per baris: bila ada satu sel shift yang tidak dikenal,
 * seluruh baris itu dilewati tanpa mengubah data, agar jadwal lama tidak
 * terhapus sebagian.
 */
class JadwalImportService
{
    /** Nilai sel yang dianggap tidak ada shift (libur). */
    private const SEL_KOSONG = ['', '-', '--', '–', '—', '_', 'libur', 'n/a'];

    private const ALIAS = [
        'nip' => ['NIP', 'NO NIP', 'NO. NIP', 'NOMOR INDUK PEGAWAI', 'NIP PEGAWAI', 'NOINDUKPEGAWAI'],
        'email' => ['EMAIL', 'E-MAIL', 'EMAIL PEGAWAI', 'EMAILPEGAWAI'],
        'nama' => ['NAMA', 'NAMA LENGKAP', 'NAMALENGKAP', 'NAMA PEGAWAI', 'NAMAPEGAWAI', 'PEGAWAI'],
    ];

    /**
     * @return array{sukses: int, entri: int, galat: array<int, string>}
     */
    public function impor(string $path, int $bulan, int $tahun, ?int $oleh = null): array
    {
        $gagal = ['sukses' => 0, 'entri' => 0, 'galat' => []];

        if ($bulan < 1 || $bulan > 12) {
            $gagal['galat'][] = 'Bulan tidak valid.';

            return $gagal;
        }

        if ($tahun < now()->year - 3 || $tahun > now()->year + 3) {
            $gagal['galat'][] = 'Tahun tidak valid.';

            return $gagal;
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $gagal['galat'][] = 'Berkas tidak dapat dibaca: '.$e->getMessage();

            return $gagal;
        }

        $hariDalamBulan = (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        // Jangan andalkan hanya sheet aktif: template kita membuat sheet
        // Petunjuk/Daftar setelah sheet Jadwal, jadi urutan sheet tidak
        // menjamin sheet pertama adalah sheet berisi data.
        [$sheet, $barisHeader, $peta, $galatKolom] = $this->cariSheetData(
            $spreadsheet,
            $bulan,
            $tahun,
            $hariDalamBulan
        );

        if ($sheet === null) {
            $gagal['galat'][] = 'Berkas kosong atau tidak memiliki data.';

            return $gagal;
        }

        if (! isset($peta['nip']) && ! isset($peta['email'])) {
            $gagal['galat'] = array_merge(
                ['Kolom "NIP" atau "Email" wajib ada pada baris header.'],
                $galatKolom
            );

            return $gagal;
        }

        if (! $peta['hari']) {
            $gagal['galat'] = array_merge(
                ['Tidak ada kolom tanggal yang dikenali pada baris header. '
                    .'Format kolom tanggal: 1, 01, atau tanggal lengkap seperti 2026-09-01.'],
                $galatKolom
            );

            return $gagal;
        }

        $petaShift = $this->petaShift();
        $petaPengguna = $this->petaPengguna();
        $adminId = $oleh ?: (int) (session('uid') ?? 0);

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hariDalamBulan);

        $sukses = 0;
        $entri = 0;
        $galat = $galatKolom;
        $sudah = [];
        $shiftTakDikenal = [];
        $shiftAmbigu = [];

        for ($i = $barisHeader + 1; $i <= $sheet->getHighestRow(); $i++) {
            $d = $this->barisKeArray($sheet, $i, $peta);

            $nip = trim((string) ($d['nip'] ?? ''));
            $email = strtolower(trim((string) ($d['email'] ?? '')));
            if ($nip === '' && $email === '') {
                continue;
            }

            $user = $this->cariPengguna($petaPengguna, $nip, $email);
            if (! $user) {
                $galat[] = 'Baris '.$i.': '.$this->alasanPengguna($petaPengguna, $nip, $email);

                continue;
            }

            if (isset($sudah[$user['id']])) {
                $galat[] = 'Baris '.$i.': '.$user['nama'].' sudah ada pada baris '.$sudah[$user['id']]
                    .' — satu pegawai hanya boleh satu baris.';

                continue;
            }

            // Kumpulkan sel dulu; bila ada yang tidak valid, baris ini batal
            // dan data lama tidak boleh terhapus sebagian.
            $barisEntri = [];
            $selBuruk = [];
            foreach ($peta['hari'] as $hari => $idx) {
                $nilai = trim((string) $d['hari'][$hari]);
                if (in_array(mb_strtolower($nilai), self::SEL_KOSONG, true)) {
                    continue;
                }

                [$shiftId, $alasan] = $this->cariShift($petaShift, $nilai);
                if ($shiftId) {
                    $barisEntri[] = [
                        'user_id' => $user['id'],
                        'shift_id' => $shiftId,
                        'tanggal_berlaku' => sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari),
                        'diubah_oleh' => $adminId,
                        'created_at' => now(),
                    ];

                    continue;
                }

                $selBuruk[] = 'tanggal '.$hari.' ("'.$nilai.'")';
                $alasan === 'ambigu' ? $shiftAmbigu[$nilai] = true : $shiftTakDikenal[$nilai] = true;
            }

            if ($selBuruk) {
                $galat[] = 'Baris '.$i.': '.$user['nama'].' — '.implode(', ', $selBuruk).' tidak dikenali, baris dilewati.';

                continue;
            }

            DB::transaction(function () use ($user, $barisEntri, $awal, $akhir) {
                JadwalShift::where('user_id', $user['id'])
                    ->where('tanggal_berlaku', '>=', $awal)
                    ->where('tanggal_berlaku', '<=', $akhir)
                    ->delete();

                foreach (array_chunk($barisEntri, 200) as $potongan) {
                    JadwalShift::insert($potongan);
                }
            });

            $sudah[$user['id']] = $i;
            $sukses++;
            $entri += count($barisEntri);
        }

        if ($shiftTakDikenal) {
            $galat[] = 'Shift tidak dikenal: '.implode(', ', array_map(
                fn ($v) => '"'.$v.'"',
                array_slice(array_keys($shiftTakDikenal), 0, 6)
            )).'. Gunakan nama shift dari dropdown pada template.';
        }

        if ($shiftAmbigu) {
            $galat[] = 'Shift ambigu: '.implode(', ', array_map(
                fn ($v) => '"'.$v.'"',
                array_slice(array_keys($shiftAmbigu), 0, 6)
            )).'. Nama shift dipakai lebih dari satu jadwal — tulis lengkap, contoh "Pagi = 05:00 - 12:00".';
        }

        return ['sukses' => $sukses, 'entri' => $entri, 'galat' => $galat];
    }

    /**
     * Baris pertama yang tidak kosong dipakai sebagai baris header.
     */
    private function cariBarisHeader($sheet): int
    {
        foreach ($sheet->getRowIterator() as $baris) {
            $adaIsi = false;
            $sel = $baris->getCellIterator();
            $sel->setIterateOnlyExistingCells(false);
            foreach ($sel as $c) {
                if (trim((string) $c->getValue()) !== '') {
                    $adaIsi = true;
                    break;
                }
            }
            if ($adaIsi) {
                return $baris->getRowIndex();
            }
        }

        return 0;
    }

    /**
     * Cari sheet yang benar-benar berisi grid jadwal, yaitu sheet yang punya
     * kolom NIP/Email sekaligus minimal satu kolom tanggal. Sheet Petunjuk dan
     * Daftar pada template tidak punya kolom tanggal, jadi tidak akan terpilih.
     *
     * @return array{0: ?object, 1: ?int, 2: ?array, 3: array<int, string>}
     */
    private function cariSheetData(
        Spreadsheet $spreadsheet,
        int $bulan,
        int $tahun,
        int $hariDalamBulan
    ): array {
        $calon = null;

        for ($i = 0; $i < $spreadsheet->getSheetCount(); $i++) {
            $sheet = $spreadsheet->getSheet($i);
            $barisHeader = $this->cariBarisHeader($sheet);
            if (! $barisHeader) {
                continue;
            }

            [$peta, $galat] = $this->petakanKolom(
                $this->barisKeArray($sheet, $barisHeader),
                $bulan,
                $tahun,
                $hariDalamBulan
            );

            $adaIdentitas = isset($peta['nip']) || isset($peta['email']);
            if ($adaIdentitas && $peta['hari']) {
                return [$sheet, $barisHeader, $peta, $galat];
            }

            // Simpan kandidat pertama yang punya kolom identitas saja, supaya
            // pesan galat tetap menyebut masalah yang sebenarnya.
            if ($calon === null && $adaIdentitas) {
                $calon = [$sheet, $barisHeader, $peta, $galat];
            }
        }

        return $calon ?? [null, null, null, []];
    }

    /**
     * Petakan kolom NIP/Email/Nama dan kolom tanggal.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function petakanKolom(array $header, int $bulan, int $tahun, int $hariDalamBulan): array
    {
        $peta = ['hari' => []];
        $galat = [];

        foreach ($header as $idx => $nilai) {
            $kunci = $this->kunci((string) $nilai);
            if ($kunci === '') {
                continue;
            }

            $lapangan = null;
            foreach (self::ALIAS as $nama => $varian) {
                if (in_array($kunci, $varian, true)) {
                    $lapangan = $nama;
                    break;
                }
            }

            if ($lapangan !== null) {
                if (! isset($peta[$lapangan])) {
                    $peta[$lapangan] = $idx;
                }

                continue;
            }

            $hari = $this->hariDariHeader($nilai, $bulan, $tahun);
            if ($hari === null) {
                continue;
            }

            if ($hari < 1 || $hari > $hariDalamBulan) {
                $galat[] = 'Kolom tanggal "'.trim((string) $nilai).'" diabaikan: tanggal tersebut tidak ada di bulan '
                    .BULAN_ID[$bulan].' '.$tahun.'.';

                continue;
            }

            $peta['hari'][$hari] = $idx;
        }

        ksort($peta['hari']);

        return [$peta, $galat];
    }

    /**
     * Ubah judul kolom tanggal menjadi angka hari (1-31), atau null bila kolom
     * itu bukan tanggal. Menangani angka, sel bertipe tanggal Excel, dan teks
     * seperti "01", "2026-09-01", atau "01/09/2026".
     */
    private function hariDariHeader($nilai, int $bulan, int $tahun): ?int
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            $angka = (int) $nilai;

            // Sel tanggal Excel bisa tersimpan sebagai nomor seri.
            if ($angka > 31) {
                if ($angka < 100000) {
                    try {
                        $tanggal = PhpSpreadsheetDate::excelToPHP($angka);
                        $tanggal = Carbon::instance($tanggal);

                        if ((int) $tanggal->month === $bulan && (int) $tanggal->year === $tahun) {
                            return (int) $tanggal->day;
                        }

                        return -1; // tanggal dari bulan lain
                    } catch (\Throwable $e) {
                        return null;
                    }
                }

                return null;
            }

            return $angka >= 1 ? $angka : null;
        }

        $format = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'j F Y', 'j M Y', 'd F Y', 'd M Y'];
        foreach ($format as $pola) {
            // Carbon pada strict mode melempar exception, bukan false.
            try {
                $tanggal = Carbon::createFromFormat($pola, $nilai);
            } catch (\Throwable $e) {
                continue;
            }

            if ($tanggal->year === $tahun && $tanggal->month === $bulan) {
                return (int) $tanggal->day;
            }

            return -1; // tanggal dari bulan lain
        }

        return null;
    }

    /**
     * Kunci pencocokan: huruf besar + hanya karakter alfanumerik, sehingga
     * "budi.santoso@rsud.id" tetap cocok dengan "BUDI SANTOSO@RSUD.ID".
     */
    private function kunci(string $teks): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($teks))) ?? '';
    }

    /**
     * @return array{nip: array<string, array>, email: array<string, array>}
     */
    private function petaPengguna(): array
    {
        $peta = ['nip' => [], 'email' => []];

        $daftar = User::where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->get(['id', 'nama_lengkap', 'nip', 'email']);

        foreach ($daftar as $u) {
            $baris = ['id' => (int) $u->id, 'nama' => (string) $u->nama_lengkap];

            $kunciNip = $this->kunci((string) $u->nip);
            if ($kunciNip !== '' && ! isset($peta['nip'][$kunciNip])) {
                $peta['nip'][$kunciNip] = $baris;
            }

            $kunciEmail = $this->kunci((string) $u->email);
            if ($kunciEmail !== '' && ! isset($peta['email'][$kunciEmail])) {
                $peta['email'][$kunciEmail] = $baris;
            }
        }

        return $peta;
    }

    /**
     * @return array{id: int, nama: string}|null
     */
    private function cariPengguna(array $peta, string $nip, string $email): ?array
    {
        $lewatNip = $nip !== '' ? ($peta['nip'][$this->kunci($nip)] ?? null) : null;
        $lewatEmail = $email !== '' ? ($peta['email'][$this->kunci($email)] ?? null) : null;

        if ($lewatNip && $lewatEmail && $lewatNip['id'] !== $lewatEmail['id']) {
            return null;
        }

        return $lewatNip ?: $lewatEmail;
    }

    private function alasanPengguna(array $peta, string $nip, string $email): string
    {
        $cari = function (string $jenis, string $nilai) use ($peta) {
            if ($nilai === '') {
                return null;
            }

            return $peta[$jenis][$this->kunci($nilai)] ?? null;
        };

        $viaNip = $cari('nip', $nip);
        $viaEmail = $cari('email', $email);

        if ($nip !== '' && $email !== '') {
            if ($viaNip && $viaEmail && $viaNip['id'] !== $viaEmail['id']) {
                return 'NIP '.$nip.' milik '.$viaNip['nama'].', tetapi email '.$email.' milik '
                    .$viaEmail['nama'].' — baris dilewati.';
            }

            $ada = $viaNip ?? $viaEmail;

            return 'NIP '.$nip.' / email '.$email.' tidak cocok dengan data pegawai aktif'
                .($ada ? ' ('.$ada['nama'].')' : '')
                .' — baris dilewati.';
        }

        return ($nip !== '' ? 'NIP '.$nip : 'email '.$email)
            .' tidak ditemukan pada pegawai aktif (admin dan akun nonaktif tidak bisa diimpor) — baris dilewati.';
    }

    /**
     * @return array<string, mixed>
     */
    private function petaShift(): array
    {
        $peta = [];

        $daftar = Shift::where('aktif', 1)
            ->orderBy('kategori')
            ->orderBy('jam_masuk')
            ->get();

        foreach ($daftar as $s) {
            $id = (int) $s->id;
            $kategori = (string) $s->kategori;
            $label = $kategori.' = '.$s->jam_masuk->format('H:i').' - '.$s->jam_pulang->format('H:i');

            $peta['id:'.$id] = $id;
            $peta['label:'.$this->kunci($label)] = $id;
            $peta['label:'.$this->kunci($kategori.' '.$s->jam_masuk->format('H:i').' '.$s->jam_pulang->format('H:i'))] = $id;
            $peta['kategori:'.$this->kunci($kategori)][] = $id;
        }

        return $peta;
    }

    /**
     * @return array{0: int|null, 1: string} id shift (null = gagal) + alasan singkat
     */
    private function cariShift(array $peta, string $nilai): array
    {
        $kunci = $this->kunci($nilai);

        if (isset($peta['id:'.$kunci])) {
            return [(int) $peta['id:'.$kunci], ''];
        }

        if (isset($peta['label:'.$kunci])) {
            return [(int) $peta['label:'.$kunci], ''];
        }

        $kandidat = $peta['kategori:'.$kunci] ?? [];
        if (count($kandidat) === 1) {
            return [(int) $kandidat[0], ''];
        }

        return [null, count($kandidat) > 1 ? 'ambigu' : 'tidak dikenal'];
    }

    private function barisKeArray($sheet, int $nomor, array $peta = []): array
    {
        $tinggi = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $nilai = [];
        for ($i = 1; $i <= $tinggi; $i++) {
            $nilai[] = $sheet->getCell(Coordinate::stringFromColumnIndex($i).$nomor)->getValue();
        }

        if (! $peta) {
            return $nilai;
        }

        $hasil = [];
        foreach (['nip', 'email', 'nama'] as $lapangan) {
            $hasil[$lapangan] = isset($peta[$lapangan]) ? ($nilai[$peta[$lapangan]] ?? null) : null;
        }

        foreach ((array) $peta['hari'] as $hari => $idx) {
            $hasil['hari'][$hari] = $nilai[$idx] ?? null;
        }

        return $hasil;
    }
}
