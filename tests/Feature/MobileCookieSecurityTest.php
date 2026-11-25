<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use App\Services\PasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Keamanan',
            'email' => uniqid('sec_', true).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    public function test_login_menyimpan_token_terhash_dan_cookie_secure_di_https(): void
    {
        $user = $this->buatUser();

        $res = $this->postJson('https://absen.rsud-merauke.id/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertOk();

        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'auth_token');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));

        $rawToken = (string) $cookie->getValue();
        $row = ApiToken::where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertNotSame($rawToken, $row->token, 'Token di DB tidak boleh plaintext.');
        $this->assertSame(ApiToken::hashToken($rawToken), $row->token);

        // Token terhash dapat dipakai pada endpoint terproteksi & dihapus saat logout
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $rawToken)
            ->getJson('/api/mobile/me')
            ->assertOk();

        $this->withCredentials()
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withUnencryptedCookie('auth_token', $rawToken)
            ->postJson('/api/mobile/logout')
            ->assertOk();

        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
    }

    public function test_transisi_otomatis_mengupgrade_token_plaintext_lama_ke_sha256(): void
    {
        $user = $this->buatUser();
        $legacyRaw = bin2hex(random_bytes(32));

        $row = ApiToken::create([
            'user_id' => $user->id,
            'token' => $legacyRaw,
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        // Request pertama dengan token plaintext lama tetap lolos
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $legacyRaw)
            ->getJson('/api/mobile/me')
            ->assertOk();

        // Di database kini sudah otomatis ter-upgrade ke SHA-256
        $row->refresh();
        $this->assertSame(ApiToken::hashToken($legacyRaw), $row->token);

        // Request berikutnya tetap valid memakai cookie yang sama
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $legacyRaw)
            ->getJson('/api/mobile/me')
            ->assertOk();
    }

    public function test_csrf_menolak_mutasi_tanpa_header_atau_asal_berbeda(): void
    {
        $user = $this->buatUser();
        $raw = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'token' => ApiToken::hashToken($raw),
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        // Tanpa X-Requested-With -> 403
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $raw)
            ->postJson('/api/mobile/logout')
            ->assertStatus(403);

        // Origin dari subdomain asing -> 403
        $this->withCredentials()
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Origin', 'https://jahat.rsud-merauke.id')
            ->withUnencryptedCookie('auth_token', $raw)
            ->postJson('/api/mobile/logout')
            ->assertStatus(403);

        // Dengan header valid & origin sama -> 200
        $this->withCredentials()
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withHeader('Origin', 'http://localhost')
            ->withUnencryptedCookie('auth_token', $raw)
            ->postJson('/api/mobile/logout')
            ->assertOk();
    }

    public function test_ganti_password_mencabut_semua_token_mobile(): void
    {
        $user = $this->buatUser();
        $raw = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'token' => ApiToken::hashToken($raw),
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        app(PasswordService::class)->tandaiDiubah($user, 'uji');

        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $raw)
            ->getJson('/api/mobile/me')
            ->assertStatus(401);
    }

    public function test_sso_menyimpan_token_terhash_dan_dapat_dipakai_autentikasi(): void
    {
        config(['services.web_absen.url' => 'https://absen.rsud-merauke.id']);
        $user = $this->buatUser();

        $res = $this->withSession(['uid' => $user->id, 'role' => 'pegawai'])
            ->get('/sso-absen')
            ->assertRedirect();

        $target = (string) $res->headers->get('Location');
        parse_str((string) parse_url($target, PHP_URL_QUERY), $qs);
        $rawSsoToken = (string) ($qs['token'] ?? '');

        $this->assertNotSame('', $rawSsoToken);
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $user->id,
            'token' => ApiToken::hashToken($rawSsoToken),
        ]);
        $this->assertDatabaseMissing('api_tokens', [
            'user_id' => $user->id,
            'token' => $rawSsoToken,
        ]);

        // Raw token dari redirect SSO valid saat dipakai sebagai auth_token
        $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $rawSsoToken)
            ->getJson('/api/mobile/me')
            ->assertOk();
    }
}
