<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Profesi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class StatusPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => uniqid('status_', true).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'status_pegawai' => 'Non-PNS',
            'email_verified_at' => now(),
        ], $atribut));
    }

    private function sebagaiMobile(User $user): self
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

    public function test_profil_mobile_dapat_memperbarui_status_pegawai_pns_non_pns_honor_blud(): void
    {
        $user = $this->buatUser(['nama_lengkap' => 'Firman', 'email' => 'firman@contoh.test']);

        foreach (['Honor', 'BLUD', 'PNS', 'Non-PNS'] as $status) {
            $this->sebagaiMobile($user)
                ->postJson('/api/mobile/profil', [
                    'nama_lengkap' => 'Firman',
                    'email' => 'firman@contoh.test',
                    'status_pegawai' => $status,
                ])
                ->assertOk()
                ->assertJsonPath('sukses', true)
                ->assertJsonPath('user.status_pegawai', $status);

            $this->assertDatabaseHas('users', [
                'id' => $user->id,
                'status_pegawai' => $status,
            ]);
        }
    }

    public function test_profil_mobile_menolak_status_pegawai_tidak_valid(): void
    {
        $user = $this->buatUser(['nama_lengkap' => 'Firman', 'email' => 'firman2@contoh.test']);

        $this->sebagaiMobile($user)
            ->postJson('/api/mobile/profil', [
                'nama_lengkap' => 'Firman',
                'email' => 'firman2@contoh.test',
                'status_pegawai' => 'Kontrak',
            ])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }

    public function test_admin_dapat_menyimpan_status_honor_dan_blud(): void
    {
        Validator::fakeDnsLookups();

        $admin = $this->buatUser(['role' => 'admin', 'email' => 'admin@contoh.test']);
        $unit = UnitKerja::create(['nama' => 'Unit Uji', 'punya_sub' => false]);
        $profesi = Profesi::create(['nama' => 'Profesi Uji']);

        foreach (['Honor', 'BLUD'] as $status) {
            $email = strtolower($status).'@example.com';
            $this->withSession(['uid' => $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap])
                ->post('admin/pegawai/simpan', [
                    'nama_lengkap' => 'Pegawai '.$status,
                    'email' => $email,
                    'unit_kerja_id' => $unit->id,
                    'profesi_id' => $profesi->id,
                    'jabatan_kategori' => 'Staf/Pelaksana',
                    'posisi' => 'Staf',
                    'status_pegawai' => $status,
                    'password_default' => '1',
                ])
                ->assertRedirect('admin/pegawai');

            $this->assertDatabaseHas('users', [
                'email' => $email,
                'status_pegawai' => $status,
            ]);
        }
    }
}
