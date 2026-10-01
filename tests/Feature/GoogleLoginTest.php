<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleLoginService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $kid = 'kunci-uji-1';

    private $privateKey;

    private array $rsaDetail;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-web.apps.googleusercontent.com',
            'services.google.client_secret' => 'rahasia-uji',
            'services.google.redirect' => 'https://contoh.test/auth/google/callback',
            'services.google.mobile_client_ids' => ['client-react.apps.googleusercontent.com'],
            'services.google.hd' => null,
        ]);
        Cache::clear();

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $this->privateKey);
        $this->rsaDetail = openssl_pkey_get_details($res)['rsa'];
    }

    /** User uji: tidak memakai akun sungguhan. */
    private function buatUser(array $atribut = []): User
    {
        static $urut = 0;
        $urut++;

        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji '.$urut,
            'email' => 'pegawai.uji'.$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => null,
        ], $atribut));
    }

    private function akunGoogle(string $email, bool $terverifikasi = true, string $sub = 'sub-1', ?string $hd = null): SocialiteUser
    {
        $mentah = ['email_verified' => $terverifikasi, 'sub' => $sub];
        if ($hd !== null) {
            $mentah['hd'] = $hd;
        }

        return new class($email, $sub, $mentah) implements SocialiteUser
        {
            public function __construct(private string $email, private string $sub, private array $mentah) {}

            public function getId()
            {
                return $this->sub;
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return 'Nama Dari Google';
            }

            public function getEmail()
            {
                return $this->email;
            }

            public function getAvatar()
            {
                return null;
            }

            public function getRaw()
            {
                return $this->mentah + ['email' => $this->email];
            }
        };
    }

    private function idToken(array $klaim, ?string $kid = null, $privateKey = null): string
    {
        $klaim += [
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-react.apps.googleusercontent.com',
            'sub' => 'sub-1',
            'email' => 'pegawai.uji1@contoh.test',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        return JWT::encode($klaim, $privateKey ?? $this->privateKey, 'RS256', $kid ?? $this->kid);
    }

    private function fakesJwks(): void
    {
        $key = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $this->kid,
            'n' => rtrim(strtr(base64_encode($this->rsaDetail['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($this->rsaDetail['e']), '+/', '-_'), '='),
        ];

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$key]]),
        ]);
    }

    private function service(): GoogleLoginService
    {
        return app(GoogleLoginService::class);
    }

    // ---- Kebijakan akun ----

    public function test_pegawai_terdaftar_login_berhasil_dan_email_terverifikasi_otomatis(): void
    {
        $user = $this->buatUser();
        $email = $user->email;

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($email));

        $this->assertSame(200, $hasil['kode']);
        $this->assertNotNull($hasil['user']);
        $this->assertEquals($user->id, $hasil['user']->id);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at, 'email_verified_at harus terisi otomatis');
        $this->assertSame('sub-1', $user->google_sub);

        $this->assertDatabaseHas('aktivitas_log', ['aksi' => 'Verifikasi Email (Google)']);
    }

    public function test_email_belum_terdaftar_ditolak(): void
    {
        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle('tidak.terdaftar@contoh.test'));

        $this->assertSame(403, $hasil['kode']);
        $this->assertNull($hasil['user']);
        $this->assertStringContainsString('belum terdaftar', (string) $hasil['pesan']);
        $this->assertDatabaseHas('aktivitas_log', ['aksi' => 'Login Google Ditolak']);
    }

    public function test_admin_ditolak(): void
    {
        $user = $this->buatUser(['role' => 'admin']);

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($user->email));

        $this->assertSame(403, $hasil['kode']);
        $this->assertStringContainsString('hanya untuk pegawai', (string) $hasil['pesan']);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $user = $this->buatUser(['status' => 'nonaktif']);

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($user->email));

        $this->assertSame(403, $hasil['kode']);
        $this->assertStringContainsString('dinonaktifkan', (string) $hasil['pesan']);
    }

    public function test_email_belum_terverifikasi_di_google_ditolak(): void
    {
        $user = $this->buatUser();

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($user->email, false));

        $this->assertSame(403, $hasil['kode']);
        $this->assertStringContainsString('belum terverifikasi', (string) $hasil['pesan']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_sub_google_yang_berubah_ditolak(): void
    {
        $user = $this->buatUser(['google_sub' => 'sub-lama']);
        $user->forceFill(['email_verified_at' => now()])->save();

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($user->email, true, 'sub-baru'));

        $this->assertSame(403, $hasil['kode']);
        $this->assertStringContainsString('tidak lagi terhubung', (string) $hasil['pesan']);
    }

    public function test_login_ulang_dengan_sub_yang_sama_berhasil(): void
    {
        $user = $this->buatUser(['google_sub' => 'sub-sama']);

        $hasil = $this->service()->masukDenganSocialite($this->akunGoogle($user->email, true, 'sub-sama'));

        $this->assertSame(200, $hasil['kode']);
    }

    public function test_domain_google_dibatasi_bila_google_hd_diisi(): void
    {
        config(['services.google.hd' => 'rsud-merauke.id']);
        $user = $this->buatUser();

        $luar = $this->service()->masukDenganSocialite($this->akunGoogle($user->email, true, 'sub-x', 'gmail.com'));
        $this->assertSame(403, $luar['kode']);

        $dalam = $this->service()->masukDenganSocialite($this->akunGoogle($user->email, true, 'sub-x', 'rsud-merauke.id'));
        $this->assertSame(200, $dalam['kode']);
    }

    // ---- Verifikasi id_token (JWT) ----

    public function test_id_token_valid_diterima(): void
    {
        $this->fakesJwks();
        $user = $this->buatUser();

        $hasil = $this->service()->masukDenganIdToken($this->idToken(['email' => $user->email]));

        $this->assertSame(200, $hasil['kode']);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_id_token_audience_asing_ditolak(): void
    {
        $this->fakesJwks();
        $this->buatUser();

        $hasil = $this->service()->masukDenganIdToken($this->idToken([
            'aud' => 'client-lain.apps.googleusercontent.com',
        ]));

        $this->assertSame(401, $hasil['kode']);
        $this->assertStringContainsString('diterbitkan untuk aplikasi ini', (string) $hasil['pesan']);
    }

    public function test_id_token_kedaluwarsa_ditolak(): void
    {
        $this->fakesJwks();
        $this->buatUser();

        $hasil = $this->service()->masukDenganIdToken($this->idToken(['exp' => time() - 600]));

        $this->assertSame(401, $hasil['kode']);
        $this->assertStringContainsString('kedaluwarsa', (string) $hasil['pesan']);
    }

    public function test_id_token_issuer_asing_ditolak(): void
    {
        $this->fakesJwks();
        $this->buatUser();

        $hasil = $this->service()->masukDenganIdToken($this->idToken(['iss' => 'https://penyerang.example']));

        $this->assertSame(401, $hasil['kode']);
    }

    public function test_id_token_tanda_tangan_palsu_ditolak(): void
    {
        $this->fakesJwks();
        $this->buatUser();

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $kunciPalsu);

        $hasil = $this->service()->masukDenganIdToken($this->idToken([], null, $kunciPalsu));

        $this->assertSame(401, $hasil['kode']);
        $this->assertStringContainsString('tidak valid', (string) $hasil['pesan']);
    }

    public function test_id_token_rusak_ditolak(): void
    {
        $this->fakesJwks();

        $hasil = $this->service()->masukDenganIdToken('bukan.token.jwt');

        $this->assertSame(401, $hasil['kode']);
    }

    public function test_kunci_google_tidak_bisa_dimuat_memberi_503(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([], 500),
        ]);

        $hasil = $this->service()->masukDenganIdToken($this->idToken([]));

        $this->assertSame(503, $hasil['kode']);
    }

    // ---- Endpoint API ----

    public function test_endpoint_mobile_tanpa_id_token_memberi_422(): void
    {
        $this->postJson('/api/mobile/login/google', [])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }

    public function test_endpoint_mobile_dengan_id_token_sampah_memberi_401(): void
    {
        $this->fakesJwks();

        $this->postJson('/api/mobile/login/google', ['id_token' => 'sampah'])
            ->assertStatus(401)
            ->assertJsonPath('sukses', false);
    }

    public function test_endpoint_mobile_terdaftar_menerbitkan_token_dan_cookie(): void
    {
        $this->fakesJwks();
        $user = $this->buatUser();

        $respons = $this->postJson('/api/mobile/login/google', [
            'id_token' => $this->idToken(['email' => $user->email]),
        ]);

        $respons->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['sukses', 'user', 'lokasi' => ['lat', 'lng', 'radius']]);

        $token = $respons->headers->getCookies()[0] ?? null;
        $this->assertNotNull($token);
        $this->assertSame('auth_token', $token->getName());
        $this->assertTrue($token->isHttpOnly());

        $this->assertDatabaseHas('api_tokens', ['user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_endpoint_mobile_admin_memberi_403(): void
    {
        $this->fakesJwks();
        $admin = $this->buatUser(['role' => 'admin']);

        $this->postJson('/api/mobile/login/google', [
            'id_token' => $this->idToken(['email' => $admin->email]),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('api_tokens', ['user_id' => $admin->id]);
    }

    public function test_endpoint_mobile_tidak_membuat_akun_baru(): void
    {
        $this->fakesJwks();
        $jumlahAwal = User::count();

        $this->postJson('/api/mobile/login/google', [
            'id_token' => $this->idToken(['email' => 'entah@contoh.test']),
        ])->assertStatus(403);

        $this->assertSame($jumlahAwal, User::count());
    }

    // ---- Endpoint web ----

    public function test_tombol_google_disembunyikan_bila_kredensial_kosong(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('auth/google')
            ->assertDontSee('Sign in with Google');
    }

    public function test_tombol_google_muncul_bila_kredensial_ada(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('auth/google')
            ->assertSee('Sign in with Google');
    }

    public function test_redirect_google_mengarah_ke_google(): void
    {
        $respons = $this->get('/auth/google');

        $respons->assertRedirectContains('accounts.google.com');
    }

    public function test_redirect_google_tanpa_kredensial_kembali_ke_login(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/auth/google')
            ->assertRedirect(route('login'))
            ->assertSessionHas('galat');
    }

    public function test_callback_ditolak_menampilkan_pesan_di_halaman_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirect(route('login'))
            ->assertSessionHas('galat');
    }

    public function test_pesan_galat_google_muncul_di_halaman_login(): void
    {
        $this->withSession(['galat' => 'Email ini belum terdaftar di sistem.'])
            ->get('/login')
            ->assertOk()
            ->assertSee('Email ini belum terdaftar di sistem.');
    }

    public function test_client_id_aplikasi_kosong_tetap_menerima_client_id_web(): void
    {
        config(['services.google.mobile_client_ids' => []]);
        $service = $this->service();

        $this->assertSame(
            ['client-web.apps.googleusercontent.com'],
            $service->clientIds()
        );
        $this->assertTrue($service->tersedia());
    }

    public function test_client_id_aplikasi_ditambahkan_ke_daftar(): void
    {
        config(['services.google.mobile_client_ids' => ['client-react.apps.googleusercontent.com']]);
        $ids = $this->service()->clientIds();

        $this->assertContains('client-react.apps.googleusercontent.com', $ids);
        $this->assertContains('client-web.apps.googleusercontent.com', $ids);
    }

    public function test_google_tidak_tersedia_bila_client_id_kosong(): void
    {
        config(['services.google.client_id' => null, 'services.google.mobile_client_ids' => []]);

        $this->assertFalse($this->service()->tersedia());
        $this->get('/auth/google')
            ->assertRedirect(route('login'))
            ->assertSessionHas('galat');
    }

    /** redirect_uri yang benar-benar dikirim ke Google pada respons /auth/google. */
    private function redirectUriTerkirim(string $alamat = 'http://localhost:8000/auth/google'): ?string
    {
        $respons = $this->get($alamat);
        $lokasi = (string) $respons->headers->get('Location');

        if ($lokasi === '') {
            return null;
        }

        parse_str((string) parse_url($lokasi, PHP_URL_QUERY), $query);

        return $query['redirect_uri'] ?? null;
    }

    public function test_host_loopback_ikuti_host_browser(): void
    {
        config(['services.google.redirect' => 'http://localhost:8000/auth/google/callback']);

        $this->assertSame(
            'http://127.0.0.1:8000/auth/google/callback',
            $this->redirectUriTerkirim('http://127.0.0.1:8000/auth/google')
        );
    }

    public function test_host_loopback_sama_tidak_diubah(): void
    {
        config(['services.google.redirect' => 'http://localhost:8000/auth/google/callback']);

        $this->assertSame(
            'http://localhost:8000/auth/google/callback',
            $this->redirectUriTerkirim('http://localhost:8000/auth/google')
        );
    }

    public function test_host_produksi_tidak_pernah_ikut_host_browser(): void
    {
        config(['services.google.redirect' => 'https://rsud-merauke.id/auth/google/callback']);

        $this->assertSame(
            'https://rsud-merauke.id/auth/google/callback',
            $this->redirectUriTerkirim('http://127.0.0.1:8000/auth/google')
        );
    }

    public function test_callback_dengan_state_salah_menyarankan_host_konsisten(): void
    {
        config(['services.google.redirect' => 'http://127.0.0.1:8000/auth/google/callback']);

        $this->get('http://127.0.0.1:8000/auth/google/callback?code=palsu&state=ngawur')
            ->assertRedirect(route('login'))
            ->assertSessionHas('galat');

        $pesan = (string) session('galat');
        $this->assertStringContainsString('GOOGLE_REDIRECT_URI', $pesan);
    }
}
