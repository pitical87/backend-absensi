<?php

namespace Tests\Feature;

use App\Models\HariLibur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiburTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        return $admin;
    }

    private function libur(int $tahun, string $bulan, string $hari, string $keterangan): HariLibur
    {
        return HariLibur::create([
            'tanggal' => sprintf('%d-%02d-%02d', $tahun, $bulan, $hari),
            'keterangan' => $keterangan,
        ]);
    }

    public function test_halaman_menampilkan_kalender_dan_pencarian(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');
        $this->libur($tahun, 3, 2, 'Cuti Bersama Kantor');

        $this->get(route('admin.libur.index'))
            ->assertOk()
            ->assertSee('data-kategori="tahun"', false)
            ->assertSee('Cuti Bersama Kantor')
            ->assertSee('data-cari', false);
    }

    public function test_holiday_tetap_terisi_otomatis(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');

        $this->getJson(route('admin.libur.data', ['tahun' => $tahun]))->assertOk();

        $this->assertDatabaseHas('hari_libur', ['tanggal' => $tahun.'-01-01']);
        $this->assertDatabaseHas('hari_libur', ['tanggal' => $tahun.'-12-25']);
    }

    public function test_pencarian_menyaring_keterangan_dan_tanggal(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');
        $this->libur($tahun, 3, 2, 'Cuti Bersama Kantor');
        $this->libur($tahun, 4, 3, 'Hari Rayavenant');

        $this->get(route('admin.libur.index', ['q' => 'kantor']))
            ->assertOk()
            ->assertSee('Cuti Bersama Kantor')
            ->assertDontSee('Hari Rayavenant');

        $this->get(route('admin.libur.index', ['q' => '04-03']))
            ->assertOk()
            ->assertSee('Hari Rayavenant')
            ->assertDontSee('Cuti Bersama Kantor');
    }

    public function test_endpoint_data_mengembalikan_tabel_dan_jumlah(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');

        $respons = $this->getJson(route('admin.libur.data', ['tahun' => $tahun, 'q' => 'Ramadan']));
        $respons->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('tahun', $tahun)->assertJsonPath('q', 'Ramadan');
        $this->assertStringContainsString('Tidak ada hari libur pada tahun '.$tahun.' yang cocok dengan', $respons->json('html'));
    }

    public function test_pagination_membatasi_hari_libur(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y') + 3;
        $tetap = count(hari_libur_tetap($tahun));

        for ($i = 1; $i <= 18; $i++) {
            $this->libur($tahun, 3, $i, 'Hari Uji '.$i);
        }

        $respons = $this->getJson(route('admin.libur.data', ['tahun' => $tahun]));
        $respons->assertOk()
            ->assertJsonPath('total', 18 + $tetap)
            ->assertJsonPath('hal', 1)
            ->assertJsonPath('totalHal', 2);
        $this->assertSame(16, substr_count($respons->json('html'), '<tr>'));
        $this->assertStringContainsString('Hari Uji 14', $respons->json('html'));
        $this->assertStringNotContainsString('Hari Uji 18', $respons->json('html'));

        $halaman2 = $this->getJson(route('admin.libur.data', ['tahun' => $tahun, 'hal' => 2]));
        $halaman2->assertOk()->assertJsonPath('hal', 2);
        $this->assertStringContainsString('Hari Uji 18', $halaman2->json('html'));
    }

    public function test_tambah_lewat_json_mengembalikan_tabel_baru(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');

        $respons = $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'tambah',
            'tahun' => $tahun,
            'tanggal' => $tahun.'-05-02',
            'keterangan' => 'Cuti Bersama Kantor',
        ]);

        $respons->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('pesan', 'Hari libur ditambahkan.')
            ->assertJsonPath('tahun', $tahun);

        $this->assertDatabaseHas('hari_libur', ['tanggal' => $tahun.'-05-02', 'keterangan' => 'Cuti Bersama Kantor']);
        $this->assertStringContainsString('Cuti Bersama Kantor', $respons->json('html'));
    }

    public function test_tambah_menolak_tanggal_bentrok_dan_tahun_lain(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');
        $this->libur($tahun, 5, 2, 'Cuti Bersama Kantor');

        $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'tambah', 'tahun' => $tahun, 'tanggal' => $tahun.'-05-02', 'keterangan' => 'Duplikat',
        ])->assertStatus(422)->assertJsonPath('pesan', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');

        $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'tambah', 'tahun' => $tahun, 'tanggal' => ($tahun + 1).'-05-02', 'keterangan' => 'Tahun lain',
        ])->assertStatus(422)->assertJsonPath('pesan', 'Tanggal harus berada pada tahun yang sedang ditampilkan.');

        $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'tambah', 'tahun' => $tahun, 'tanggal' => 'bukan-tanggal', 'keterangan' => 'Salah',
        ])->assertStatus(422)->assertJsonPath('pesan', 'Tanggal dan keterangan wajib diisi.');

        $this->assertDatabaseMissing('hari_libur', ['keterangan' => 'Duplikat']);
        $this->assertDatabaseMissing('hari_libur', ['keterangan' => 'Tahun lain']);
    }

    public function test_ubah_lewat_json(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');
        $libur = $this->libur($tahun, 5, 2, 'Cuti Bersama Kantor');

        $respons = $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'ubah',
            'tahun' => $tahun,
            'id' => $libur->id,
            'tanggal' => $tahun.'-05-03',
            'keterangan' => 'Cuti Bersama Kantor (diperpanjang)',
        ]);

        $respons->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('pesan', 'Hari libur diperbarui.');
        $this->assertDatabaseHas('hari_libur', ['id' => $libur->id, 'tanggal' => $tahun.'-05-03', 'keterangan' => 'Cuti Bersama Kantor (diperpanjang)']);
    }

    public function test_hapus_lewat_json_mengembalikan_tabel_tanpa_baris(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');
        $libur = $this->libur($tahun, 5, 2, 'Cuti Bersama Kantor');

        $respons = $this->postJson(route('admin.libur.aksi'), [
            'aksi' => 'hapus', 'tahun' => $tahun, 'id' => $libur->id,
        ]);

        $respons->assertOk()->assertJsonPath('pesan', 'Hari libur dihapus.');
        $this->assertDatabaseMissing('hari_libur', ['id' => $libur->id]);
        $this->assertStringNotContainsString('Cuti Bersama Kantor', $respons->json('html'));
    }

    public function test_aksi_tidak_dikenal(): void
    {
        $this->admin();

        $this->postJson(route('admin.libur.aksi'), ['aksi' => 'ngawur', 'tahun' => now()->format('Y')])
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Aksi tidak dikenal.');
    }

    public function test_tambah_tanpa_json_tetap_redirect(): void
    {
        $this->admin();
        $tahun = (int) now()->format('Y');

        $this->post(route('admin.libur.aksi'), [
            'aksi' => 'tambah', 'tahun' => $tahun, 'tanggal' => $tahun.'-05-09', 'keterangan' => 'Cuti',
        ])->assertRedirect(route('admin.libur.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('hari_libur', ['tanggal' => $tahun.'-05-09']);
    }

    public function test_halaman_bukan_admin_diarahkan(): void
    {
        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p1@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);
        $this->withSession(['uid' => (int) $pegawai->id, 'role' => 'pegawai', 'nama' => 'Pegawai']);

        $this->get(route('admin.libur.index'))->assertRedirect();
        $this->getJson(route('admin.libur.data'))->assertStatus(302);
    }
}
