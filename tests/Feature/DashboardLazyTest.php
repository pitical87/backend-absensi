<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard dimuat dua tahap: kerangka + skeleton lebih dulu, lalu datanya
 * diambil lewat admin.dashboard.data setelah halaman tampil.
 */
class DashboardLazyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.dashboard@contoh.test',
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

    public function test_halaman_dashboard_hanya_kerangka_dan_skeleton(): void
    {
        $this->admin();

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('id="dashboard-isi"', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('admin\/dashboard\/data', $html);

        // Isi berat belum ada di kerangka.
        $this->assertStringNotContainsString('id="grafik-tren"', $html);
        $this->assertStringNotContainsString('Ketaatan Absen Bulan', $html);
    }

    public function test_endpoint_data_mengembalikan_isi_dashboard(): void
    {
        $this->admin();

        $html = $this->get(route('admin.dashboard.data'))->assertOk()->getContent();

        $this->assertStringContainsString('stat-admin', $html);
        $this->assertStringContainsString('Ketaatan Absen Bulan', $html);
        $this->assertStringContainsString('id="grafik-tren"', $html);
    }

    public function test_endpoint_data_dilindungi_login(): void
    {
        $this->get(route('admin.dashboard.data'))->assertRedirect(route('login'));
    }
}
