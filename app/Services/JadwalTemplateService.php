<?php

namespace App\Services;

use App\Models\Shift;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembuat template Excel import jadwal shift.
 *
 * Dipakai bersama oleh halaman admin (admin/jadwal/template) dan API mobile
 * (api/mobile/jadwal/template) supaya format berkasnya selalu sama.
 *
 * Format grid: NIP | Email | Nama Lengkap | 01 | 02 | ... , sel berisi nama
 * shift dengan dropdown, bulan/tahun mengikuti periode yang diminta.
 */
class JadwalTemplateService
{
    /**
     * Buat berkas template dan kembalikan path file sementara.
     *
     * Pemanggil yang mengirim berkas (response()->download) bertanggung jawab
     * menghapus file ini setelah dikirim.
     */
    public function buat(int $bulan, int $tahun): string
    {
        $hariDalamBulan = (int) cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadwal');

        $sheet->setCellValue('A1', 'NIP');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Nama Lengkap');

        for ($hari = 1; $hari <= $hariDalamBulan; $hari++) {
            $kolom = Coordinate::stringFromColumnIndex($hari + 3);
            $sheet->setCellValue($kolom.'1', sprintf('%02d', $hari));
        }

        $kolomTerakhir = Coordinate::stringFromColumnIndex($hariDalamBulan + 3);
        $sheet->getStyle('A1:'.$kolomTerakhir.'1')->getFont()->setBold(true);

        // NIP sering dibaca Excel sebagai angka panjang, kunci sebagai teks.
        $sheet->getStyle('A2:A501')->getNumberFormat()->setFormatCode('@');

        $lebar = ['A' => 22, 'B' => 28, 'C' => 26];
        foreach ($lebar as $kolom => $px) {
            $sheet->getColumnDimension($kolom)->setWidth($px);
        }
        for ($hari = 1; $hari <= $hariDalamBulan; $hari++) {
            $kolom = Coordinate::stringFromColumnIndex($hari + 3);
            $sheet->getColumnDimension($kolom)->setWidth(9);
        }

        $this->tambahDropdownShift($spreadsheet, $sheet, $kolomTerakhir);

        // Bekukan baris header dan tiga kolom identitas saat file dibuka.
        $sheet->freezePane('D2');

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');

        $contoh = ['198605142010011001', 'budi@rsud-mrk.id', 'Budi Santoso'];
        $contohJadwal = [];
        for ($hari = 1; $hari <= $hariDalamBulan; $hari++) {
            $contohJadwal[] = ($hari % 7 === 0) ? null : 'Pagi';
        }

        $baris = [
            ['Import Jadwal Shift — '.BULAN_ID[$bulan].' '.$tahun],
            [],
            ['1', 'Isi kolom NIP atau Email untuk menandai pegawai. Kolom yang kosong tidak diimpor.'],
            ['', 'Boleh diisi salah satu; bila keduanya diisi harus menunjuk pegawai yang sama.'],
            ['', 'Hanya pegawai aktif (role selain admin) yang bisa diimpor.'],
            ['2', 'Kolom tanggal dimulai setelah kolom Nama Lengkap, bernomor 01 sampai '.$hariDalamBulan.'.'],
            ['', 'Isi dengan nama shift dari dropdown. Sel kosong berarti libur.'],
            ['3', 'Jadwal bulan '.BULAN_ID[$bulan].' '.$tahun.' untuk pegawai pada baris tersebut'],
            ['', 'dihapus lalu diisi ulang dari isi file. Pegawai yang tidak ada di file tidak tersentuh.'],
            ['', 'Baris tanpa nilai shift sama sekali akan mengosongkan jadwal bulan itu.'],
            ['4', 'Satu pegawai hanya boleh satu baris.'],
            ['5', 'Bila ada satu sel shift yang tidak dikenal, seluruh baris dilewati tanpa'],
            ['', 'mengubah data, agar jadwal lama tidak terhapus sebagian.'],
            [],
            ['Contoh baris (baris contoh ini boleh dihapus):'],
            array_merge($contoh, $contohJadwal),
        ];

        foreach ($baris as $no => $nilai) {
            foreach ((array) $nilai as $kol => $isi) {
                if ($isi === null || $isi === '') {
                    continue;
                }
                $petunjuk->setCellValue(Coordinate::stringFromColumnIndex($kol + 1).($no + 1), $isi);
            }
        }
        $petunjuk->getStyle('A1')->getFont()->setBold(true);
        $petunjuk->getStyle('A14')->getFont()->setBold(true);
        $petunjuk->getColumnDimension('A')->setWidth(4);
        $petunjuk->getColumnDimension('B')->setWidth(120);

        $spreadsheet->setActiveSheetIndex(0);

        // Tulis langsung ke file temp; nama berkas yang dikirim sudah diatur
        // lewat response()->download, jadi tidak ada file stub yang tertinggal.
        $temp = tempnam(sys_get_temp_dir(), 'tpl_jadwal_');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($temp);

        return $temp;
    }

    /**
     * Nama berkas template, mis. template_jadwal_2027-03.xlsx
     */
    public function namaBerkas(int $bulan, int $tahun): string
    {
        return sprintf('template_jadwal_%04d-%02d.xlsx', $tahun, $bulan);
    }

    /**
     * Nama shift siap pakai untuk dropdown, memakai format yang sama dengan
     * select di halaman jadwal. Nama kategori yang dipakai lebih dari satu
     * jadwal selalu ditulis lengkap beserta jamnya.
     *
     * @return array<int, string>
     */
    public function pilihanShift(): array
    {
        $kategori = Shift::where('aktif', 1)
            ->orderBy('kategori')
            ->orderBy('jam_masuk')
            ->get();

        $jumlah = $kategori->countBy('kategori');

        return $kategori
            ->map(fn ($s) => $jumlah[$s->kategori] > 1
                ? $s->kategori.' = '.$s->jam_masuk->format('H:i').' - '.$s->jam_pulang->format('H:i')
                : $s->kategori)
            ->values()
            ->all();
    }

    /**
     * Dropdown nama shift (disimpan di sheet "Daftar" yang disembunyikan).
     */
    private function tambahDropdownShift(Spreadsheet $spreadsheet, Worksheet $jadwal, string $kolomTerakhir): void
    {
        $daftar = $spreadsheet->createSheet();
        $daftar->setTitle('Daftar');
        $daftar->setCellValue('A1', 'Daftar Shift');

        $baris = 2;
        foreach ($this->pilihanShift() as $nilai) {
            $daftar->setCellValue('A'.$baris++, $nilai);
        }
        $jumlah = $baris - 1;
        $daftar->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $dv = new DataValidation;
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setFormula1('Daftar!$A$2:$A$'.$jumlah);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(false);
        $dv->setShowErrorMessage(true);
        $dv->setErrorTitle('Nilai tidak valid');
        $dv->setError('Pilih nama shift dari daftar dropdown.');
        $jadwal->setDataValidation('D2:'.$kolomTerakhir.'501', $dv);
    }
}
