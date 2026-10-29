<?php

namespace Tests\Feature;

use App\Models\AtasanLangsung;
use App\Models\Profesi;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class PegawaiAtasanAutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();

        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin-atasan-auto@example.com',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap]);
    }

    private function dataPegawai(string $nama, string $email, int $unitId, int $profesiId, array $tambahan = []): array
    {
        return array_merge([
            'nama_lengkap' => $nama,
            'email' => $email,
            'unit_kerja_id' => $unitId,
            'profesi_id' => $profesiId,
            'jabatan_kategori' => 'Staf/Pelaksana',
            'posisi' => 'Staf',
            'password_default' => '1',
        ], $tambahan);
    }

    public function test_atasan_sub_unit_ditambahkan_otomatis_saat_membuat_pegawai(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => true]);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Sub Uji']);
        $atasan = User::create([
            'nama_lengkap' => 'Atasan Sub',
            'email' => 'atasan-sub@example.com',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'unit_kerja_id' => $unit->id,
        ]);
        $subUnit->update(['atasan_id' => $atasan->id]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);

        $this->post('admin/pegawai/simpan', $this->dataPegawai(
            'Pegawai Baru',
            'pegawai-baru-auto@example.com',
            $unit->id,
            $profesi->id,
            ['sub_unit_id' => $subUnit->id]
        ))->assertRedirect('admin/pegawai');

        $pegawai = User::where('email', 'pegawai-baru-auto@example.com')->firstOrFail();
        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasan->id,
        ]);
    }

    public function test_tanpa_atasan_sub_unit_form_menyediakan_pilihan_manual(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => true]);
        SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Sub Tanpa Atasan']);

        $this->get('admin/pegawai/form')
            ->assertOk()
            ->assertSee('id="btn-pilih-atasan"', false)
            ->assertSee('Pilih Atasan')
            ->assertSee('id="pilihan-atasan-manual"', false);
    }

    public function test_pilihan_manual_menambah_relasi_tanpa_menghapus_atasan_lama(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => false]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);
        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai Lama',
            'email' => 'pegawai-lama-auto@example.com',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'unit_kerja_id' => $unit->id,
        ]);
        $atasanLama = User::create([
            'nama_lengkap' => 'Atasan Lama',
            'email' => 'atasan-lama-auto@example.com',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);
        $atasanBaru = User::create([
            'nama_lengkap' => 'Atasan Baru',
            'email' => 'atasan-baru-auto@example.com',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);
        AtasanLangsung::create(['user_id' => $pegawai->id, 'atasan_id' => $atasanLama->id]);
        $this->post('admin/pegawai/simpan', $this->dataPegawai(
            'Pegawai Lama',
            'pegawai-lama-auto@example.com',
            $unit->id,
            $profesi->id,
            [
                'id' => $pegawai->id,
                'atasan' => [$atasanBaru->id],
            ]
        ))->assertRedirect('admin/pegawai');

        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasanLama->id,
        ]);
        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasanBaru->id,
        ]);
    }

    public function test_status_pns_mewajibkan_nip(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => false]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);

        $this->from('admin/pegawai/form')
            ->post('admin/pegawai/simpan', $this->dataPegawai(
                'Pegawai PNS',
                'pegawai-pns@example.com',
                $unit->id,
                $profesi->id,
                ['status_pegawai' => 'PNS']
            ))
            ->assertRedirect('admin/pegawai/form')
            ->assertSessionHasErrors('nip');

        $this->assertDatabaseMissing('users', ['email' => 'pegawai-pns@example.com']);
    }

    public function test_status_non_pns_menyimpan_nip_kosong(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => false]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);

        $this->post('admin/pegawai/simpan', $this->dataPegawai(
            'Pegawai Non PNS',
            'pegawai-non-pns@example.com',
            $unit->id,
            $profesi->id,
            ['nip' => 'NIP-TERISI']
        ))->assertRedirect('admin/pegawai');

        $this->assertDatabaseHas('users', [
            'email' => 'pegawai-non-pns@example.com',
            'status_pegawai' => 'Non-PNS',
            'nip' => null,
        ]);
    }

    public function test_status_pns_menyimpan_nip_wajib(): void
    {
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => false]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);

        $this->post('admin/pegawai/simpan', $this->dataPegawai(
            'Pegawai PNS',
            'pegawai-pns-valid@example.com',
            $unit->id,
            $profesi->id,
            ['status_pegawai' => 'PNS', 'nip' => '19870001']
        ))->assertRedirect('admin/pegawai');

        $this->assertDatabaseHas('users', [
            'email' => 'pegawai-pns-valid@example.com',
            'status_pegawai' => 'PNS',
            'nip' => '19870001',
        ]);
    }
}
