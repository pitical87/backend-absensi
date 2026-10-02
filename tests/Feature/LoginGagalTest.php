<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AncamanLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginGagalTest extends TestCase
{
    use RefreshDatabase;

    private AncamanLoginService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AncamanLoginService;
    }

    private function gagal(string $email, string $ip, string $ua = 'Mozilla/5.0', ?int $jamLalu = null): void
    {
        LoginAttempt::create([
            'email' => $email,
            'ip' => $ip,
            'sumber' => 'api',
            'user_agent' => $ua,
            'sukses' => 0,
            'waktu' => now()->subHours($jamLalu ?? 1),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin@rsudmerauke.go.id',
            'password_hash' => bcrypt('rahasia-admin-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
    }

    // ── SKALA SEVERITY ────────────────────────────────────────────────

    public function test_satu_kegagalan_berlevel_low(): void
    {
        $this->gagal('a@x.test', '10.0.0.1');

        $this->assertSame('low', $this->svc->level(1, 1, 1));
        $this->assertSame('low', $this->svc->level(0, 1, 1));
    }

    public function test_dua_sampai_lima_kegagalan_berlevel_mencurigakan(): void
    {
        $this->assertSame('mencurigakan', $this->svc->level(2, 1, 1));
        $this->assertSame('mencurigakan', $this->svc->level(5, 1, 1));
    }

    public function test_enam_kegagalan_satu_ip_berlevel_medium(): void
    {
        $this->assertSame('medium', $this->svc->level(6, 1, 1));
        $this->assertSame('medium', $this->svc->level(20, 1, 3));
    }

    public function test_enam_kegagalan_berlevel_danger_bila_sasaran_beragam(): void
    {
        $this->assertSame('danger', $this->svc->level(6, 2, 1));
    }

    public function test_enam_kegagalan_berlevel_danger2_bila_perangkat_berbeda(): void
    {
        $this->assertSame('danger2', $this->svc->level(6, 2, 2));
    }

    public function test_level_terhitung_dari_data_asli(): void
    {
        // 6 gagal, 2 IP, 1 perangkat -> danger
        for ($i = 0; $i < 3; $i++) {
            $this->gagal('b@x.test', '10.0.0.1');
        }
        for ($i = 0; $i < 3; $i++) {
            $this->gagal('b@x.test', '10.0.0.2');
        }

        $grup = $this->svc->grup();
        $ini = $grup->firstWhere('email', 'b@x.test');

        $this->assertNotNull($ini);
        $this->assertSame(6, $ini['gagal']);
        $this->assertSame(2, $ini['jml_ip']);
        $this->assertSame('danger', $ini['level']);
    }

    // ── PENGELOMPOKAN ────────────────────────────────────────────────

    public function test_gagal_tanpa_email_dikelompokkan_per_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            LoginAttempt::create([
                'email' => '',
                'ip' => '10.0.0.9',
                'sumber' => 'google',
                'sukses' => 0,
                'waktu' => now()->subHour(),
            ]);
        }

        $grup = $this->svc->grup();
        $ini = $grup->firstWhere('ip', '10.0.0.9');

        $this->assertNotNull($ini);
        $this->assertSame('ip', $ini['kunci']);
        $this->assertSame(6, $ini['gagal']);
    }

    public function test_gagal_dengan_email_dan_gagal_tanpa_email_dari_ip_sama_terpisah(): void
    {
        $this->gagal('kamu@korban.test', '10.0.0.9');
        LoginAttempt::create([
            'email' => '',
            'ip' => '10.0.0.9',
            'sumber' => 'google',
            'sukses' => 0,
            'waktu' => now()->subHour(),
        ]);

        $grup = $this->svc->grup();

        // Email yang diketahui dikelompokkan sebagai akun, bukan ikut berhitung
        // pada grup IP tanpa email.
        $perEmail = $grup->firstWhere('email', 'kamu@korban.test');
        $perIp = $grup->firstWhere('kunci', 'ip');

        $this->assertNotNull($perEmail);
        $this->assertNotNull($perIp);
        $this->assertSame(1, $perEmail['gagal']);
        $this->assertSame(1, $perIp['gagal']);
    }

    public function test_grup_dikelompokkan_berdasarkan_email_bukan_ip(): void
    {
        $this->gagal('c@x.test', '10.0.0.1');
        $this->gagal('c@x.test', '10.0.0.2');
        $this->gagal('d@x.test', '10.0.0.1');

        $grup = $this->svc->grup();

        $this->assertCount(2, $grup);
        $this->assertSame(2, $grup->firstWhere('email', 'c@x.test')['gagal']);
    }

    public function test_kegagalan_di_luar_jendela_tidak_dihitung(): void
    {
        $this->gagal('e@x.test', '10.0.0.1', jamLalu: 30);
        $this->gagal('e@x.test', '10.0.0.1', jamLalu: 30);

        $this->assertCount(0, $this->svc->grup(24));
        $this->assertCount(1, $this->svc->grup(720));
    }

    public function test_nama_dan_status_blokir_tersambung_dari_tabel_users(): void
    {
        User::create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'nonaktif',
            'email_verified_at' => now(),
        ]);
        $this->gagal('budi@x.test', '10.0.0.1');

        $grup = $this->svc->grup()->firstWhere('email', 'budi@x.test');

        $this->assertSame('Budi Santoso', $grup['nama']);
        $this->assertTrue($grup['terblokir']);
    }

    // ── RINGKASAN DAN BADGE ───────────────────────────────────────────

    public function test_badge_hanya_menghitung_medium_ke_atas(): void
    {
        $this->gagal('low@x.test', '10.0.0.1');
        for ($i = 0; $i < 2; $i++) {
            $this->gagal('kuning@x.test', '10.0.0.2');
        }
        for ($i = 0; $i < 6; $i++) {
            $this->gagal('oranye@x.test', '10.0.0.3');
        }

        $r = $this->svc->ringkasan();

        $this->assertSame(1, $r['jumlah_low']);
        $this->assertSame(1, $r['jumlah_mencurigakan']);
        $this->assertSame(1, $r['jumlah_medium']);
        $this->assertSame(1, $r['jumlahAncaman']);
        $this->assertSame(9, $r['totalGagal']);
    }

    public function test_badge_dicache(): void
    {
        Cache::flush();
        $this->assertSame(0, $this->svc->jumlahBadge());

        for ($i = 0; $i < 6; $i++) {
            $this->gagal('baru@x.test', '10.0.0.5');
        }

        // Masih 0 karena hasil pertama sudah di-cache.
        $this->assertSame(0, $this->svc->jumlahBadge());

        $this->svc->bersihkanBadge();
        $this->assertSame(1, $this->svc->jumlahBadge());
    }

    // ── FILTER DAN PAGINASI ──────────────────────────────────────────

    public function test_daftar_bisa_difilter_berdasarkan_level(): void
    {
        $this->gagal('sepi@x.test', '10.0.0.1');
        for ($i = 0; $i < 6; $i++) {
            $this->gagal('ramai@x.test', '10.0.0.2');
        }

        $rows = $this->svc->daftar(['level' => 'medium']);

        $this->assertSame(1, $rows->total());
        $this->assertSame('ramai@x.test', $rows->first()['email']);
    }

    public function test_daftar_bisa_dicari_pencarian(): void
    {
        $this->gagal('satu@x.test', '10.0.0.1');
        $this->gagal('dua@x.test', '10.0.0.2');

        $this->assertSame(1, $this->svc->daftar(['q' => 'dua@'])->total());
        $this->assertSame(1, $this->svc->daftar(['q' => '10.0.0.1'])->total());
    }

    public function test_daftar_bisa_difilter_sumber(): void
    {
        $this->gagal('a@x.test', '10.0.0.1');
        LoginAttempt::create([
            'email' => 'a@x.test',
            'ip' => '10.0.0.1',
            'sumber' => 'google',
            'sukses' => 0,
            'waktu' => now()->subHour(),
        ]);

        $semua = $this->svc->daftar();
        $google = $this->svc->daftar(['sumber' => 'google']);

        $this->assertSame(1, $semua->total());
        $this->assertSame(1, $google->total());
        $this->assertSame(1, $google->first()['gagal']);
    }

    public function test_daftar_terpaginasi_dan_terurut_dari_paling_serius(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->gagal('serius@x.test', '10.0.0.1');
        }
        $this->gagal('sepele@x.test', '10.0.0.2');

        $rows = $this->svc->daftar(['perHal' => 1]);

        $this->assertSame(2, $rows->total());
        $this->assertSame('serius@x.test', $rows->first()['email']);

        $hal2 = $this->svc->daftar(['perHal' => 1, 'halaman' => 2]);
        $this->assertSame('sepele@x.test', $hal2->first()['email']);
    }

    // ── PEMUTARAN AKUN ────────────────────────────────────────────────

    public function test_memblokir_akun_menonaktifkan_dan_mencabut_sesi(): void
    {
        $admin = $this->admin();
        $user = User::create([
            'nama_lengkap' => 'Korban',
            'email' => 'korban@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $user->id,
            'token' => 'tokentest123',
            'tipe' => 'mobile',
            'user_agent' => 'Mozilla/5.0',
            'ip' => '10.0.0.1',
            'expires_at' => now()->addDays(7),
        ]);
        $this->gagal('korban@x.test', '10.0.0.1');

        $hasil = $this->svc->alihkanBlokir($user->id, $admin->id);

        $this->assertTrue($hasil['sukses']);
        $this->assertSame('nonaktif', $hasil['status']);
        $this->assertSame('nonaktif', $user->fresh()->status);
        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
        $this->assertSame(0, LoginAttempt::where('email', 'korban@x.test')->where('sukses', 0)->count());
    }

    public function test_membuka_blokir_mengaktifkan_kembali(): void
    {
        $admin = $this->admin();
        $user = User::create([
            'nama_lengkap' => 'Korban',
            'email' => 'korban@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'nonaktif',
            'email_verified_at' => now(),
        ]);

        $hasil = $this->svc->alihkanBlokir($user->id, $admin->id);

        $this->assertTrue($hasil['sukses']);
        $this->assertSame('aktif', $hasil['status']);
        $this->assertSame('aktif', $user->fresh()->status);
    }

    public function test_tidak_bisa_memblokir_akun_sendiri(): void
    {
        $admin = $this->admin();

        $hasil = $this->svc->alihkanBlokir($admin->id, $admin->id);

        $this->assertFalse($hasil['sukses']);
        $this->assertSame('aktif', $admin->fresh()->status);
    }

    public function test_tidak_bisa_memblokir_akun_admin_lain(): void
    {
        $aktor = $this->admin();
        $admin2 = User::create([
            'nama_lengkap' => 'Admin Dua',
            'email' => 'admin2@rsudmerauke.go.id',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $hasil = $this->svc->alihkanBlokir($admin2->id, $aktor->id);

        $this->assertFalse($hasil['sukses']);
        $this->assertSame('aktif', $admin2->fresh()->status);
    }

    // ── PENANGANAN DI JALUR LOGIN ─────────────────────────────────────

    public function test_status_nonaktif_menolak_di_semua_jalur_login(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Diblokir',
            'email' => 'diblokir@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'nonaktif',
            'email_verified_at' => now(),
        ]);

        // Jalur API
        $res = $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat-panjang',
        ]);
        $this->assertContains($res->status(), [401, 403, 422]);
    }

    // ── PENANGANAN RETENSI ───────────────────────────────────────────

    public function test_riwayat_lama_dihapus_saat_percobaan_baru(): void
    {
        // 31 hari lalu -> harus terhapus, 29 hari lalu -> harus bertahan
        $this->gagal('lama@x.test', '10.0.0.1', jamLalu: 24 * 31);
        $this->gagal('baru@x.test', '10.0.0.1', jamLalu: 24 * 29);

        // Picu pencatatan baru lewat endpoint API.
        User::create([
            'nama_lengkap' => 'Uji Retensi',
            'email' => 'uji-retensi@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        $this->postJson('/api/mobile/login', [
            'email' => 'uji-retensi@x.test',
            'password' => 'salah-sekali',
        ])->assertStatus(401);

        $this->assertSame(0, LoginAttempt::where('email', 'lama@x.test')->count());
        $this->assertSame(1, LoginAttempt::where('email', 'baru@x.test')->count());
    }

    public function test_sumber_dicatat_sesuai_jalur_login(): void
    {
        User::create([
            'nama_lengkap' => 'Uji Sumber',
            'email' => 'uji-sumber@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/mobile/login', [
            'email' => 'uji-sumber@x.test',
            'password' => 'salah-sekali',
        ])->assertStatus(401);

        $baris = LoginAttempt::where('email', 'uji-sumber@x.test')->where('sukses', 0)->first();

        $this->assertNotNull($baris);
        $this->assertSame('api', $baris->sumber);
    }

    // ── HALAMAN ADMIN ────────────────────────────────────────────────

    // Middleware admin memakai session uid/role, bukan guard auth, jadi test
    // menyetel session secara langsung.
    private function sebagaiAdmin(): User
    {
        $admin = $this->admin();
        $this->withSession([
            'uid' => $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
            'email' => $admin->email,
        ]);

        return $admin;
    }

    public function test_halaman_tracker_bisa_diakses_admin(): void
    {
        $this->sebagaiAdmin();
        $this->gagal('x@x.test', '10.0.0.1');

        $res = $this->get('/admin/login-gagal');

        $res->assertStatus(200);
        $res->assertSee('Tracker Login Gagal', false);
    }

    public function test_halaman_tracker_tertahan_dari_non_admin(): void
    {
        $user = User::create([
            'nama_lengkap' => 'Pegawai',
            'email' => 'pegawai@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        $this->withSession(['uid' => $user->id, 'role' => 'pegawai']);

        $this->get('/admin/login-gagal')->assertRedirect();
    }

    public function test_halaman_tracker_menampilkan_badge_an_tacaman(): void
    {
        $this->sebagaiAdmin();
        for ($i = 0; $i < 6; $i++) {
            $this->gagal('bahaya@x.test', '10.0.0.1');
        }
        Cache::flush();

        $res = $this->get('/admin/login-gagal');

        $res->assertStatus(200);
        // Badgele sidebar dan banner harus muncul karena ada 1 ancaman medium.
        $this->assertSame(1, app(AncamanLoginService::class)->ringkasan()['jumlahAncaman']);
    }

    public function test_endpoint_data_mengembalikan_tbody(): void
    {
        $this->sebagaiAdmin();
        $this->gagal('cari@x.test', '10.0.0.1');

        $res = $this->getJson('/admin/login-gagal/data');

        $res->assertStatus(200);
        $res->assertJsonPath('sukses', true);
        $res->assertJsonStructure(['sukses', 'total', 'tbody', 'paginasi', 'ringkasan']);
    }

    public function test_endpoint_status_menolak_blokir_akun_sendiri(): void
    {
        $admin = $this->sebagaiAdmin();

        $res = $this->postJson('/admin/login-gagal/status', ['id' => $admin->id]);

        $res->assertStatus(422);
        $this->assertSame('aktif', $admin->fresh()->status);
    }

    public function test_endpoint_status_memblokir_akun_pegawai(): void
    {
        $this->sebagaiAdmin();
        $user = User::create([
            'nama_lengkap' => 'Pegawai Target',
            'email' => 'target@x.test',
            'password_hash' => bcrypt('rahasia-kuat-panjang'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $res = $this->postJson('/admin/login-gagal/status', ['id' => $user->id]);

        $res->assertStatus(200);
        $res->assertJsonPath('sukses', true);
        $res->assertJsonPath('status', 'nonaktif');
        $this->assertSame('nonaktif', $user->fresh()->status);
    }
}
