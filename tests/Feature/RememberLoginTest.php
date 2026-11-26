<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordService;
use App\Services\RememberLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Ingat Saya',
            'email' => uniqid('ingat_', true).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    private function loginWeb(User $user, bool $remember = true): \Illuminate\Testing\TestResponse
    {
        $captchaToken = 'captcha-'.bin2hex(random_bytes(4));

        $payload = [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
            'captcha_token' => $captchaToken,
            'captcha' => 'abcde',
        ];
        if ($remember) {
            $payload['remember'] = '1';
        }

        return $this->withSession([
            'login_form_started' => time() - 5,
            'captcha_'.$captchaToken => 'ABCDE',
        ])->post('/login', $payload);
    }

    private function ambilCookie(\Illuminate\Testing\TestResponse $respons, string $nama): ?Cookie
    {
        return collect($respons->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === $nama);
    }

    public function test_login_ingat_saya_membuat_sesi_berumur_satu_hari(): void
    {
        $user = $this->buatUser(['email' => 'ingat.saya@contoh.test']);

        $respons = $this->loginWeb($user, true);

        $respons->assertRedirect('/dashboard')
            ->assertSessionHas('uid', $user->id)
            ->assertSessionHas('remember_login_until');

        $sessionCookie = $this->ambilCookie($respons, config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $sisaCookie = $sessionCookie->getExpiresTime() - time();
        $this->assertGreaterThan(86390, $sisaCookie);
        $this->assertLessThanOrEqual(86400, $sisaCookie);

        $rememberCookie = $this->ambilCookie($respons, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($rememberCookie);
        $this->assertTrue($rememberCookie->isHttpOnly());
        $this->assertGreaterThan(86390, $rememberCookie->getExpiresTime() - time());

        Route::get('/_test/remember-session', fn () => response()->json([
            'lifetime' => config('session.lifetime'),
        ]))->middleware(['web', 'auth']);

        $responsLanjutan = $this->getJson('/_test/remember-session')->assertOk();
        $this->assertGreaterThanOrEqual(1439, $responsLanjutan->json('lifetime'));
        $cookieLanjutan = $this->ambilCookie($responsLanjutan, config('session.cookie'));
        $this->assertNotNull($cookieLanjutan);
        $this->assertLessThanOrEqual($sessionCookie->getExpiresTime(), $cookieLanjutan->getExpiresTime());
    }

    public function test_admin_ingat_saya_mempertahankan_umur_sesi_dan_pulih_setelah_sesi_habis(): void
    {
        $admin = $this->buatUser([
            'nama_lengkap' => 'Admin Ingat Saya',
            'email' => 'admin.ingat@contoh.test',
            'role' => 'admin',
        ]);

        $responsLogin = $this->loginWeb($admin, true);
        $responsLogin->assertRedirect('/admin');

        $rememberCookie = $this->ambilCookie($responsLogin, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($rememberCookie);

        Route::get('/_test/remember-admin', fn () => response()->json([
            'uid' => session('uid'),
            'role' => session('role'),
            'lifetime' => config('session.lifetime'),
        ]))->middleware(['web', 'admin']);

        // 1. Saat sesi masih ada, rute admin tetap menjaga umur sesi ~24 jam
        $this->getJson('/_test/remember-admin')
            ->assertOk()
            ->assertJsonPath('uid', $admin->id)
            ->assertJsonPath('role', 'admin');

        // 2. Simulasikan sesi database habis / browser dibuka ulang (session kosong, hanya bawa cookie remember_login)
        $this->flushSession();
        $pulih = $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $rememberCookie->getValue())
            ->withCredentials()
            ->getJson('/_test/remember-admin');

        $pulih->assertOk()
            ->assertJsonPath('uid', $admin->id)
            ->assertJsonPath('role', 'admin');
        $this->assertGreaterThanOrEqual(1439, $pulih->json('lifetime'));
    }

    public function test_membuka_login_atau_beranda_otomatis_masuk_saat_sesi_habis_tapi_ingat_saya_masih_aktif(): void
    {
        $user = $this->buatUser(['role' => 'pegawai']);
        $responsLogin = $this->loginWeb($user, true);
        $rememberCookie = $this->ambilCookie($responsLogin, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($rememberCookie);

        // Simulasikan sesi Laravel sudah kedaluwarsa setelah > 2 jam
        $this->flushSession();

        $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $rememberCookie->getValue())
            ->withCredentials()
            ->get('/login')
            ->assertRedirect('dashboard')
            ->assertSessionHas('uid', $user->id);
    }

    public function test_logout_mencabut_cookie_ingat_saya_sehingga_tidak_bisa_dipakai_ulang(): void
    {
        $user = $this->buatUser();
        $responsLogin = $this->loginWeb($user, true);
        $rememberCookie = $this->ambilCookie($responsLogin, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($rememberCookie);

        $responsLogout = $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $rememberCookie->getValue())
            ->withCredentials()
            ->get('/logout');
        $responsLogout->assertRedirect('login');

        // Cookie remember_login lama yang dicoba dipakai ulang setelah logout harus ditolak
        $this->flushSession();
        Route::get('/_test/remember-after-logout', fn () => response()->json(['ok' => true]))
            ->middleware(['web', 'auth']);

        $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $rememberCookie->getValue())
            ->withCredentials()
            ->getJson('/_test/remember-after-logout')
            ->assertStatus(401);
    }

    public function test_ganti_password_dan_nonaktif_membatalkan_ingat_saya(): void
    {
        $user = $this->buatUser();
        $responsLogin = $this->loginWeb($user, true);
        $rememberCookie = $this->ambilCookie($responsLogin, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($rememberCookie);

        // Ganti password -> password_hash berubah & cache cabut user terisi
        $user->forceFill(['password_hash' => bcrypt('password-baru-kuat')])->save();
        app(PasswordService::class)->tandaiDiubah($user, 'uji', false);

        $this->flushSession();
        Route::get('/_test/remember-after-pwd', fn () => response()->json(['ok' => true]))
            ->middleware(['web', 'auth']);

        $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $rememberCookie->getValue())
            ->withCredentials()
            ->getJson('/_test/remember-after-pwd')
            ->assertStatus(401);

        // User baru dinonaktifkan -> cookie remember_login ditolak
        $user2 = $this->buatUser();
        $respons2 = $this->loginWeb($user2, true);
        $cookie2 = $this->ambilCookie($respons2, RememberLoginService::COOKIE_NAME);
        $this->assertNotNull($cookie2);

        $user2->update(['status' => 'nonaktif']);
        $this->flushSession();

        $this->withUnencryptedCookie(RememberLoginService::COOKIE_NAME, (string) $cookie2->getValue())
            ->withCredentials()
            ->getJson('/_test/remember-after-pwd')
            ->assertStatus(401);
    }

    public function test_login_tanpa_ingat_saya_tidak_menerbitkan_cookie_ingat_saya(): void
    {
        $user = $this->buatUser();
        $respons = $this->loginWeb($user, false);

        $respons->assertRedirect('/dashboard')
            ->assertSessionHas('uid', $user->id)
            ->assertSessionMissing('remember_login_until');

        $rememberCookie = $this->ambilCookie($respons, RememberLoginService::COOKIE_NAME);
        $this->assertTrue($rememberCookie === null || $rememberCookie->getExpiresTime() <= time());
    }
}
