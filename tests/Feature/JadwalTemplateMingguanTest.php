<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\JadwalShift;
use App\Models\JadwalTemplateMingguan;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\JadwalEfektifService;
use App\Services\RekapService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalTemplateMingguanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Jadwal',
            'email' => 'admin.template@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);

        return $admin;
    }

    private function setupSubUnit(): SubUnit
    {
        $unit = UnitKerja::create(['nama' => 'Unit Template']);

        return SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'TU']);
    }

    private function pegawai(SubUnit $subUnit): User
    {
        return User::create([
            'nama_lengkap' => 'Pegawai Template',
            'email' => 'pegawai.template@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'unit_kerja_id' => $subUnit->unit_kerja_id,
            'sub_unit_id' => $subUnit->id,
        ]);
    }

    private function shift(string $kategori, string $masuk, string $pulang): Shift
    {
        return Shift::create([
            'kategori' => $kategori,
            'jam_masuk' => $masuk,
            'jam_pulang' => $pulang,
        ]);
    }

    public function test_admin_dapat_menyimpan_template_shift_per_hari_sub_unit(): void
    {
        $this->admin();
        $subUnit = $this->setupSubUnit();
        $pagi = $this->shift('Pagi', '08:00', '14:00');
        $malam = $this->shift('Malam', '20:00', '08:00');

        $this->postJson(route('admin.jadwal.template-mingguan'), [
            'sub_unit_id' => $subUnit->id,
            'jadwal' => [1 => $pagi->id, 2 => '', 7 => $malam->id],
        ])->assertOk()->assertJson([
            'pesan' => 'Template jadwal mingguan berhasil disimpan.',
        ]);

        $this->assertDatabaseHas('jadwal_template_mingguan', [
            'sub_unit_id' => $subUnit->id,
            'hari' => 1,
            'shift_id' => $pagi->id,
        ]);
        $this->assertDatabaseHas('jadwal_template_mingguan', [
            'sub_unit_id' => $subUnit->id,
            'hari' => 7,
            'shift_id' => $malam->id,
        ]);
        $this->assertDatabaseMissing('jadwal_template_mingguan', [
            'sub_unit_id' => $subUnit->id,
            'hari' => 2,
        ]);

        $this->get(route('admin.jadwal.template-mingguan.index', ['sub_unit' => $subUnit->id]))
            ->assertOk()
            ->assertSee('Template Jadwal Mingguan')
            ->assertSee('Tambah Template')
            ->assertSee('value="'.$pagi->id.'"', false);
        $this->get(route('admin.jadwal.template-mingguan.data'))
            ->assertOk()
            ->assertJsonPath('data.0.unit_nama', 'Unit Template')
            ->assertJsonPath('data.0.sub_unit_nama', 'TU')
            ->assertJsonPath('data.0.jadwal.1.jam_masuk', '08:00');
    }

    public function test_admin_dapat_menghapus_template_mingguan_sub_unit(): void
    {
        $this->admin();
        $subUnit = $this->setupSubUnit();
        $pagi = $this->shift('Pagi', '08:00', '14:00');
        JadwalTemplateMingguan::create([
            'sub_unit_id' => $subUnit->id,
            'hari' => 1,
            'shift_id' => $pagi->id,
        ]);

        $this->postJson(route('admin.jadwal.template-mingguan.hapus'), [
            'sub_unit_id' => $subUnit->id,
        ])->assertOk()->assertJson([
            'pesan' => 'Template jadwal untuk sub unit TU berhasil dihapus.',
        ]);

        $this->assertDatabaseMissing('jadwal_template_mingguan', [
            'sub_unit_id' => $subUnit->id,
        ]);
        $this->get(route('admin.jadwal.index', ['sub_unit' => $subUnit->id]))
            ->assertOk()
            ->assertDontSee('Template Jadwal Mingguan');
    }

    public function test_template_diterapkan_hanya_pada_hari_yang_diatur_dan_tidak_pada_hari_libur(): void
    {
        $subUnit = $this->setupSubUnit();
        $pegawai = $this->pegawai($subUnit);
        $pagi = $this->shift('Pagi', '08:00', '14:00');
        $tanggal = '2026-10-26'; // Senin

        JadwalTemplateMingguan::create([
            'sub_unit_id' => $subUnit->id,
            'hari' => 1,
            'shift_id' => $pagi->id,
        ]);

        $service = app(JadwalEfektifService::class);
        $peta = $service->petaUntukPegawai($pegawai, $tanggal, '2026-10-27');
        $this->assertSame([$tanggal => (int) $pagi->id], $peta);

        HariLibur::create(['tanggal' => $tanggal, 'keterangan' => 'Hari libur uji']);
        $this->assertSame([], $service->petaUntukPegawai($pegawai, $tanggal, $tanggal));
    }

    public function test_jadwal_per_tanggal_mengalahkan_template_meskipun_tanggal_libur(): void
    {
        $subUnit = $this->setupSubUnit();
        $pegawai = $this->pegawai($subUnit);
        $pagi = $this->shift('Pagi', '08:00', '14:00');
        $sore = $this->shift('Sore', '14:00', '20:00');
        $tanggal = '2026-10-26';

        JadwalTemplateMingguan::create([
            'sub_unit_id' => $subUnit->id,
            'hari' => 1,
            'shift_id' => $pagi->id,
        ]);
        HariLibur::create(['tanggal' => $tanggal, 'keterangan' => 'Hari libur uji']);
        JadwalShift::create([
            'user_id' => $pegawai->id,
            'shift_id' => $sore->id,
            'tanggal_berlaku' => $tanggal,
        ]);

        $this->assertSame(
            [$tanggal => (int) $sore->id],
            app(JadwalEfektifService::class)->petaUntukPegawai($pegawai, $tanggal, $tanggal)
        );
    }

    public function test_hari_libur_mingguan_tidak_mewarisi_shift_hari_sebelumnya(): void
    {
        Carbon::setTestNow('2026-10-27 08:00:00'); // Selasa
        try {
            $subUnit = $this->setupSubUnit();
            $pegawai = $this->pegawai($subUnit);
            $pagi = $this->shift('Pagi', '08:00', '14:00');
            JadwalTemplateMingguan::create([
                'sub_unit_id' => $subUnit->id,
                'hari' => 1,
                'shift_id' => $pagi->id,
            ]);

            $this->assertNull($pegawai->shift);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shift_pegawai_diambil_dari_template_pada_hari_yang_diatur(): void
    {
        Carbon::setTestNow('2026-10-26 08:00:00'); // Senin
        try {
            $subUnit = $this->setupSubUnit();
            $pegawai = $this->pegawai($subUnit);
            $pagi = $this->shift('Pagi', '08:00', '14:00');
            JadwalTemplateMingguan::create([
                'sub_unit_id' => $subUnit->id,
                'hari' => 1,
                'shift_id' => $pagi->id,
            ]);

            $this->assertSame((int) $pagi->id, (int) $pegawai->shift->id);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rekap_menghitung_hari_template_sebagai_hari_efektif(): void
    {
        Carbon::setTestNow('2026-10-28 12:00:00');
        try {
            $subUnit = $this->setupSubUnit();
            $pegawai = $this->pegawai($subUnit);
            $pagi = $this->shift('Pagi', '08:00', '14:00');
            JadwalTemplateMingguan::create([
                'sub_unit_id' => $subUnit->id,
                'hari' => 1,
                'shift_id' => $pagi->id,
            ]);

            $rekap = app(RekapService::class)->hitung($pegawai->id, 10, 2026);

            $this->assertSame(4, $rekap['hari_efektif']);
            $this->assertSame(4, $rekap['alpa']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
