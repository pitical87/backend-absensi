<?php

namespace Tests\Feature;

use App\Mail\PeringatanLoginMencurigakanMail;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Peringatan email "aktivitas mencurigakan" dikirim ke pemilik akun yang masih
 * aktif ketika percobaan login gagalnya lebih dari 5, beserta IP dan perangkatnya.
 */
class PeringatanLoginTest extends TestCase
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

    private function gagalLogin(string $email = 'pegawai.uji@contoh.test'): TestResponse
    {
        return $this->postJson('/api/mobile/login', ['email' => $email, 'password' => 'salah-kota']);
    }

    public function test_tidak_kirim_email_sampai_lima_gagal(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->gagalLogin($user->email)->assertStatus(401);
        }

        Mail::assertNothingSent();
        $this->assertSame(5, LoginAttempt::where('sukses', 0)->count());
    }

    public function test_kirim_email_ke_pemilik_akun_setelah_gagal_melewati_lima(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        // Blokir 10 menit sudah habis, sehingga gelombangnya bisa terulang.
        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin($user->email)->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        $this->assertSame(10, LoginAttempt::where('sukses', 0)->count());

        Mail::assertSentCount(1);
        Mail::assertSent(PeringatanLoginMencurigakanMail::class, function ($mail) use ($user) {
            $this->assertSame([$user->email], array_column($mail->to, 'address'));

            return true;
        });
    }

    public function test_email_peringatan_memuat_ip_dan_perangkat(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        // 5 kegagalan pertama supaya blokir aktif, lalu 1 lagi dari IP/perangkat lain.
        for ($i = 1; $i <= 5; $i++) {
            $this->gagalLogin($user->email)->assertStatus(401);
        }
        $this->travel(11)->minutes();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->withHeaders(['User-Agent' => 'okhttp/4.9.2 (Android 14)'])
            ->gagalLogin($user->email)->assertStatus(401);

        Mail::assertSent(PeringatanLoginMencurigakanMail::class, function ($mail) {
            $ip = array_column($mail->daftarIp, 'ip');
            $perangkat = array_column($mail->daftarPerangkat, 'nama');

            $this->assertContains('203.0.113.77', $ip);
            $this->assertContains('Android', $perangkat);
            $this->assertSame(6, $mail->jumlahGagal);

            return true;
        });
    }

    public function test_tidak_kirim_ulang_saat_percobaan_terus_berlanjut(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin($user->email)->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        $this->travel(1)->days();

        Mail::assertSentCount(1);
    }

    public function test_pendingan_dilepas_setelah_login_berhasil(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin($user->email)->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        Mail::assertSentCount(1);

        // Login berhasil: riwayat gagal dihapus dan pendinginan dilepas.
        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'rahasia-kuat',
        ])->assertStatus(200);

        for ($i = 1; $i <= 6; $i++) {
            $this->travel(11)->minutes();
            $this->gagalLogin($user->email);
        }

        Mail::assertSentCount(2);
    }

    public function test_akun_nonaktif_tidak_dikirim_email(): void
    {
        Mail::fake();
        $user = $this->buatUser(['status' => 'nonaktif']);

        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin($user->email)->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        $this->assertSame(10, LoginAttempt::where('sukses', 0)->count());
        Mail::assertNothingSent();
    }

    public function test_email_belum_verifikasi_tidak_dikirim_email(): void
    {
        Mail::fake();
        $user = $this->buatUser(['email_verified_at' => null]);

        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin($user->email)->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        $this->assertSame(10, LoginAttempt::where('sukses', 0)->count());
        Mail::assertNothingSent();
    }

    public function test_akun_tidak_dikenal_tidak_dikirim_email(): void
    {
        Mail::fake();

        for ($gelombang = 1; $gelombang <= 2; $gelombang++) {
            for ($i = 1; $i <= 5; $i++) {
                $this->gagalLogin('tidak.ada@contoh.test')->assertStatus(401);
            }
            $this->travel(11)->minutes();
        }

        Mail::assertNothingSent();
    }

    public function test_gagal_di_luar_jendela_24_jam_tidak_memicu_email(): void
    {
        Mail::fake();
        $user = $this->buatUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->gagalLogin($user->email)->assertStatus(401);
        }
        $this->travel(11)->minutes();

        // Satu percobaan lagi, tapi jendela 24 jam sudah bergeser cukup jauh
        // sehingga angka gagalnya belum melewati ambang.
        $this->travel(2)->days();

        $this->gagalLogin($user->email)->assertStatus(401);

        Mail::assertNothingSent();
    }
}