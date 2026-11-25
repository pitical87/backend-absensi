<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\MappingSIMRSAccount;
use App\Models\User;
use App\Services\SimrsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MobileMappingSimrsTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => uniqid('simrs_', true).'@contoh.test',
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

        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withUnencryptedCookie('auth_token', $token)
            ->withCredentials();
    }

    public function test_status_cek_simpan_dan_tes_mapping_simrs(): void
    {
        $user = $this->buatUser(['nama_lengkap' => 'Firman']);

        $mockSimrs = Mockery::mock(SimrsService::class);
        $mockSimrs->shouldReceive('cekMapping')
            ->with('9101010101010001')
            ->twice()
            ->andReturn([
                'sukses' => true,
                'data' => ['nik' => '9101010101010001', 'nama' => 'FIRMAN'],
            ]);
        $this->app->instance(SimrsService::class, $mockSimrs);

        // 1. Awalnya belum termapping
        $this->sebagai($user)
            ->getJson('/api/mobile/mapping-simrs')
            ->assertOk()
            ->assertJson([
                'sukses' => true,
                'termapping' => false,
                'simrs_user_id' => null,
            ]);

        // 2. Cek ID SIMRS
        $this->sebagai($user)
            ->getJson('/api/mobile/mapping-simrs/cek?id=9101010101010001')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.nama', 'FIRMAN');

        // 3. Simpan mapping
        $this->sebagai($user)
            ->postJson('/api/mobile/mapping-simrs', [
                'simrs_user_id' => '9101010101010001',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('simrs_user_id', '9101010101010001');

        $this->assertDatabaseHas('mapping_simrs_accounts', [
            'user_id' => $user->id,
            'simrs_user_id' => '9101010101010001',
        ]);

        // 4. Status kini termapping & tes koneksi berhasil
        $this->sebagai($user)
            ->getJson('/api/mobile/mapping-simrs')
            ->assertOk()
            ->assertJson([
                'sukses' => true,
                'termapping' => true,
                'simrs_user_id' => '9101010101010001',
            ]);

        $this->sebagai($user)
            ->getJson('/api/mobile/mapping-simrs/tes')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('data.nik', '9101010101010001');
    }

    public function test_menolak_id_simrs_yang_sudah_dipakai_pegawai_lain(): void
    {
        $user1 = $this->buatUser(['nama_lengkap' => 'Pegawai Satu']);
        $user2 = $this->buatUser(['nama_lengkap' => 'Pegawai Dua']);

        MappingSIMRSAccount::create([
            'user_id' => $user1->id,
            'simrs_user_id' => 'NIK-DUP-001',
        ]);

        $this->sebagai($user2)
            ->getJson('/api/mobile/mapping-simrs/cek?id=NIK-DUP-001')
            ->assertOk()
            ->assertJsonPath('sukses', false);

        $this->sebagai($user2)
            ->postJson('/api/mobile/mapping-simrs', [
                'simrs_user_id' => 'NIK-DUP-001',
            ])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }
}
