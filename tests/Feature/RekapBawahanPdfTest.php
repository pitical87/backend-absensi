<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapBawahanPdfTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => uniqid('user_', true).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

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

    public function test_atasan_dapat_mengunduh_pdf_rekap_multi_bawahan_satu_halaman_per_pegawai(): void
    {
        $unit = UnitKerja::create(['nama' => 'Penunjang dan Sarana Prasana', 'punya_sub' => true]);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'SIMRS']);

        $atasan = $this->buatUser([
            'nama_lengkap' => 'Firmansyah Diana',
            'email' => 'atasan@contoh.test',
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subUnit->id,
        ]);
        $bawahan1 = $this->buatUser([
            'nama_lengkap' => 'Firman',
            'email' => 'firman@contoh.test',
            'nip' => '199001012020011001',
            'unit_kerja_id' => $unit->id,
        ]);
        $bawahan2 = $this->buatUser([
            'nama_lengkap' => 'Rina Wijaya',
            'email' => 'rina@contoh.test',
            'nip' => '199202022021022002',
            'unit_kerja_id' => $unit->id,
        ]);

        AtasanLangsung::create(['user_id' => $bawahan1->id, 'atasan_id' => $atasan->id]);
        AtasanLangsung::create(['user_id' => $bawahan2->id, 'atasan_id' => $atasan->id]);

        Absensi::create([
            'user_id' => $bawahan1->id,
            'tanggal' => '2026-10-05',
            'waktu_masuk' => '2026-10-05 08:02:00',
            'waktu_pulang' => '2026-10-05 14:05:00',
            'status_masuk' => 'Tepat Waktu',
            'menit_terlambat' => 0,
            'total_menit_kerja' => 363,
        ]);

        // Uji bulan 31 hari (Oktober) dengan 2 pegawai => harus tepat 2 halaman (1 halaman per user)
        $response = $this->sebagai($atasan)
            ->get('/api/mobile/rekap/bawahan/pdf?bulan=10&tahun=2026&user_ids='.$bawahan1->id.','.$bawahan2->id);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="rekap-bulanan-2026-10.pdf"');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        // Dompdf menulis "/Type /Page" (bukan "/Type /Pages") untuk tiap halaman
        $jumlahHalaman = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
        $this->assertSame(2, $jumlahHalaman, 'Setiap pegawai harus menempati tepat 1 halaman PDF.');
    }

    public function test_menolak_akses_jika_memilih_pegawai_bukan_bawahan(): void
    {
        $atasan = $this->buatUser(['nama_lengkap' => 'Atasan A']);
        $orangLain = $this->buatUser(['nama_lengkap' => 'Bukan Bawahan']);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=11&tahun=2026&user_ids='.$orangLain->id)
            ->assertStatus(403)
            ->assertJsonPath('sukses', false);
    }

    public function test_menolak_jika_user_ids_kosong_atau_bulan_tidak_valid(): void
    {
        $atasan = $this->buatUser(['nama_lengkap' => 'Atasan B']);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=11&tahun=2026')
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=13&tahun=2026&user_ids='.$atasan->id)
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }
}
