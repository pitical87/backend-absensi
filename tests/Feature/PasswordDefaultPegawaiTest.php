<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class PasswordDefaultPegawaiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();
    }

    private function siap(): array
    {
        $unitId = DB::table('unit_kerja')->insertGetId(['nama' => 'Unit Uji', 'punya_sub' => 0]);
        $profId = DB::table('profesi')->insertGetId(['nama' => 'Profesi Uji']);

        $admin = User::create([
            'nama_lengkap' => 'Admin',
            'email' => 'admin.pw@example.com',
            'password_hash' => bcrypt('abc123'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap]);

        return [$unitId, $profId];
    }

    public function test_centang_default_pakai_pengaturan(): void
    {
        [$unitId, $profId] = $this->siap();
        simpan_pengaturan('password_default_pegawai', 'rahasia-default');

        $this->post('admin/pegawai/simpan', [
            'nama_lengkap' => 'Pegawai Baru',
            'email' => 'pegawai.baru@example.com',
            'unit_kerja_id' => $unitId,
            'profesi_id' => $profId,
            'password_default' => '1',
            'jabatan_kategori' => 'Staf/Pelaksana',
            'posisi' => 'Staf',
            'status' => 'aktif',
        ])->assertRedirect('admin/pegawai');

        $u = User::where('email', 'pegawai.baru@example.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue(Hash::check('rahasia-default', $u->password_hash));
    }

    public function test_pegawai_baru_selalu_memiliki_status_akun_aktif(): void
    {
        [$unitId, $profId] = $this->siap();

        $this->post('admin/pegawai/simpan', [
            'nama_lengkap' => 'Pegawai Aktif Otomatis',
            'email' => 'pegawai.aktif@example.com',
            'unit_kerja_id' => $unitId,
            'profesi_id' => $profId,
            'password_default' => '1',
            'jabatan_kategori' => 'Staf/Pelaksana',
            'posisi' => 'Staf',
            'status' => 'nonaktif',
        ])->assertRedirect('admin/pegawai');

        $this->assertDatabaseHas('users', [
            'email' => 'pegawai.aktif@example.com',
            'status' => 'aktif',
        ]);
    }

    public function test_form_pegawai_tidak_menampilkan_pilihan_status_akun(): void
    {
        $this->siap();

        $this->get('admin/pegawai/form')
            ->assertOk()
            ->assertSee('Status akun pegawai baru otomatis Aktif.')
            ->assertDontSee('<select name="status">', false);
    }

    public function test_edit_tanpa_centang_tidak_mengubah_password(): void
    {
        [$unitId, $profId] = $this->siap();
        $u = User::create([
            'nama_lengkap' => 'Pegawai Lama',
            'email' => 'pegawai.lama@example.com',
            'password_hash' => bcrypt('tetap-sama'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        $this->post('admin/pegawai/simpan', [
            'id' => (int) $u->id,
            'nama_lengkap' => 'Pegawai Lama',
            'email' => 'pegawai.lama@example.com',
            'unit_kerja_id' => $unitId,
            'profesi_id' => $profId,
            'jabatan_kategori' => 'Staf/Pelaksana',
            'posisi' => 'Staf',
            'status' => 'aktif',
        ])->assertRedirect('admin/pegawai');

        $u->refresh();
        $this->assertTrue(Hash::check('tetap-sama', $u->password_hash));
    }

    public function test_pengaturan_password_default_bisa_disimpan(): void
    {
        $this->siap();

        $this->post('admin/pengaturan', [
            'nama_instansi' => 'RSUD Merauke',
            'lokasi_lat' => '-8.4991120',
            'lokasi_lng' => '140.4049840',
            'radius_meter' => '100',
            'toleransi_menit' => '5',
            'batas_awal_absen_menit' => '60',
            'batas_ubah_jadwal_jam' => '1',
            'batas_pengajuan_lembur_jam' => '2',
            'maks_lembur_per_hari_jam' => '4',
            'toleransi_lembur_menit' => '5',
            'password_default_pegawai' => 'ganti-password',
        ])->assertRedirect('admin/pengaturan');

        $this->assertSame('ganti-password', pengaturan('password_default_pegawai'));
    }

    public function test_pengaturan_password_default_minimal_enam_karakter(): void
    {
        $this->siap();

        $this->post('admin/pengaturan', [
            'nama_instansi' => 'RSUD Merauke',
            'lokasi_lat' => '-8.4991120',
            'lokasi_lng' => '140.4049840',
            'radius_meter' => '100',
            'toleransi_menit' => '5',
            'batas_awal_absen_menit' => '60',
            'batas_ubah_jadwal_jam' => '1',
            'batas_pengajuan_lembur_jam' => '2',
            'maks_lembur_per_hari_jam' => '4',
            'toleransi_lembur_menit' => '5',
            'password_default_pegawai' => 'abc',
        ])->assertSessionHas('error');

        $this->assertSame('pegawai123', pengaturan('password_default_pegawai', 'pegawai123'));
    }

    public function test_pegawai_baru_ketik_password_sendiri(): void
    {
        [$unitId, $profId] = $this->siap();

        $this->post('admin/pegawai/simpan', [
            'nama_lengkap' => 'Pegawai Custom',
            'email' => 'pegawai.custom@example.com',
            'unit_kerja_id' => $unitId,
            'profesi_id' => $profId,
            'password' => 'rahasia-custom',
            'jabatan_kategori' => 'Staf/Pelaksana',
            'posisi' => 'Staf',
            'status' => 'aktif',
        ])->assertRedirect('admin/pegawai');

        $u = User::where('email', 'pegawai.custom@example.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue(Hash::check('rahasia-custom', $u->password_hash));
    }
}