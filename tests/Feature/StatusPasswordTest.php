<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Notifikasi;
use App\Models\User;
use App\Services\PasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status password per pengguna: apakah pernah diganti, kapan terakhir diganti,
 * dan apakah sudah melewati ambang waktu. Menutup jalur penulisan password
 * (ubah password, ganti oleh admin) sekaligus endpoint notifikasi mobile.
 */
class StatusPasswordTest extends TestCase
{
    use RefreshDatabase;

    private PasswordService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(PasswordService::class);
    }

    private static int $urut = 0;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => 'pegawai.uji.'.(++self::$urut).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    /** Sesi web pegawai biasa; middleware auth membaca uid/role dari session. */
    private function actingAsPegawai(User $user): void
    {
        $this->withSession([
            'uid' => (int) $user->id,
            'role' => $user->role,
            'nama' => $user->nama_lengkap,
        ]);
    }

    private function actingAsAdmin(): void
    {
        $admin = $this->buatUser(['email' => 'admin.uji.'.(++self::$urut).'@contoh.test', 'role' => 'admin']);

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);
    }

    /** Login mobile sungguhan supaya token & cookie-nya valid untuk middleware. */
    private function loginMobile(User $user): ApiToken
    {
        $respons = $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ]);
        $respons->assertStatus(200);

        return ApiToken::where('user_id', $user->id)->latest('id')->first();
    }

    // ── STATUS DASAR ────────────────────────────────────────────────

    public function test_akun_baru_berstatus_belum_pernah_ganti(): void
    {
        $user = $this->buatUser();

        $this->assertNull($user->password_changed_at);
        $this->assertFalse($user->pernahGantiPassword());

        $status = $this->svc->status($user);
        $this->assertFalse($status['pernah']);
        $this->assertNull($status['terakhir']);
        $this->assertNull($status['umur_hari']);
        $this->assertSame('belum', $status['level']);
        $this->assertTrue($status['perlu_ganti']);
    }

    public function test_password_baru_dihitung_aman(): void
    {
        $user = $this->buatUser(['password_changed_at' => now()->subDays(10)]);

        $status = $this->svc->status($user);
        $this->assertTrue($status['pernah']);
        $this->assertSame(10, $status['umur_hari']);
        $this->assertSame('baru', $status['level']);
        $this->assertFalse($status['perlu_ganti']);
    }

    public function test_batas_ambang_hari_tepat_dihitung(): void
    {
        $tepatAmbang = $this->svc->status($this->buatUser([
            'password_changed_at' => now()->subDays(PasswordService::AMBANG_PERLU_HARI),
        ]));
        $this->assertSame('perlu', $tepatAmbang['level']);

        $tepatLama = $this->svc->status($this->buatUser([
            'password_changed_at' => now()->subDays(PasswordService::AMBANG_LAMA_HARI),
        ]));
        $this->assertSame('lama', $tepatLama['level']);

        $sehariBelum = $this->svc->status($this->buatUser([
            'password_changed_at' => now()->subDays(PasswordService::AMBANG_PERLU_HARI - 1),
        ]));
        $this->assertSame('baru', $sehariBelum['level']);
    }

    public function test_tandai_diubah_mencatat_waktu_dan_mengirim_notifikasi(): void
    {
        $user = $this->buatUser();

        $this->svc->tandaiDiubah($user, 'oleh administrator');

        $user->refresh();
        $this->assertNotNull($user->password_changed_at);

        $notifikasi = Notifikasi::where('user_id', $user->id)->first();
        $this->assertNotNull($notifikasi);
        $this->assertSame('password', $notifikasi->kategori);
        $this->assertSame('success', $notifikasi->tipe);
        $this->assertStringContainsString('diperbarui', $notifikasi->isi);
    }

    public function test_tandai_diubah_tanpa_notifikasi_tetap_mencatat_waktu(): void
    {
        $user = $this->buatUser();

        $this->svc->tandaiDiubah($user, null, false);

        $this->assertNotNull($user->fresh()->password_changed_at);
        $this->assertDatabaseMissing('notifikasis', ['user_id' => $user->id]);
    }

    // ── RINGKASAN & FILTER ───────────────────────────────────────────

    public function test_ringkasan_memisahkan_kelompok_status(): void
    {
        $this->buatUser(['email' => 'a@x.test']);
        $this->buatUser(['email' => 'b@x.test', 'password_changed_at' => now()->subDays(5)]);
        $this->buatUser(['email' => 'c@x.test', 'password_changed_at' => now()->subDays(120)]);
        $this->buatUser(['email' => 'd@x.test', 'password_changed_at' => now()->subDays(400)]);
        $this->buatUser(['email' => 'admin@x.test', 'role' => 'admin', 'status' => 'aktif']);

        $r = $this->svc->ringkasan();
        $this->assertSame(4, $r['total'], 'Admin tidak ikut dihitung.');
        $this->assertSame(1, $r['belum']);
        $this->assertSame(1, $r['perlu']);
        $this->assertSame(1, $r['lama']);
        $this->assertSame(1, $r['aman']);
        $this->assertSame(3, $r['perluPerhatian']);
    }

    public function test_filter_status_password_memisahkan_pengguna(): void
    {
        $belum = $this->buatUser(['email' => 'a@x.test']);
        $aman = $this->buatUser(['email' => 'b@x.test', 'password_changed_at' => now()->subDays(5)]);
        $lama = $this->buatUser(['email' => 'c@x.test', 'password_changed_at' => now()->subDays(400)]);

        $ambil = function (?string $filter) {
            return User::where('role', '!=', 'admin')
                ->tap(fn ($q) => $this->svc->terapkanFilter($q, $filter))
                ->pluck('id')->all();
        };

        $this->assertEqualsCanonicalizing([$belum->id], $ambil('belum'));
        $this->assertEqualsCanonicalizing([$aman->id], $ambil('aman'));
        $this->assertEqualsCanonicalizing([$lama->id], $ambil('lama'));
        $this->assertCount(3, $ambil(null), 'Filter tak dikenal berarti tanpa filter.');
    }

    // ── PENGINGAT ────────────────────────────────────────────────────

    public function test_pengingat_dikirim_ke_yang_belum_dan_yang_lama_saja(): void
    {
        $belum = $this->buatUser(['email' => 'a@x.test']);
        $aman = $this->buatUser(['email' => 'b@x.test', 'password_changed_at' => now()->subDays(5)]);
        $lama = $this->buatUser(['email' => 'c@x.test', 'password_changed_at' => now()->subDays(400)]);
        $admin = $this->buatUser(['email' => 'admin@x.test', 'role' => 'admin']);

        $hasil = $this->svc->ingatkan();

        $this->assertTrue($hasil['sukses']);
        $this->assertSame(2, $hasil['terkirim']);

        $this->assertDatabaseHas('notifikasis', ['user_id' => $belum->id, 'kategori' => 'password', 'tipe' => 'warning']);
        $this->assertDatabaseHas('notifikasis', ['user_id' => $lama->id, 'kategori' => 'password']);
        $this->assertDatabaseMissing('notifikasis', ['user_id' => $aman->id]);
        $this->assertDatabaseMissing('notifikasis', ['user_id' => $admin->id]);
    }

    public function test_pengingat_dilewati_selama_cooldown(): void
    {
        $user = $this->buatUser();

        $this->svc->ingatkan();
        $kedua = $this->svc->ingatkan();

        $this->assertSame(0, $kedua['terkirim']);
        $this->assertSame(1, $kedua['dilewati']);
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->count());
    }

    public function test_pengingat_dikirim_lagi_setelah_cooldown_berlalu(): void
    {
        $user = $this->buatUser();

        $this->svc->ingatkan();

        Notifikasi::where('user_id', $user->id)
            ->update(['created_at' => now()->subDays(PasswordService::COOLDOWN_HARI + 1)]);

        $this->assertSame(1, $this->svc->ingatkan()['terkirim']);
    }

    public function test_pengingat_menyebut_kondisi_akun_nonaktif(): void
    {
        $user = $this->buatUser(['status' => 'nonaktif']);

        $this->svc->ingatkan();

        $this->assertStringContainsString(
            'nonaktif',
            Notifikasi::where('user_id', $user->id)->value('isi')
        );
    }

    public function test_pengingat_tidak_menggagalkan_ketika_tidak_ada_kandidat(): void
    {
        $hasil = $this->svc->ingatkan();

        $this->assertTrue($hasil['sukses']);
        $this->assertSame(0, $hasil['terkirim']);
    }

    // ── ENDPOINT NOTIFIKASI ──────────────────────────────────────────

    public function test_endpoint_notifikasi_hanya_menampilkan_milik_pengguna(): void
    {
        $saya = $this->buatUser(['email' => 'saya@x.test']);
        $lain = $this->buatUser(['email' => 'lain@x.test']);

        Notifikasi::create(['user_id' => $saya->id, 'isi' => 'Notifikasi saya', 'kategori' => 'password']);
        Notifikasi::create(['user_id' => $lain->id, 'isi' => 'Rahasia orang lain']);

        $token = $this->loginMobile($saya);

        $respons = $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->getJson('/api/mobile/notifikasi');

        $respons->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('belum_dibaca', 1);

        $isi = collect($respons->json('notifikasi'))->pluck('isi');
        $this->assertSame(['Notifikasi saya'], $isi->all());
        $this->assertNotContains('Rahasia orang lain', $isi->all());
    }

    public function test_endpoint_notifikasi_menolak_tanpa_token(): void
    {
        $this->getJson('/api/mobile/notifikasi')
            ->assertStatus(401)
            ->assertJsonPath('sukses', false);
    }

    public function test_endpoint_notifikasi_dapat_difilter_kategori(): void
    {
        $user = $this->buatUser();

        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Pengingat password', 'kategori' => 'password']);
        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Jadwal berubah', 'kategori' => 'jadwal']);

        $token = $this->loginMobile($user);

        $respons = $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->getJson('/api/mobile/notifikasi?kategori=password');

        $respons->assertStatus(200)->assertJsonPath('total', 1);
        $this->assertSame('password', $respons->json('notifikasi.0.kategori'));
        $this->assertSame('Password', $respons->json('notifikasi.0.kategori_label'));
    }

    public function test_kategori_tak_dikenal_ditolak_dengan_daftar_yang_tersedia(): void
    {
        $user = $this->buatUser();
        $token = $this->loginMobile($user);

        $respons = $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->getJson('/api/mobile/notifikasi?kategori=ngawur');

        $respons->assertStatus(422);
        $this->assertContains('password', $respons->json('kategori_tersedia'));
    }

    public function test_tandai_baca_hanya_untuk_notifikasi_milik_sendiri(): void
    {
        $saya = $this->buatUser(['email' => 'saya@x.test']);
        $lain = $this->buatUser(['email' => 'lain@x.test']);

        $milikSaya = Notifikasi::create(['user_id' => $saya->id, 'isi' => 'Milik saya']);
        $milikOrangLain = Notifikasi::create(['user_id' => $lain->id, 'isi' => 'Milik orang lain']);

        $token = $this->loginMobile($saya);

        $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->postJson("/api/mobile/notifikasi/{$milikSaya->id}/baca")
            ->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('belum_dibaca', 0);

        $this->assertTrue((bool) $milikSaya->fresh()->is_read);

        $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->postJson("/api/mobile/notifikasi/{$milikOrangLain->id}/baca")
            ->assertStatus(404);

        $this->assertFalse((bool) $milikOrangLain->fresh()->is_read);
    }

    public function test_tandai_semua_dibaca(): void
    {
        $user = $this->buatUser();

        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Satu']);
        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Dua']);
        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Tiga', 'is_read' => true]);

        $token = $this->loginMobile($user);

        $respons = $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->postJson('/api/mobile/notifikasi/baca-semua');

        $respons->assertStatus(200)
            ->assertJsonPath('ditandai', 2)
            ->assertJsonPath('belum_dibaca', 0);

        $this->assertSame(0, Notifikasi::where('user_id', $user->id)->belumDibaca()->count());
    }

    public function test_endpoint_total_mengembalikan_badge(): void
    {
        $user = $this->buatUser();

        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Satu']);
        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Dua']);

        $token = $this->loginMobile($user);

        $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->getJson('/api/mobile/notifikasi/total')
            ->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('belum_dibaca', 2);
    }

    public function test_hapus_notifikasi_milik_sendiri(): void
    {
        $saya = $this->buatUser(['email' => 'saya@x.test']);
        $lain = $this->buatUser(['email' => 'lain@x.test']);

        $notifikasi = Notifikasi::create(['user_id' => $saya->id, 'isi' => 'Dihapus']);
        $milikOrangLain = Notifikasi::create(['user_id' => $lain->id, 'isi' => 'Tetap']);

        $token = $this->loginMobile($saya);

        $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->deleteJson("/api/mobile/notifikasi/{$notifikasi->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('notifikasis', ['id' => $notifikasi->id]);
        $this->assertDatabaseHas('notifikasis', ['id' => $milikOrangLain->id]);
    }

    // ── JALUR WEB ────────────────────────────────────────────────────

    public function test_ubah_password_pegawai_mencatat_waktu(): void
    {
        $user = $this->buatUser();
        $this->actingAsPegawai($user);

        $this->post('/ubah-password', [
            'password_lama' => 'rahasia-kuat',
            'password_baru' => 'rahasia-baru-kuat',
            'password_konfirmasi' => 'rahasia-baru-kuat',
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(password_verify('rahasia-baru-kuat', $user->password_hash));
        $this->assertDatabaseHas('notifikasis', ['user_id' => $user->id, 'kategori' => 'password']);
    }

    public function test_ubah_password_ditolak_bila_password_lama_salah(): void
    {
        $user = $this->buatUser();
        $this->actingAsPegawai($user);

        $this->post('/ubah-password', [
            'password_lama' => 'salah',
            'password_baru' => 'rahasia-baru-kuat',
            'password_konfirmasi' => 'rahasia-baru-kuat',
        ]);

        $this->assertNull($user->fresh()->password_changed_at, 'Waktu tidak boleh berubah saat permintaan gagal.');
    }

    public function test_ganti_password_oleh_admin_mencatat_waktu_dan_mengirim_notifikasi(): void
    {
        $pegawai = $this->buatUser();
        $this->actingAsAdmin();

        $this->post('/admin/pegawai/ganti-password', [
            'id' => $pegawai->id,
            'password' => 'rahasia-admin-kuat',
            'password_konfirmasi' => 'rahasia-admin-kuat',
        ])->assertRedirect();

        $pegawai->refresh();
        $this->assertNotNull($pegawai->password_changed_at);
        $this->assertTrue(password_verify('rahasia-admin-kuat', $pegawai->password_hash));
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $pegawai->id,
            'kategori' => 'password',
            'tipe' => 'success',
        ]);
    }

    public function test_admin_dapat_memicat_daftar_pegawai(): void
    {
        $belum = $this->buatUser(['nama_lengkap' => 'Pegawai Belum Ganti', 'email' => 'a@x.test']);
        $aman = $this->buatUser([
            'nama_lengkap' => 'Pegawai Password Aman',
            'email' => 'b@x.test',
            'password_changed_at' => now()->subDays(5),
        ]);
        $this->actingAsAdmin();

        $respons = $this->get('/admin/pegawai?password=belum');

        $respons->assertStatus(200)->assertSee('Pegawai Belum Ganti');
        $respons->assertDontSee('Pegawai Password Aman');
    }

    public function test_admin_tidak_bisa_mengirim_pengingat(): void
    {
        $pegawai = $this->buatUser();

        $this->post('/admin/pegawai/ingatkan-password')->assertRedirect(route('login'));

        $this->assertDatabaseMissing('notifikasis', ['user_id' => $pegawai->id]);
    }

    public function test_admin_mengirim_pengingat_hanya_untuk_yang_perlu(): void
    {
        $belum = $this->buatUser(['email' => 'a@x.test']);
        $aman = $this->buatUser(['email' => 'b@x.test', 'password_changed_at' => now()->subDays(5)]);
        $this->actingAsAdmin();

        $this->post('/admin/pegawai/ingatkan-password')->assertRedirect();

        $this->assertDatabaseHas('notifikasis', ['user_id' => $belum->id, 'kategori' => 'password']);
        $this->assertDatabaseMissing('notifikasis', ['user_id' => $aman->id]);
    }

    // ── ENDPOINT ME ───────────────────────────────────────────────────

    public function test_endpoint_me_memiliki_status_password_dan_badge_notifikasi(): void
    {
        $user = $this->buatUser();

        Notifikasi::create(['user_id' => $user->id, 'isi' => 'Satu']);

        $token = $this->loginMobile($user);

        $respons = $this->withCredentials()->withUnencryptedCookie('auth_token', $token->token)
            ->getJson('/api/mobile/me');

        $respons->assertStatus(200)
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('password.pernah', false)
            ->assertJsonPath('password.level', 'belum')
            ->assertJsonPath('password.perlu_ganti', true)
            ->assertJsonPath('notifikasi_belum_dibaca', 1);
    }
}
