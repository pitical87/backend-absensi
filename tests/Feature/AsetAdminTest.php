<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route admin.asset menyajikan public/assets/* dengan header cache jangka
 * panjang + ETag, dan menolak path traversal.
 */
class AsetAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.aset@contoh.test',
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

    public function test_menyajikan_aset_dengan_header_cache_dan_etag(): void
    {
        $this->admin();

        $respons = $this->get(route('admin.asset', ['berkas' => 'img/logo.svg']))->assertOk();

        $respons->assertHeader('ETag');
        $cache = $respons->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=31536000', $cache);
        $this->assertStringContainsString('immutable', $cache);
    }

    public function test_membalas_304_bila_etag_cocok(): void
    {
        $this->admin();

        $etag = $this->get(route('admin.asset', ['berkas' => 'img/logo.svg']))
            ->assertOk()
            ->headers->get('ETag');

        $this->withHeaders(['If-None-Match' => $etag])
            ->get(route('admin.asset', ['berkas' => 'img/logo.svg']))
            ->assertStatus(304);
    }

    public function test_berkas_tidak_ada_mengembalikan_404(): void
    {
        $this->admin();

        $this->get(route('admin.asset', ['berkas' => 'img/tidak-ada.svg']))->assertNotFound();
    }

    public function test_menolak_path_traversal(): void
    {
        $this->admin();

        $this->get('/admin/assets/..%2F..%2F.env')->assertNotFound();
    }

    public function test_tanpa_login_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.asset', ['berkas' => 'img/logo.svg']))
            ->assertRedirect(route('login'));
    }
}
