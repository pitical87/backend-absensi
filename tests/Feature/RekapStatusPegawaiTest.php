<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalShift;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RekapStatusPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'nama_lengkap' => 'Admin Laporan',
            'email' => 'admin-laporan@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
    }

    private function sebagaiAdmin(): self
    {
        return $this->withSession([
            'uid' => (int) $this->admin->id,
            'role' => 'admin',
            'nama' => $this->admin->nama_lengkap,
        ]);
    }

    public function test_halaman_filter_pdf_dan_excel_rekap_per_status_pegawai(): void
    {
        $unit = UnitKerja::create(['nama' => 'Rawat Jalan', 'punya_sub' => false]);
        $profDokter = Profesi::create(['nama' => 'Dokter Spesialis']);
        $shiftPagi = Shift::create([
            'kategori' => 'Pagi',
            'jam_masuk' => '08:00:00',
            'jam_pulang' => '14:00:00',
            'aktif' => 1,
        ]);

        // Gunakan bulan Oktober 2025 (sudah selesai penuh, 31 hari)
        // Minggu di Okt 2025: 5, 12, 19, 26 (4 hari) + tambah 1 libur 27 = 5 hari libur -> 26 hari kerja
        HariLibur::create(['tanggal' => '2025-10-27', 'keterangan' => 'Libur Uji']);

        $honor1 = User::create([
            'nama_lengkap' => 'dr. Dyah Pratiwi',
            'email' => 'dyah@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'status_pegawai' => 'Honor',
            'unit_kerja_id' => $unit->id,
            'profesi_id' => $profDokter->id,
        ]);

        $honor2 = User::create([
            'nama_lengkap' => 'dr. Marcelly Salyo',
            'email' => 'marcelly@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'status_pegawai' => 'Honor',
            'unit_kerja_id' => $unit->id,
            'profesi_id' => $profDokter->id,
        ]);

        User::create([
            'nama_lengkap' => 'Pegawai PNS Lain',
            'email' => 'pns-lain@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'status_pegawai' => 'PNS',
            'unit_kerja_id' => $unit->id,
            'profesi_id' => $profDokter->id,
        ]);

        $liburTgl = ['2025-10-05', '2025-10-12', '2025-10-19', '2025-10-26', '2025-10-27'];
        for ($d = 1; $d <= 31; $d++) {
            $tgl = sprintf('2025-10-%02d', $d);
            if (in_array($tgl, $liburTgl, true)) {
                continue;
            }

            JadwalShift::create(['user_id' => $honor1->id, 'shift_id' => $shiftPagi->id, 'tanggal_berlaku' => $tgl]);
            JadwalShift::create(['user_id' => $honor2->id, 'shift_id' => $shiftPagi->id, 'tanggal_berlaku' => $tgl]);

            Absensi::create([
                'user_id' => $honor1->id,
                'tanggal' => $tgl,
                'waktu_masuk' => $tgl.' 07:55:00',
                'waktu_pulang' => $tgl.' 14:05:00',
                'status_masuk' => 'Tepat Waktu',
            ]);

            if ($d <= 18) {
                Absensi::create([
                    'user_id' => $honor2->id,
                    'tanggal' => $tgl,
                    'waktu_masuk' => $tgl.' 07:55:00',
                    'waktu_pulang' => $tgl.' 14:05:00',
                    'status_masuk' => 'Tepat Waktu',
                ]);
            }
        }

        // Izin panjang 19..31 Okt 2025 (10 hari kerja, melewati Minggu 19, 26 & libur 27)
        Izin::create([
            'user_id' => $honor2->id,
            'jenis' => 'Izin',
            'tanggal_mulai' => '2025-10-19',
            'tanggal_selesai' => '2025-10-31',
            'lama_hari' => 13,
            'keterangan' => 'Tes PPDS',
            'status' => 'Disetujui',
        ]);

        // 1. Halaman index filter status_pegawai=Honor
        $this->sebagaiAdmin()
            ->get('/admin/rekap_status?bulan=10&tahun=2025&status_pegawai=Honor')
            ->assertOk()
            ->assertSee('REKAPAN ABSEN KERJA PEGAWAI HONORER RSUD MERAUKE')
            ->assertSee('DOKTER SPESIALIS')
            ->assertSee('dr. Dyah Pratiwi')
            ->assertSee('dr. Marcelly Salyo')
            ->assertSee('IZIN TES PPDS')
            ->assertSee('IZIN 10 (TES PPDS)')
            ->assertDontSee('Pegawai PNS Lain');

        // 2. Export PDF valid
        $pdfRes = $this->sebagaiAdmin()
            ->get('/admin/rekap_status/pdf?bulan=10&tahun=2025&status_pegawai=Honor')
            ->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdfRes->getContent());

        // 3. Export Excel (.xlsx) valid dan isinya sesuai
        $xlsRes = $this->sebagaiAdmin()
            ->get('/admin/rekap_status/excel?bulan=10&tahun=2025&status_pegawai=Honor')
            ->assertOk();
        $filePath = $xlsRes->getFile()->getPathname();
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Absen Oktober 2025', $sheet->getTitle());
        $this->assertSame('REKAPAN ABSEN KERJA PEGAWAI HONORER RSUD MERAUKE', $sheet->getCell('A8')->getValue());
        $this->assertSame('BULAN OKTOBER/ TAHUN 2025', $sheet->getCell('A9')->getValue());
        $this->assertSame('DOKTER SPESIALIS', $sheet->getCell('A12')->getValue());
        $this->assertSame('dr. Dyah Pratiwi', $sheet->getCell('B13')->getValue());
        $this->assertSame(26, (int) $sheet->getCell('AH13')->getValue());
        $this->assertSame(100, (int) $sheet->getCell('AI13')->getValue());
        $this->assertSame('dr. Marcelly Salyo', $sheet->getCell('B14')->getValue());
        $this->assertSame(16, (int) $sheet->getCell('AH14')->getValue());
        $this->assertSame(62, (int) $sheet->getCell('AI14')->getValue());
        $this->assertSame('IZIN 10 (TES PPDS)', $sheet->getCell('AJ14')->getValue());
    }
}
