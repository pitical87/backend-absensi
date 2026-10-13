<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout admin menyediakan skeleton loading di area konten (di dalam <main>)
 * supaya perpindahan halaman terasa cepat tanpa menyentuh sidebar/navbar.
 */
class LayoutSkeletonTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.skeleton@contoh.test',
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

    public function test_area_konten_memiliki_wrapper_dan_skeleton(): void
    {
        $this->admin();

        $html = $this->get('/admin/pegawai')->assertOk()->getContent();

        $this->assertStringContainsString('id="konten-admin"', $html);
        $this->assertStringContainsString('id="skeleton-admin"', $html);

        // Skeleton berada di dalam <main>, sebelum penutupnya.
        $posMain = strpos($html, '<main');
        $posSkeleton = strpos($html, 'id="skeleton-admin"');
        $posMainTutup = strpos($html, '</main>');
        $this->assertNotFalse($posMain);
        $this->assertNotFalse($posMainTutup);
        $this->assertTrue($posSkeleton > $posMain && $posSkeleton < $posMainTutup);
    }
}
