<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapSayaPdfTest extends TestCase
{
    use RefreshDatabase;

    private function sebagai(User $user): self
    {
        $token = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        return $this->withUnencryptedCookie('auth_token', $token)->withCredentials();
    }

    public function test_pegawai_dapat_unduh_pdf_rekap_kehadiran_pribadi(): void
    {
        $atasan = User::create([
            'nama_lengkap' => 'Kepala Unit Uji',
            'nip' => '198001012005011001',
            'email' => 'atasan-rekap@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        $pegawai = User::create([
            'nama_lengkap' => 'Firmansyah Diana',
            'email' => 'firman-rekap@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        AtasanLangsung::create([
            'user_id' => $pegawai->id,
            'atasan_id' => $atasan->id,
        ]);

        Logbook::create([
            'user_id' => $pegawai->id,
            'tanggal' => '2026-11-10',
            'jam' => '08:30:00',
            'isi' => 'Monitoring layanan aplikasi absensi',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $atasan->id,
        ]);

        $res = $this->sebagai($pegawai)
            ->get('/api/mobile/rekap/pdf?bulan=11&tahun=2026')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('Content-Type'));
        $pdf = $res->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $jumlahHalaman = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
        $this->assertSame(2, $jumlahHalaman, 'Rekap pribadi harus menghasilkan 1 halaman absen + 1 halaman logbook.');
    }

    public function test_menolak_bulan_tidak_valid(): void
    {
        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => 'uji-rekap@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        $this->sebagai($pegawai)
            ->getJson('/api/mobile/rekap/pdf?bulan=15&tahun=2026')
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }
}
