<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profesi;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\RekapStatusPegawaiService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class RekapStatusController extends Controller
{
    private function ambilParam(Request $request): array
    {
        $status = (string) $request->query('status_pegawai', '');
        $grup = (string) $request->query('grup', 'profesi');

        return [
            'bulan' => min(12, max(1, (int) ($request->query('bulan') ?: now()->month))),
            'tahun' => min(2100, max(2024, (int) ($request->query('tahun') ?: now()->year))),
            'status_pegawai' => in_array($status, User::STATUS_PEGAWAI, true) ? $status : '',
            'unit' => (int) $request->query('unit'),
            'sub' => (int) $request->query('sub'),
            'prof' => (int) $request->query('prof'),
            'grup' => array_key_exists($grup, RekapStatusPegawaiService::GRUP_PILIHAN) ? $grup : 'profesi',
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 60),
        ];
    }

    private function qsDari(array $f): string
    {
        return http_build_query(array_filter([
            'bulan' => $f['bulan'],
            'tahun' => $f['tahun'],
            'status_pegawai' => $f['status_pegawai'] ?: null,
            'unit' => $f['unit'] ?: null,
            'sub' => $f['sub'] ?: null,
            'prof' => $f['prof'] ?: null,
            'grup' => $f['grup'] ?: null,
            'q' => $f['q'] ?: null,
        ]));
    }

    public function index(Request $request, RekapStatusPegawaiService $service)
    {
        $f = $this->ambilParam($request);
        $matriks = $service->bangunMatriks($f);

        return view('admin.rekap_status.index', [
            'judulHalaman' => 'Rekap Absen Per Status Pegawai',
            'menuAktif' => 'rekap_status',
            'f' => $f,
            'qs' => $this->qsDari($f),
            'matriks' => $matriks,
            'statusList' => User::STATUS_PEGAWAI,
            'grupPilihan' => RekapStatusPegawaiService::GRUP_PILIHAN,
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'subUnitList' => SubUnit::with('unitKerja:id,nama')
                ->orderBy('unit_kerja_id')->orderBy('nama')->get()->all(),
            'profList' => Profesi::orderBy('id')->get()->all(),
        ]);
    }

    public function pdf(Request $request, RekapStatusPegawaiService $service)
    {
        $f = $this->ambilParam($request);
        $matriks = $service->bangunMatriks($f);

        $logoKiri = $this->logoDataUri(public_path('assets/img/kop-kabupaten-merauke.png'));
        $logoKanan = $this->logoDataUri(public_path('assets/img/kop-rsud-merauke.png'));

        $html = view('pdf.rekap_status_pegawai', [
            'matriks' => $matriks,
            'logoKiri' => $logoKiri,
            'logoKanan' => $logoKanan,
            'tanggalCetak' => tgl_id(now()->toDateString(), false),
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');

        ini_set('memory_limit', '512M');
        $dompdf->render();

        $slugStatus = $f['status_pegawai'] !== ''
            ? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $f['status_pegawai']))
            : 'semua';
        $namaBerkas = sprintf(
            'rekap-absen-%s-%s-%d.pdf',
            $slugStatus,
            strtolower($matriks['nama_bulan']),
            $matriks['tahun']
        );

        catat_aktivitas(
            'Export PDF Rekap Per Status',
            ($f['status_pegawai'] ?: 'Semua').' · '.$matriks['nama_bulan'].' '.$matriks['tahun']
                .' ('.$matriks['total_pegawai'].' pegawai)'
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'"',
        ]);
    }

    public function excel(Request $request, RekapStatusPegawaiService $service)
    {
        $f = $this->ambilParam($request);
        $matriks = $service->bangunMatriks($f);

        $hariDalamBulan = (int) $matriks['hari_dalam_bulan'];
        $kolomHariAkhir = 2 + $hariDalamBulan;
        $kolomJmlhIdx = $kolomHariAkhir + 1;
        $kolomPersenIdx = $kolomHariAkhir + 2;
        $kolomKetIdx = $kolomHariAkhir + 3;

        $colHariAwal = Coordinate::stringFromColumnIndex(3);
        $colHariAkhir = Coordinate::stringFromColumnIndex($kolomHariAkhir);
        $colJmlh = Coordinate::stringFromColumnIndex($kolomJmlhIdx);
        $colPersen = Coordinate::stringFromColumnIndex($kolomPersenIdx);
        $colKet = Coordinate::stringFromColumnIndex($kolomKetIdx);

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet();
        $sheetTitle = mb_substr('Absen '.$matriks['nama_bulan'].' '.$matriks['tahun'], 0, 31);
        $ws->setTitle($sheetTitle);

        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);

        // KOP Surat (baris 1-5)
        $kopTeks = [
            1 => ['DINAS KESEHATAN', 11, true],
            2 => ['RUMAH SAKIT UMUM DAERAH KELAS C', 11, true],
            3 => ['MERAUKE', 11, true],
            4 => ['Jalan Soekarjo Wiryopranoto No. 1-Kelurahan Karang Indah-Merauke', 9, true],
            5 => ['Pos-el : rsudmerauke1@gmail.com. Kode Pos : 99614', 9, true],
        ];
        foreach ($kopTeks as $baris => [$teks, $ukuran, $tebal]) {
            $ws->mergeCells("A{$baris}:{$colKet}{$baris}");
            $ws->setCellValue("A{$baris}", $teks);
            $ws->getStyle("A{$baris}")->getFont()->setSize($ukuran)->setBold($tebal);
            $ws->getStyle("A{$baris}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getRowDimension($baris)->setRowHeight($baris <= 3 ? 16 : 14);
        }

        // Garis bawah ganda KOP pada baris 6
        $ws->getRowDimension(6)->setRowHeight(6);
        $ws->getStyle("A6:{$colKet}6")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_DOUBLE)
            ->getColor()->setARGB('FF000000');

        // Logo kiri & kanan
        $logoKiriPath = public_path('assets/img/kop-kabupaten-merauke.png');
        if (is_file($logoKiriPath)) {
            $drawingKiri = new Drawing;
            $drawingKiri->setName('Logo Kabupaten Merauke');
            $drawingKiri->setPath($logoKiriPath);
            $drawingKiri->setHeight(68);
            $drawingKiri->setCoordinates('C1');
            $drawingKiri->setOffsetX(8);
            $drawingKiri->setOffsetY(4);
            $drawingKiri->setWorksheet($ws);
        }

        $logoKananPath = public_path('assets/img/kop-rsud-merauke.png');
        if (is_file($logoKananPath)) {
            $drawingKanan = new Drawing;
            $drawingKanan->setName('Logo RSUD Merauke');
            $drawingKanan->setPath($logoKananPath);
            $drawingKanan->setHeight(68);
            $drawingKanan->setCoordinates(Coordinate::stringFromColumnIndex(max(5, $kolomKetIdx - 3)).'1');
            $drawingKanan->setOffsetX(4);
            $drawingKanan->setOffsetY(4);
            $drawingKanan->setWorksheet($ws);
        }

        // Judul & periode
        $ws->getRowDimension(7)->setRowHeight(8);
        $ws->mergeCells("A8:{$colKet}8");
        $ws->setCellValue('A8', $matriks['judul']);
        $ws->getStyle('A8')->getFont()->setSize(11)->setBold(true);
        $ws->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension(8)->setRowHeight(18);

        $ws->mergeCells("A9:{$colKet}9");
        $ws->setCellValue('A9', $matriks['periode_label']);
        $ws->getStyle('A9')->getFont()->setSize(10)->setBold(true);
        $ws->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension(9)->setRowHeight(16);

        $borderTipis = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];

        $row = 10;
        $kelompokList = $matriks['kelompok'];

        if ($kelompokList === []) {
            $ws->mergeCells("A{$row}:{$colKet}{$row}");
            $ws->setCellValue("A{$row}", 'Tidak ada data pegawai sesuai filter.');
            $ws->getStyle("A{$row}:{$colKet}{$row}")->applyFromArray($borderTipis);
            $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        } else {
            foreach ($kelompokList as $idxKelompok => $kelompok) {
                if ($idxKelompok > 0) {
                    $ws->getRowDimension($row)->setRowHeight(10);
                    $row++;
                }

                $headerTop = $row;
                $headerBot = $row + 1;

                // Header tabel
                $ws->mergeCells("A{$headerTop}:A{$headerBot}");
                $ws->setCellValue("A{$headerTop}", 'NO');

                $ws->mergeCells("B{$headerTop}:B{$headerBot}");
                $ws->setCellValue("B{$headerTop}", 'N A M A');

                $ws->mergeCells("{$colHariAwal}{$headerTop}:{$colHariAkhir}{$headerTop}");
                $ws->setCellValue("{$colHariAwal}{$headerTop}", 'T A N G G A L');

                $ws->mergeCells("{$colJmlh}{$headerTop}:{$colJmlh}{$headerBot}");
                $ws->setCellValue("{$colJmlh}{$headerTop}", 'JMLH');

                $ws->mergeCells("{$colPersen}{$headerTop}:{$colPersen}{$headerBot}");
                $ws->setCellValue("{$colPersen}{$headerTop}", '%');

                $ws->mergeCells("{$colKet}{$headerTop}:{$colKet}{$headerBot}");
                $ws->setCellValue("{$colKet}{$headerTop}", 'KET');

                $ws->getStyle("A{$headerTop}:{$colKet}{$headerBot}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E7EB'],
                    ],
                ]);

                for ($d = 1; $d <= $hariDalamBulan; $d++) {
                    $col = Coordinate::stringFromColumnIndex(2 + $d);
                    $ws->setCellValue("{$col}{$headerBot}", $d);
                    if ($matriks['tanggal_meta'][$d]['merah']) {
                        $ws->getStyle("{$col}{$headerBot}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 8.5, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFC00000'],
                            ],
                        ]);
                    }
                }

                $ws->getRowDimension($headerTop)->setRowHeight(16);
                $ws->getRowDimension($headerBot)->setRowHeight(16);
                $row = $headerBot + 1;

                // Subheader kelompok (mis. DOKTER SPESIALIS)
                if ($kelompok['nama'] !== '') {
                    $ws->mergeCells("A{$row}:{$colKet}{$row}");
                    $ws->setCellValue("A{$row}", $kelompok['nama']);
                    $ws->getStyle("A{$row}")->getFont()->setBold(true)->setSize(9);
                    $ws->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $ws->getStyle("A{$row}:{$colKet}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF3F4F6');
                    $ws->getRowDimension($row)->setRowHeight(16);
                    $row++;
                }

                $no = 1;
                foreach ($kelompok['pegawai'] as $p) {
                    $ws->setCellValue("A{$row}", $no++);
                    $ws->setCellValue("B{$row}", $p['nama']);
                    $ws->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $ws->getStyle("B{$row}")->getFont()->setBold(true)->setSize(8.5);
                    $ws->getStyle("B{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    foreach ($p['sel'] as $cell) {
                        $cMulai = Coordinate::stringFromColumnIndex(2 + $cell['mulai']);
                        $cSampai = Coordinate::stringFromColumnIndex(2 + $cell['sampai']);
                        if ($cell['colspan'] > 1) {
                            $ws->mergeCells("{$cMulai}{$row}:{$cSampai}{$row}");
                        }
                        $ws->setCellValue("{$cMulai}{$row}", $cell['teks']);
                        $styleCell = $ws->getStyle("{$cMulai}{$row}:{$cSampai}{$row}");
                        $styleCell->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);

                        if ($cell['tipe'] === 'libur') {
                            $styleCell->getFont()->setBold(true)->setSize(8.5)->getColor()->setARGB('FF111827');
                            $styleCell->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FAFB');
                        } elseif ($cell['tipe'] === 'alpa') {
                            $styleCell->getFont()->setBold(true)->setSize(8.5)->getColor()->setARGB('FFC00000');
                        } elseif ($cell['tipe'] === 'izin' || $cell['tipe'] === 'dinas') {
                            $styleCell->getFont()->setBold(true)->setSize(8)->getColor()->setARGB('FF1D4ED8');
                            if ($cell['gabung']) {
                                $styleCell->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
                            }
                        } elseif ($cell['tipe'] === 'hadir') {
                            $styleCell->getFont()->setSize(8)->getColor()->setARGB('FF15803D');
                        }
                    }

                    $ws->setCellValue("{$colJmlh}{$row}", $p['jmlh']);
                    $ws->setCellValue("{$colPersen}{$row}", $p['persen']);
                    $ws->setCellValue("{$colKet}{$row}", $p['ket']);

                    $ws->getStyle("{$colJmlh}{$row}:{$colPersen}{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $ws->getStyle("{$colKet}{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $ws->getStyle("{$colKet}{$row}")->getFont()->setSize(8);

                    $ws->getRowDimension($row)->setRowHeight(16);
                    $row++;
                }

                $akhirBlok = $row - 1;
                $ws->getStyle("A{$headerTop}:{$colKet}{$akhirBlok}")->applyFromArray($borderTipis);
            }
        }

        // Keterangan kode di bawah tabel
        $row++;
        $ws->mergeCells("A{$row}:{$colKet}{$row}");
        $ws->setCellValue("A{$row}", 'Keterangan Kode: H = Hadir, L = Libur/Minggu, I = Izin, S = Sakit, C = Cuti, DL = Dinas Luar, A = Alpa');
        $ws->getStyle("A{$row}")->getFont()->setItalic(true)->setSize(8);

        // Lebar kolom
        $ws->getColumnDimension('A')->setWidth(4.5);
        $ws->getColumnDimension('B')->setWidth(30);
        for ($d = 1; $d <= $hariDalamBulan; $d++) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $d))->setWidth(3.4);
        }
        $ws->getColumnDimension($colJmlh)->setWidth(6.5);
        $ws->getColumnDimension($colPersen)->setWidth(5.5);
        $ws->getColumnDimension($colKet)->setWidth(28);

        // Setup cetak Landscape 1 halaman lebar
        $ws->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $ws->setShowGridlines(true);

        $slugStatus = $f['status_pegawai'] !== ''
            ? strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $f['status_pegawai']))
            : 'semua';
        $namaBerkas = sprintf(
            'rekap-absen-%s-%s-%d.xlsx',
            $slugStatus,
            strtolower($matriks['nama_bulan']),
            $matriks['tahun']
        );

        catat_aktivitas(
            'Export Excel Rekap Per Status',
            ($f['status_pegawai'] ?: 'Semua').' · '.$matriks['nama_bulan'].' '.$matriks['tahun']
                .' ('.$matriks['total_pegawai'].' pegawai)'
        );

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $temp = tempnam(sys_get_temp_dir(), 'rkp_st_').'.xlsx';
        $writer->save($temp);

        return response()->download($temp, $namaBerkas)
            ->deleteFileAfterSend(true);
    }

    private function logoDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $bin = @file_get_contents($path);
        if ($bin === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bin);
    }
}
