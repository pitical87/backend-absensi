<?php

namespace Tests\Feature;

use App\Models\AtasanLangsung;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Atasan Langsung: pencarian asinkron, filter sudah/belum diatur,
 * pagination, dan pengaturan atasan sekaligus untuk beberapa pegawai.
 */
class AtasanLangsungTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function admin(): User
    {
        $admin = $this->pegawai('Admin Uji', 'admin');

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);

        return $admin;
    }

    private function pegawai(string $nama, string $role = 'pegawai'): User
    {
        return User::create([
            'nama_lengkap' => $nama,
            'email' => strtolower(str_replace(' ', '.', $nama)).'.'.(++self::$urut).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => $role,
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
    }

    // ── DAFTAR & FILTER ───────────────────────────────────────────────

    public function test_halaman_memuat_daftar_pegawai_bukan_admin(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $this->pegawai('Ani Lestari');

        $this->get(route('admin.atasan_langsung.index'))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Ani Lestari')
            ->assertSee('data-page');

        $this->assertFalse(AtasanLangsung::exists());
    }

    public function test_pencarian_menyaring_nama_email_dan_unit(): void
    {
        $this->admin();
        $this->pegawai('Budi Santoso');
        $this->pegawai('Ani Lestari');

        $this->getJson(route('admin.atasan_langsung.data').'?q=Budi')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total', 1)
            ->assertSee('Budi Santoso');

        $this->getJson(route('admin.atasan_langsung.data').'?q=@contoh.test')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_filter_sudah_dan_belum_diatur(): void
    {
        $this->admin();
        $sudah = $this->pegawai('Budi Santoso');
        $belum = $this->pegawai('Ani Lestari');
        $atasan = $this->pegawai('Kasir');

        AtasanLangsung::create(['user_id' => $sudah->id, 'atasan_id' => $atasan->id]);

        $jsonBelum = $this->getJson(route('admin.atasan_langsung.data').'?status=belum')->assertOk();
        $jsonBelum->assertJsonPath('total', 2);
        $jsonBelum->assertSee('Ani Lestari');
        $jsonBelum->assertDontSee('Budi Santoso');

        $jsonSudah = $this->getJson(route('admin.atasan_langsung.data').'?status=sudah')->assertOk();
        $jsonSudah->assertJsonPath('total', 1);
        $jsonSudah->assertSee('Budi Santoso');
        $jsonSudah->assertSee('Kasir');

        $semua = $this->getJson(route('admin.atasan_langsung.data'))->assertOk();
        $semua->assertJsonPath('total', 3);
        $semua->assertJsonPath('statistik.total', 3);
        $semua->assertJsonPath('statistik.sudah', 1);
        $semua->assertJsonPath('statistik.belum', 2);
    }

    public function test_pagination_memotong_pegawai_per_halaman(): void
    {
        $this->admin();
        for ($i = 1; $i <= 18; $i++) {
            $this->pegawai('Pegawai '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $hal1 = $this->getJson(route('admin.atasan_langsung.data'))->assertOk();
        $hal1->assertJsonPath('total', 18)
            ->assertJsonPath('halaman', 1)
            ->assertJsonPath('totalHal', 2);

        $hal2 = $this->getJson(route('admin.atasan_langsung.data').'?page=2')->assertOk();
        $hal2->assertJsonPath('halaman', 2)
            ->assertJsonPath('dari', 16)
            ->assertJsonPath('sampai', 18);
    }

    public function test_pencarian_kandidat_atasan_dibatasi_dan_tidak_mencakup_admin(): void
    {
        $this->admin();
        $this->pegawai('Budi Santoso');
        $this->pegawai('Ani Lestari');

        $this->getJson(route('admin.atasan_langsung.pilihan').'?q=Budi')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total', 1)
            ->assertSee('Budi Santoso')
            ->assertDontSee('Ani Lestari');
    }

    // ── SIMPAN SATU PEGAWAI ───────────────────────────────────────────

    public function test_atur_atasan_untuk_satu_pegawai(): void
    {
        $this->admin();
        $bawahan = $this->pegawai('Budi Santoso');
        $atasan = $this->pegawai('Kasir');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_id' => $bawahan->id,
            'atasan' => [$atasan->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $bawahan->id, 'atasan_id' => $atasan->id]);
    }

    // ── SIMPAN BANYAK PEGAWAI ─────────────────────────────────────────

    public function test_satu_atasan_untuk_beberapa_pegawai_sekaligus(): void
    {
        $this->admin();
        $atasan = $this->pegawai('Kasir');
        $bawahan = collect([
            $this->pegawai('Budi Santoso'),
            $this->pegawai('Ani Lestari'),
            $this->pegawai('Citra Dewi'),
        ]);

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => $bawahan->pluck('id')->all(),
            'atasan' => [$atasan->id],
            'mode' => 'ganti',
        ])->assertRedirect()->assertSessionHas('success');

        foreach ($bawahan as $p) {
            $this->assertDatabaseHas('atasan_langsung', ['user_id' => $p->id, 'atasan_id' => $atasan->id]);
        }
    }

    public function test_mode_ganti_menimpa_atasan_lama(): void
    {
        $this->admin();
        $bawahan = $this->pegawai('Budi Santoso');
        $lama = $this->pegawai('Atasan Lama');
        $baru = $this->pegawai('Atasan Baru');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_id' => $bawahan->id,
            'atasan' => [$lama->id],
        ])->assertRedirect();

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_id' => $bawahan->id,
            'atasan' => [$baru->id],
            'mode' => 'ganti',
        ])->assertRedirect();

        $this->assertDatabaseMissing('atasan_langsung', ['user_id' => $bawahan->id, 'atasan_id' => $lama->id]);
        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $bawahan->id, 'atasan_id' => $baru->id]);
    }

    public function test_mode_tambah_tidak_menghapus_atasan_lama(): void
    {
        $this->admin();
        $bawahan = $this->pegawai('Budi Santoso');
        $lama = $this->pegawai('Atasan Lama');
        $tambahan = $this->pegawai('Atasan Tambahan');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_id' => $bawahan->id,
            'atasan' => [$lama->id],
        ])->assertRedirect();

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => [$bawahan->id],
            'atasan' => [$tambahan->id],
            'mode' => 'tambah',
        ])->assertRedirect();

        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $bawahan->id, 'atasan_id' => $lama->id]);
        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $bawahan->id, 'atasan_id' => $tambahan->id]);
    }

    public function test_atasan_diri_sendiri_dibuang_pada_setiap_pegawai(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $dua = $this->pegawai('Ani Lestari');

        // Satu sama lain boleh saling menjadi atasan, tapi tidak diri sendiri.
        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => [$satu->id, $dua->id],
            'atasan' => [$satu->id, $dua->id],
            'mode' => 'ganti',
        ])->assertRedirect();

        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $satu->id, 'atasan_id' => $dua->id]);
        $this->assertDatabaseHas('atasan_langsung', ['user_id' => $dua->id, 'atasan_id' => $satu->id]);
        $this->assertDatabaseMissing('atasan_langsung', ['user_id' => $satu->id, 'atasan_id' => $satu->id]);
        $this->assertDatabaseMissing('atasan_langsung', ['user_id' => $dua->id, 'atasan_id' => $dua->id]);
    }

    public function test_admin_tidak_bisa_dijadikan_atasan(): void
    {
        $this->admin();
        $bawahan = $this->pegawai('Budi Santoso');
        $adminLain = $this->pegawai('Admin Lain', 'admin');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => [$bawahan->id],
            'atasan' => [$adminLain->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('atasan_langsung', 0);
    }

    public function test_tanpa_atasan_ditolak(): void
    {
        $this->admin();
        $bawahan = $this->pegawai('Budi Santoso');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => [$bawahan->id],
            'atasan' => [],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('atasan_langsung', 0);
    }

    public function test_tanpa_pegawai_terpilih_ditolak(): void
    {
        $this->admin();
        $atasan = $this->pegawai('Kasir');

        $this->post(route('admin.atasan_langsung.aksi'), [
            'user_ids' => [],
            'atasan' => [$atasan->id],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('atasan_langsung', 0);
    }

    public function test_halaman_tertutup_untuk_bukan_admin(): void
    {
        $this->pegawai('Pegawai Biasa');

        $this->get(route('admin.atasan_langsung.index'))->assertRedirect();
    }
}
