<?php

namespace Tests\Feature;

use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tab Data Jadwal dimuat lazy (endpoint admin.jadwal.data) dengan pencarian +
 * pagination, dan jadwal per pegawai (tab Per Pegawai) diambil saat baris
 * ditambahkan lewat admin.jadwal.pegawai-jadwal.
 */
class JadwalLazyTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.jadwal@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        return $admin;
    }

    private function pegawai(string $nama): User
    {
        $urut = ++self::$urut;

        return User::create([
            'nama_lengkap' => $nama,
            'nip' => '19800'.(100 + $urut),
            'email' => 'pegawai'.$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
    }

    private function shift(): Shift
    {
        return Shift::create(['kategori' => 'Pagi', 'jam_masuk' => '07:00', 'jam_pulang' => '15:00']);
    }

    private function beriJadwal(User $pegawai, Shift $shift, ?string $tanggal = null): void
    {
        $tanggal = $tanggal ?? now()->startOfMonth()->toDateString();

        JadwalShift::create([
            'user_id' => $pegawai->id,
            'shift_id' => $shift->id,
            'tanggal_berlaku' => $tanggal,
        ]);
    }

    public function test_halaman_jadwal_tidak_memuat_tabel_data_secara_langsung(): void
    {
        $this->admin();
        $shift = $this->shift();
        $this->beriJadwal($this->pegawai('Budi Santoso'), $shift);

        $html = $this->get(route('admin.jadwal.index'))->assertOk()->getContent();

        // Wadah lazy + kotak pencarian ada, isi berat tidak.
        $this->assertStringContainsString('id="data-jadwal-isi"', $html);
        $this->assertStringContainsString('id="cari-jadwal"', $html);
        $this->assertStringContainsString('admin\/jadwal\/data', $html);
        $this->assertStringNotContainsString('class="baris-data"', $html);
        $this->assertStringNotContainsString('Menampilkan', $html);
    }

    public function test_endpoint_data_mengembalikan_baris_dan_pagination(): void
    {
        $this->admin();
        $shift = $this->shift();
        for ($i = 1; $i <= 25; $i++) {
            $this->beriJadwal($this->pegawai(sprintf('Pegawai %02d', $i)), $shift);
        }

        $hal1 = $this->get(route('admin.jadwal.data', ['per' => 20]))
            ->assertOk()
            ->assertSee('class="baris-data"', false)
            ->assertSee('Menampilkan 1–20 dari 25 pegawai');

        $this->assertEquals(20, substr_count($hal1->getContent(), 'class="baris-data"'));

        $hal2 = $this->get(route('admin.jadwal.data', ['per' => 20, 'page' => 2]))
            ->assertOk()
            ->assertSee('Menampilkan 21–25 dari 25 pegawai');

        $this->assertEquals(5, substr_count($hal2->getContent(), 'class="baris-data"'));
    }

    public function test_endpoint_data_menyaring_dengan_pencarian(): void
    {
        $this->admin();
        $shift = $this->shift();
        $this->beriJadwal($this->pegawai('Budi Santoso'), $shift);
        $this->beriJadwal($this->pegawai('Siti Aminah'), $shift);

        $respons = $this->get(route('admin.jadwal.data', ['cari' => 'Budi']))->assertOk();

        $respons->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
        $this->assertEquals(1, substr_count($respons->getContent(), 'class="baris-data"'));
    }

    public function test_endpoint_data_tanpa_jadwal_menampilkan_pesan_kosong(): void
    {
        $this->admin();

        $this->get(route('admin.jadwal.data'))
            ->assertOk()
            ->assertSee('Belum ada jadwal tersimpan pada periode ini.');
    }

    public function test_per_all_mengembalikan_seluruh_hasil(): void
    {
        $this->admin();
        $shift = $this->shift();
        for ($i = 1; $i <= 25; $i++) {
            $this->beriJadwal($this->pegawai(sprintf('Pegawai %02d', $i)), $shift);
        }

        $respons = $this->get(route('admin.jadwal.data', ['per' => 'all']))->assertOk();

        $this->assertEquals(25, substr_count($respons->getContent(), 'class="baris-data"'));
    }

    public function test_endpoint_jadwal_pegawai_mengembalikan_map_tanggal_shift(): void
    {
        $this->admin();
        $shift = $this->shift();
        $pegawai = $this->pegawai('Budi Santoso');
        $tanggal = now()->startOfMonth()->toDateString();
        $this->beriJadwal($pegawai, $shift, $tanggal);

        $this->getJson(route('admin.jadwal.pegawai-jadwal', ['user_id' => $pegawai->id]))
            ->assertOk()
            ->assertJsonPath($tanggal, (int) $shift->id);
    }

    public function test_endpoint_dilindungi_login(): void
    {
        $this->get(route('admin.jadwal.data'))->assertRedirect(route('login'));
        $this->get(route('admin.jadwal.pegawai-jadwal', ['user_id' => 1]))->assertRedirect(route('login'));
    }
}
