<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login email-password harus tetap berperilaku sama setelah logika throttle
 * dan penerbitan token diekstrak ke trait bersama.
 */
class MobileLoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => 'pegawai.uji@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    public function test_login_email_password_terbitkan_token_dan_cookie(): void
    {
        $user = $this->buatUser();

        $respons = $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ]);

        $respons->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['sukses', 'user', 'lokasi' => ['lat', 'lng', 'radius']]);

        $cookie = $respons->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame('auth_token', $cookie->getName());
        $this->assertTrue($cookie->isHttpOnly());

        $this->assertDatabaseHas('api_tokens', ['user_id' => $user->id]);
    }

    public function test_password_salah_memberi_401(): void
    {
        $user = $this->buatUser();

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'salah',
        ])->assertStatus(401)->assertJsonPath('sukses', false);

        $this->assertDatabaseMissing('api_tokens', ['user_id' => $user->id]);
    }

    public function test_akun_nonaktif_memberi_403(): void
    {
        $user = $this->buatUser(['status' => 'nonaktif']);

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertStatus(403);
    }

    public function test_admin_tidak_bisa_login_lewat_endpoint_mobile(): void
    {
        $admin = $this->buatUser(['role' => 'admin']);

        $this->postJson('/api/mobile/login', [
            'email' => $admin->email,
            'password' => 'rahasia-kuat',
        ])->assertStatus(401);
    }

    public function test_kredensial_kosong_memberi_422(): void
    {
        $this->postJson('/api/mobile/login', [])->assertStatus(422);
    }

    /**
     * Lima kali gagal → diblokir, lalu bebas login lagi setelah 10 menit.
     */
    public function test_lima_gagal_diblokir_lalu_bebas_setelah_10_menit(): void
    {
        $user = $this->buatUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/mobile/login', [
                'email' => $user->email,
                'password' => 'salah',
            ])->assertStatus(401);
        }

        // Percobaan keenam sudah ditolak sebelum dicek password.
        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertStatus(429)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Terlalu banyak gagal. Coba lagi dalam 10 menit.');

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertStatus(200)
            ->assertJsonPath('sukses', true);
    }

    /**
     * Mencoba terus saat diblokir tidak boleh menambah penundaan: percobaan
     * tidak dicatat selama diblokir, jadi blokir tetap habis pada menit ke-10.
     */
    public function test_percobaan_ulang_saat_diblokir_tidak_memperpanjang_penundaan(): void
    {
        $user = $this->buatUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'salah'])
                ->assertStatus(401);
        }

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'rahasia-kuat'])
                ->assertStatus(429);
        }

        $this->assertSame(5, LoginAttempt::where('sukses', 0)->count());

        $this->travel(10)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'rahasia-kuat'])
            ->assertStatus(200);
    }

    public function test_blokir_hilang_setelah_10_menit_lalu_gagal_baru_mulai_dihitung(): void
    {
        $user = $this->buatUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'salah'])
                ->assertStatus(401);
        }
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertStatus(429);

        $this->travel(11)->minutes();

        // Jendela sudah bersih: satu percobaan salah memberi 401, bukan 429.
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertStatus(401);
    }
}
