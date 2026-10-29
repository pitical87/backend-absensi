<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_ingat_saya_membuat_sesi_berumur_satu_hari(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Pegawai Ingat Saya',
            'email' => 'ingat.saya@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        $captchaToken = 'captcha-ingat-saya';

        $respons = $this->withSession([
            'login_form_started' => time() - 5,
            'captcha_'.$captchaToken => 'ABCDE',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
            'remember' => '1',
            'captcha_token' => $captchaToken,
            'captcha' => 'abcde',
        ]);

        $respons->assertRedirect('/dashboard')
            ->assertSessionHas('uid', $user->id)
            ->assertSessionHas('remember_login_until');

        $sessionCookie = collect($respons->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $sisaCookie = $sessionCookie->getExpiresTime() - time();
        $this->assertGreaterThan(86390, $sisaCookie);
        $this->assertLessThanOrEqual(86400, $sisaCookie);

        Route::get('/_test/remember-session', fn () => response()->json([
            'lifetime' => config('session.lifetime'),
        ]))->middleware(['web', 'auth']);

        $responsLanjutan = $this->getJson('/_test/remember-session')->assertOk();
        $this->assertGreaterThanOrEqual(1439, $responsLanjutan->json('lifetime'));
        $cookieLanjutan = collect($responsLanjutan->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookieLanjutan);
        $this->assertLessThanOrEqual($sessionCookie->getExpiresTime(), $cookieLanjutan->getExpiresTime());
    }
}
