<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lonceng notifikasi di navbar admin: hanya menampilkan yang belum dibaca,
 * dan menandai terbaca harus muttered lewat aksi admin yang sedang login.
 */
class NotifikasiAdminTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.'.(++self::$urut).'@contoh.test',
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

    public function test_panel_navbar_hanya_menampilkan_notifikasi_belum_dibaca(): void
    {
        $admin = $this->admin();

        $baru = Notifikasi::create([
            'user_id' => $admin->id,
            'isi' => 'Notifikasi baru saja masuk',
            'kategori' => 'password',
        ]);
        Notifikasi::create([
            'user_id' => $admin->id,
            'isi' => 'Notifikasi lama sudah dibaca',
            'is_read' => true,
        ]);

        $this->get('/admin/pegawai')
            ->assertStatus(200)
            ->assertSee('Notifikasi baru saja masuk')
            ->assertDontSee('Notifikasi lama sudah dibaca');
    }

    public function test_tandai_baca_menghilangkan_notifikasi_dari_panel(): void
    {
        $admin = $this->admin();
        $notifikasi = Notifikasi::create([
            'user_id' => $admin->id,
            'isi' => 'Segera ganti password',
        ]);

        $this->post('/admin/notifikasi/'.$notifikasi->id.'/baca')->assertRedirect();

        $this->assertTrue((bool) $notifikasi->fresh()->is_read);

        $this->get('/admin/pegawai')
            ->assertStatus(200)
            ->assertDontSee('Segera ganti password');
    }

    public function test_tandai_baca_melalui_json_mengembalikan_sisa(): void
    {
        $admin = $this->admin();
        $notifikasi = Notifikasi::create(['user_id' => $admin->id, 'isi' => 'Satu']);

        $this->postJson('/admin/notifikasi/'.$notifikasi->id.'/baca')
            ->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('belum_dibaca', 0);
    }

    public function test_tandai_baca_tidak_bisa_menyentuh_notifikasi_admin_lain(): void
    {
        $this->admin();

        $adminLain = User::create([
            'nama_lengkap' => 'Admin Lain',
            'email' => 'admin.lain@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $milikOrangLain = Notifikasi::create([
            'user_id' => $adminLain->id,
            'isi' => 'Notifikasi admin lain',
        ]);

        $this->post('/admin/notifikasi/'.$milikOrangLain->id.'/baca')->assertRedirect();

        $this->assertFalse((bool) $milikOrangLain->fresh()->is_read);
    }

    public function test_tandai_semua_dibaca(): void
    {
        $admin = $this->admin();

        Notifikasi::create(['user_id' => $admin->id, 'isi' => 'Satu']);
        Notifikasi::create(['user_id' => $admin->id, 'isi' => 'Dua']);
        Notifikasi::create(['user_id' => $admin->id, 'isi' => 'Tiga', 'is_read' => true]);

        $this->post('/admin/notifikasi/baca-semua')->assertRedirect();

        $this->assertSame(0, Notifikasi::where('user_id', $admin->id)->belumDibaca()->count());
        $this->assertSame(3, Notifikasi::where('user_id', $admin->id)->count());
    }

    public function test_buka_menandai_terbaca_lalu_mengarah_ke_url_notifikasi(): void
    {
        $admin = $this->admin();
        $notifikasi = Notifikasi::create([
            'user_id' => $admin->id,
            'isi' => 'Ada pengajuan izin',
            'url' => 'admin/izin',
        ]);

        $this->post('/admin/notifikasi/'.$notifikasi->id.'/baca', ['lanjut' => 'admin/izin'])
            ->assertRedirect('admin/izin');

        $this->assertTrue((bool) $notifikasi->fresh()->is_read);
    }

    public function test_tujuan_luar_diarahkan_kembali_ke_admin(): void
    {
        $admin = $this->admin();
        $notifikasi = Notifikasi::create([
            'user_id' => $admin->id,
            'isi' => 'Pancingan',
        ]);

        $this->post('/admin/notifikasi/'.$notifikasi->id.'/baca', ['lanjut' => 'https://contoh.test/phishing'])
            ->assertRedirect(url('admin'));

        $this->post('/admin/notifikasi/'.$notifikasi->id.'/baca', ['lanjut' => '//contoh.test/phishing'])
            ->assertRedirect(url('admin'));
    }

    public function test_admin_belum_login_tidak_bisa_menandai_notifikasi(): void
    {
        $admin = $this->admin();
        $notifikasi = Notifikasi::create(['user_id' => $admin->id, 'isi' => 'Rahasia']);

        // Mulai dari sesi bersih tanpa admin.
        $this->flushSession();

        $this->post('/admin/notifikasi/'.$notifikasi->id.'/baca')->assertRedirect(route('login'));

        $this->assertFalse((bool) $notifikasi->fresh()->is_read);
    }
}
