<?php

namespace Tests\Feature;

use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapSubUnitFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_sub_unit_membatasi_pegawai_dan_tersedia_di_halaman_rekap(): void
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Rekap',
            'email' => 'admin.rekap@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
        $unit = UnitKerja::create(['nama' => 'Unit Rekap']);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Sub Rekap']);

        User::create([
            'nama_lengkap' => 'Pegawai Target',
            'email' => 'target.rekap@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subUnit->id,
        ]);
        User::create([
            'nama_lengkap' => 'Pegawai Lain',
            'email' => 'lain.rekap@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'unit_kerja_id' => $unit->id,
        ]);

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);

        $html = $this->get(route('admin.rekap.index', ['sub' => $subUnit->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pegawai Target', $html);
        $this->assertStringNotContainsString('Pegawai Lain', $html);
        $this->assertStringContainsString('Unit Rekap — Sub Rekap', $html);
    }
}
