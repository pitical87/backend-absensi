<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi logbook oleh atasan langsung lewat API mobile.
 *
 * Skenario utama: atasan langsung Firman adalah Diana, jadi Diana boleh
 * melihat dan memverifikasi logbook Firman, sedangkan user lain tidak.
 */
class VerifikasiLogbookAtasanTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function buatUser(string $nama, string $role = 'pegawai', string $status = 'aktif'): User
    {
        $urut = ++self::$urut;

        return User::create([
            'nama_lengkap' => $nama,
            'nip' => '19800'.(200 + $urut),
            'email' => str($nama)->slug().$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => now(),
        ]);
    }

    /** Terbitkan token supaya request bisa memakai middleware mobile.auth. */
    private function sebagai(User $user): self
    {
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token' => 'tok-'.$user->id.'-'.++self::$urut,
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        // route api tidak memakai middleware EncryptCookies dan token disimpan
        // lewat Cookie::make, jadi cookie dikirim apa adanya; withCredentials
        // wajib karena request JSON tidak mengirim cookie secara default
        return $this->withUnencryptedCookie('auth_token', $token->token)->withCredentials();
    }

    private function entri(User $u, array $atribut = []): Logbook
    {
        return Logbook::create(array_merge([
            'user_id' => $u->id,
            'tanggal' => now()->startOfMonth()->addDays(4)->toDateString(),
            'jam' => '08:00',
            'isi' => 'Entri logbook',
        ], $atribut));
    }

    private function hubungkan(User $bawahan, User $atasan): void
    {
        AtasanLangsung::create(['user_id' => $bawahan->id, 'atasan_id' => $atasan->id]);
    }

    // ── GET /logbook/bawahan ────────────────────────
    public function test_daftar_bawahan_menampilkan_progres_verifikasi(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman, ['isi' => 'Kontrol pagi']);

        $respons = $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan='.now()->month.'&tahun='.now()->year)
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('bulan', (int) now()->month)
            ->assertJsonPath('tahun', (int) now()->year);

        $baris = $respons->json('data.0');
        $this->assertSame($firman->id, $baris['id']);
        $this->assertSame('Firman', $baris['nama']);
        $this->assertSame(1, $baris['total_entri']);
        $this->assertSame(0, $baris['terverifikasi']);
        $this->assertSame(1, $baris['belum']);
    }

    public function test_daftar_bawahan_kosong_bila_tidak_punya_bawahan(): void
    {
        $diana = $this->buatUser('Diana Sihombong');

        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total_bawahan', 0)
            ->assertJsonPath('data', []);
    }

    public function test_daftar_bawahan_hanya_menampilkan_bawahan_langsung(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $bukanBawahan = $this->buatUser('Orang Lain');
        $this->hubungkan($firman, $diana);

        $respons = $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan='.now()->month.'&tahun='.now()->year)
            ->assertOk()
            ->assertJsonPath('total_bawahan', 1);

        $this->assertSame($firman->id, $respons->json('data.0.id'));
        $this->assertSame('Firman', $respons->json('data.0.nama'));
    }

    public function test_daftar_bawahan_menghitung_entri_bulan_berjalan(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);

        $a = $this->entri($firman);
        $this->entri($firman, ['jam' => '09:00']);
        $this->entri($firman, ['jam' => '10:00', 'is_verified' => true, 'verified_at' => now(), 'verified_by' => $diana->id]);
        // entri bulan lain tidak ikut dihitung
        $this->entri($firman, ['tanggal' => now()->subMonths(2)->startOfMonth()->addDays(2)->toDateString()]);

        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan='.now()->month.'&tahun='.now()->year)
            ->assertOk()
            ->assertJsonPath('total_belum', 2)
            ->assertJsonPath('data.0.total_entri', 3)
            ->assertJsonPath('data.0.terverifikasi', 1)
            ->assertJsonPath('data.0.belum', 2);
    }

    public function test_daftar_bawahan_menolak_bulan_tahun_tidak_valid(): void
    {
        $diana = $this->buatUser('Diana Sihombong');

        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan=13&tahun='.now()->year)
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Parameter bulan/tahun tidak valid.');
    }

    public function test_daftar_bawahan_menuntut_token(): void
    {
        $this->getJson('/api/mobile/logbook/bawahan')
            ->assertStatus(401)
            ->assertJsonPath('sukses', false);
    }

    // ── GET /logbook/bawahan/{user_id} ──────────────
    public function test_atasan_langsung_melihat_logbook_bawahannya(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman, ['isi' => 'Kontrol pagi', 'jam' => '07:30']);

        $respons = $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan/'.$firman->id.'?bulan='.now()->month.'&tahun='.now()->year)
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('bawahan.nama', 'Firman')
            ->assertJsonPath('total_entri', 1)
            ->assertJsonPath('terverifikasi', 0)
            ->assertJsonPath('belum', 1)
            ->assertJsonPath('total_hari', 1);

        $hari = $entri->tanggal->format('Y-m-d');
        $baris = $respons->json('data.'.$hari);
        $this->assertCount(1, $baris);
        $this->assertSame($entri->id, $baris[0]['id']);
        $this->assertSame('Kontrol pagi', $baris[0]['isi']);
        $this->assertFalse($baris[0]['is_verified']);
    }

    public function test_bawahan_tidak_bisa_dilihat_atasan_lain(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $orangLain = $this->buatUser('Orang Lain');
        $this->hubungkan($firman, $diana);
        $this->entri($firman);

        $this->sebagai($orangLain)
            ->getJson('/api/mobile/logbook/bawahan/'.$firman->id)
            ->assertStatus(403)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Anda bukan atasan langsung pegawai ini.');
    }

    public function test_atasan_ditolak_saat_melihat_logbook_sendiri(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $this->entri($diana);

        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan/'.$diana->id)
            ->assertStatus(403)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Gunakan /logbook untuk membaca logbook sendiri.');
    }

    public function test_atasan_terdaftar_kedua_boleh_melihat_logbook_bawahan(): void
    {
        // satu pegawai punya dua atasan langsung; keduanya berwenang
        $diana = $this->buatUser('Diana Sihombong');
        $rina = $this->buatUser('Rina Wijaya');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $this->hubungkan($firman, $rina);
        $this->entri($firman);

        $this->sebagai($rina)
            ->getJson('/api/mobile/logbook/bawahan/'.$firman->id)
            ->assertOk()
            ->assertJsonPath('total_entri', 1);
    }

    // ── POST /logbook/verifikasi ────────────────────
    public function test_atasan_langsung_dapat_memverifikasi_logbook_bawahannya(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$entri->id],
                'aksi' => 'verifikasi',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('jumlah', 1);

        $entri->refresh();
        $this->assertTrue($entri->is_verified);
        $this->assertNotNull($entri->verified_at);
        $this->assertSame((int) $diana->id, (int) $entri->verified_by);
    }

    public function test_verifikasi_banyak_entri_sekaligus(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $ids = [];
        foreach (['08:00', '09:00', '10:00'] as $jam) {
            $ids[] = $this->entri($firman, ['jam' => $jam])->id;
        }

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => $ids, 'aksi' => 'verifikasi'])
            ->assertOk()
            ->assertJsonPath('jumlah', 3);

        $this->assertSame(3, Logbook::where('user_id', $firman->id)->where('is_verified', true)->count());
    }

    public function test_verifikasi_dengan_filter_user_id_hanya_menjelang_bawahan_tersebut(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $rina = $this->buatUser('Rina Wijaya');
        $this->hubungkan($firman, $diana);
        $this->hubungkan($rina, $diana);

        $a = $this->entri($firman);
        $b = $this->entri($rina);

        $respons = $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan='.now()->month.'&tahun='.now()->year)
            ->assertOk();

        $this->assertSame(2, $respons->json('total_bawahan'));
        $this->assertSame(2, $respons->json('total_belum'));

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$a->id, $b->id],
                'aksi' => 'verifikasi',
                'user_id' => $firman->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('sukses', false);

        $this->assertFalse($a->fresh()->is_verified);
        $this->assertFalse($b->fresh()->is_verified);
    }

    public function test_atasan_tidak_bisa_memverifikasi_logbook_bukan_bawahannya(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $asing = $this->buatUser('Orang Asing');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$entri->id],
                'aksi' => 'verifikasi',
            ])
            ->assertOk();

        // request dari user yang bukan atasan langsung
        $entri->update(['is_verified' => false, 'verified_at' => null, 'verified_by' => null]);

        $this->sebagai($asing)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$entri->id],
                'aksi' => 'verifikasi',
            ])
            ->assertStatus(403)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Anda tidak memiliki bawahan langsung untuk diverifikasi.');

        $this->assertFalse($entri->fresh()->is_verified);
    }

    public function test_verifikasi_ditolak_utuh_bila_salah_satu_id_di_luar_kewenangan(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $asing = $this->buatUser('Orang Asing');
        $this->hubungkan($firman, $diana);

        $milikDiana = $this->entri($firman);
        $milikAsing = $this->entri($asing);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$milikDiana->id, $milikAsing->id],
                'aksi' => 'verifikasi',
            ])
            ->assertStatus(403)
            ->assertJsonPath('pesan', 'Entri logbook tersebut bukan milik bawahan langsung Anda.');

        // tidak ada verifikasi parsial
        $this->assertFalse($milikDiana->fresh()->is_verified);
        $this->assertFalse($milikAsing->fresh()->is_verified);
    }

    public function test_atasan_tidak_bisa_memverifikasi_logbook_sendiri(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $entri = $this->entri($diana);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$entri->id],
                'aksi' => 'verifikasi',
            ])
            ->assertStatus(403)
            ->assertJsonPath('sukses', false);

        $this->assertFalse($entri->fresh()->is_verified);
    }

    public function test_batal_verifikasi_melepas_status(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman, ['is_verified' => true, 'verified_at' => now(), 'verified_by' => $diana->id]);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => [$entri->id], 'aksi' => 'batal'])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('jumlah', 1);

        $entri->refresh();
        $this->assertFalse($entri->is_verified);
        $this->assertNull($entri->verified_at);
        $this->assertNull($entri->verified_by);
    }

    public function test_verifikasi_entri_yang_sudah_terverifikasi_mengembalikan_404(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman, ['is_verified' => true, 'verified_at' => now(), 'verified_by' => $diana->id]);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => [$entri->id], 'aksi' => 'verifikasi'])
            ->assertStatus(404)
            ->assertJsonPath('sukses', false);
    }

    public function test_batal_verifikasi_entri_yang_belum_terverifikasi_mengembalikan_404(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => [$entri->id], 'aksi' => 'batal'])
            ->assertStatus(404)
            ->assertJsonPath('sukses', false);
    }

    public function test_verifikasi_menolak_aksi_tidak_dikenal(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => [$entri->id], 'aksi' => 'hapus'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Aksi harus verifikasi atau batal.');

        $this->assertFalse($entri->fresh()->is_verified);
    }

    public function test_verifikasi_menolak_ids_kosong(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', ['ids' => [], 'aksi' => 'verifikasi'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Pilih minimal satu entri logbook.');
    }

    public function test_verifikasi_menolak_user_id_tidak_valid(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $entri = $this->entri($firman);

        $this->sebagai($diana)
            ->postJson('/api/mobile/logbook/verifikasi', [
                'ids' => [$entri->id],
                'aksi' => 'verifikasi',
                'user_id' => 'bukan-angka',
            ])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Parameter user_id tidak valid.');
    }

    public function test_endpoint_verifikasi_menuntut_token(): void
    {
        $this->postJson('/api/mobile/logbook/verifikasi', ['ids' => [1], 'aksi' => 'verifikasi'])
            ->assertStatus(401)
            ->assertJsonPath('sukses', false);
    }

    // ── Hak akses & bawahan pada respons login ──────
    public function test_login_mengembalikan_hak_akses_dan_bawahan(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $rina = $this->buatUser('Rina Wijaya');
        $this->hubungkan($firman, $diana);
        $this->hubungkan($rina, $diana);

        $respons = $this->postJson('/api/mobile/login', [
            'email' => $diana->email,
            'password' => 'rahasia-kuat',
        ])->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('user.role', 'pegawai')
            ->assertJsonPath('hak_akses.verifikasi_logbook', true)
            ->assertJsonPath('hak_akses.buat_jadwal', true)
            ->assertJsonPath('hak_akses.verifikasi_ijin', true)
            ->assertJsonPath('hak_akses.verifikasi_lembur', true)
            ->assertJsonPath('hak_akses.verifikasi_perubahan_jadwal', true);

        $bawahan = $respons->json('bawahan');
        $this->assertCount(2, $bawahan);
        $this->assertSame(['id' => $firman->id, 'nama' => 'Firman', 'email' => $firman->email], $bawahan[0]);
        $this->assertSame(['id' => $rina->id, 'nama' => 'Rina Wijaya', 'email' => $rina->email], $bawahan[1]);
    }

    public function test_login_tanpa_bawahan_menghasilkan_hak_akses_semuanya_false(): void
    {
        $budi = $this->buatUser('Budi Santoso');

        $this->postJson('/api/mobile/login', [
            'email' => $budi->email,
            'password' => 'rahasia-kuat',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'pegawai')
            ->assertJsonPath('hak_akses', [
                'verifikasi_logbook' => false,
                'buat_jadwal' => false,
                'verifikasi_ijin' => false,
                'verifikasi_lembur' => false,
                'verifikasi_perubahan_jadwal' => false,
            ])
            ->assertJsonPath('bawahan', []);
    }

    public function test_bawahan_yang_jadi_atasan_tidak_ikut_terdaftar(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        // relasi berbalik: Diana adalah bawahan Firman, bukan sebaliknya
        $this->hubungkan($diana, $firman);

        $respons = $this->postJson('/api/mobile/login', [
            'email' => $firman->email,
            'password' => 'rahasia-kuat',
        ])->assertOk();

        $this->assertSame([[
            'id' => $diana->id,
            'nama' => 'Diana Sihombong',
            'email' => $diana->email,
        ]], $respons->json('bawahan'));
    }

    public function test_endpoint_me_ikut_mengembalikan_hak_akses_dan_bawahan(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);

        $this->sebagai($diana)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('hak_akses.verifikasi_logbook', true)
            ->assertJsonPath('hak_akses.buat_jadwal', true)
            ->assertJsonPath('bawahan.0.nama', 'Firman');
    }

    // ── Entri yang dibuat admin otomatis terverifikasi ─
    public function test_entri_yang_dibuat_admin_otomatis_terverifikasi(): void
    {
        $admin = $this->buatUser('Admin Uji', 'admin');
        $firman = $this->buatUser('Firman');
        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        $respons = $this->postJson(route('admin.logbook.simpan'), [
            'user_id' => $firman->id,
            'tanggal' => ['2026-08-03', '2026-08-04'],
            'jam' => ['08:00', '09:30'],
            'isi' => ['Kontrol pagi', 'Rawat jalan'],
        ]);

        $respons->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('terverifikasi', true);

        $entri = Logbook::where('user_id', $firman->id)->orderBy('id')->get();
        $this->assertCount(2, $entri);
        foreach ($entri as $e) {
            $this->assertTrue($e->is_verified, 'Entri admin harus terverifikasi.');
            $this->assertNotNull($e->verified_at);
            $this->assertSame((int) $admin->id, (int) $e->verified_by);
        }
    }

    public function test_entri_admin_terverifikasi_tidak_lagi_ditunggu_atasan(): void
    {
        $admin = $this->buatUser('Admin Uji', 'admin');
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);
        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        $this->postJson(route('admin.logbook.simpan'), [
            'user_id' => $firman->id,
            'tanggal' => ['2026-08-03'],
            'jam' => ['08:00'],
            'isi' => ['Kontrol pagi'],
        ])->assertOk();

        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan=8&tahun=2026')
            ->assertOk()
            ->assertJsonPath('total_belum', 0)
            ->assertJsonPath('data.0.total_entri', 1)
            ->assertJsonPath('data.0.terverifikasi', 1);
    }

    public function test_entri_pegawai_tetap_belum_terverifikasi(): void
    {
        $diana = $this->buatUser('Diana Sihombong');
        $firman = $this->buatUser('Firman');
        $this->hubungkan($firman, $diana);

        $this->sebagai($firman)
            ->postJson('/api/mobile/logbook/simpan', [
                'tanggal' => '2026-08-03',
                'jam' => '08:00',
                'isi' => 'Kontrol pagi',
            ])
            ->assertStatus(201);

        $entri = Logbook::where('user_id', $firman->id)->first();
        $this->assertNotNull($entri);
        $this->assertFalse($entri->is_verified);
        $this->assertNull($entri->verified_at);

        // sehingga masih muncul sebagai "belum" pada daftar bawahan Diana
        $this->sebagai($diana)
            ->getJson('/api/mobile/logbook/bawahan?bulan=8&tahun=2026')
            ->assertOk()
            ->assertJsonPath('total_belum', 1);
    }
}
