<?php

namespace Tests\Feature;

use App\Models\Profesi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Master Profesi: daftar asinkron, aksi modal, dan guard hapus. */
class ProfesiMasterTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Profesi',
            'email' => 'admin.profesi.'.(++self::$urut).'@contoh.test',
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

    private function aksi(array $data)
    {
        return $this->postJson(route('admin.profesi.aksi'), $data);
    }

    public function test_halaman_menampilkan_menu_dan_daftar(): void
    {
        $this->admin();
        Profesi::create(['nama' => 'Fisioterapis']);

        $this->get(route('admin.profesi.index'))
            ->assertOk()
            ->assertSee('Master Profesi')
            ->assertSee('Fisioterapis')
            ->assertSee('Jabatan & Posisi');
    }

    public function test_data_json_dan_pencarian(): void
    {
        $this->admin();
        Profesi::create(['nama' => 'Fisioterapis']);
        Profesi::create(['nama' => 'Ners']);

        $semua = $this->getJson(route('admin.profesi.data'))
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertStringContainsString('Fisioterapis', $semua->json('html'));

        $cari = $this->getJson(route('admin.profesi.data', ['q' => 'Ners']))
            ->assertOk();
        $this->assertStringContainsString('Ners', $cari->json('html'));
        $this->assertStringNotContainsString('Fisioterapis', $cari->json('html'));
    }

    public function test_tambah_ubah_dan_hapus_profesi(): void
    {
        $this->admin();

        $this->aksi(['aksi' => 'tambah', 'nama' => 'Analis Laboratorium'])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseHas('profesi', ['nama' => 'Analis Laboratorium']);

        // Ganda ditolak.
        $this->aksi(['aksi' => 'tambah', 'nama' => 'Analis Laboratorium'])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);

        $id = (int) Profesi::where('nama', 'Analis Laboratorium')->value('id');

        $this->aksi(['aksi' => 'ubah', 'id' => $id, 'nama' => 'Analis Klinik'])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseHas('profesi', ['id' => $id, 'nama' => 'Analis Klinik']);

        // Masih dipakai pegawai → ditolak.
        $peg = User::create([
            'nama_lengkap' => 'Pegawai Profesi',
            'email' => 'pegawai.profesi.'.(++self::$urut).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
            'profesi_id' => $id,
        ]);

        $this->aksi(['aksi' => 'hapus', 'id' => $id])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);
        $this->assertDatabaseHas('profesi', ['id' => $id]);

        $peg->delete();
        $this->aksi(['aksi' => 'hapus', 'id' => $id])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseMissing('profesi', ['id' => $id]);
    }
}
