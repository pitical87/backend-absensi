<?php

namespace Tests\Feature;

use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PegawaiExportTest extends TestCase
{
    use RefreshDatabase;

    private function siapkan(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin-export@example.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap]);

        $unit = UnitKerja::create(['nama' => 'Bidang Uji', 'punya_sub' => true]);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Sub Uji']);
        User::create([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => 'pegawai-export@example.test',
            'nip' => '123456789',
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subUnit->id,
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        return $admin;
    }

    public function test_excel_hanya_mengeluarkan_kolom_yang_dipilih(): void
    {
        $this->siapkan();

        $response = $this->get(route('admin.pegawai.excel', [
            'kolom' => ['nama', 'sub_unit'],
        ]))->assertOk()->assertDownload();

        $file = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $file->getActiveSheet();

        $this->assertSame('Nama Lengkap', $sheet->getCell('A1')->getValue());
        $this->assertSame('Sub Unit', $sheet->getCell('B1')->getValue());
        $this->assertSame('Pegawai Uji', $sheet->getCell('A2')->getValue());
        $this->assertSame('Sub Uji', $sheet->getCell('B2')->getValue());
        $this->assertSame('B', $sheet->getHighestColumn());
    }

    public function test_excel_tanpa_pilihan_kolom_tetap_mengeluarkan_semua_data(): void
    {
        $this->siapkan();

        $response = $this->get(route('admin.pegawai.excel'))->assertOk()->assertDownload();
        $file = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $sheet = $file->getActiveSheet();

        $this->assertSame('No', $sheet->getCell('A1')->getValue());
        $this->assertSame('Nama Lengkap', $sheet->getCell('B1')->getValue());
        $this->assertSame('Status Akun', $sheet->getCell('J1')->getValue());
    }

    public function test_pdf_menerima_pilihan_kolom_dan_menolak_kolom_tidak_valid(): void
    {
        $this->siapkan();

        $this->get(route('admin.pegawai.pdf', [
            'kolom' => ['nama', 'sub_unit'],
        ]))->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="data-pegawai-'.now()->format('Y-m-d').'.pdf"')
            ->assertSee('%PDF', false);

        $this->get(route('admin.pegawai.pdf', [
            'kolom' => ['kolom-tidak-valid'],
        ]))->assertSessionHasErrors('kolom.0');
    }

    public function test_halaman_pegawai_menampilkan_pilihan_kolom_export(): void
    {
        $this->siapkan();

        $this->get(route('admin.pegawai'))
            ->assertOk()
            ->assertSee('Pilih Data Export')
            ->assertSee('Nama Lengkap')
            ->assertSee('Sub Unit')
            ->assertSee('Pilih Semua');
    }
}
