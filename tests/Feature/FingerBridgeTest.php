<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\FingerPegawai;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\FingerImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FingerBridgeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.finger@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        return $admin;
    }

    private function pegawai(): User
    {
        return User::create([
            'nama_lengkap' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
    }

    // ── Service: normalisasi & filter tanggal ─────────────────────────

    public function test_bridge_menormalkan_log_dan_menyaring_rentang_tanggal(): void
    {
        Http::fake([
            'http://192.168.0.50:8080/api/logs' => Http::response([
                'status' => 'success',
                'action' => 'getlogs',
                'count' => 3,
                'logs' => [
                    ['user_id' => 12, 'timestamp' => '2026-10-05 08:15:00'],
                    ['user_id' => 12, 'timestamp' => '2026-10-05 17:02:00'],
                    ['user_id' => 12, 'timestamp' => '2026-10-09 07:00:00'],
                ],
            ], 200),
        ]);

        $hasil = app(FingerImportService::class)->ambilDariBridge(
            'http://192.168.0.50:8080', '192.168.0.40', 5005, 0, 1,
            '2026-10-05', '2026-10-05'
        );

        $this->assertSame('success', $hasil['status']);
        $this->assertCount(2, $hasil['scan']);
        $this->assertSame('12', $hasil['scan'][0]['finger_id']);
        $this->assertSame('2026-10-05', $hasil['scan'][0]['tanggal']);
        $this->assertSame('08:15', $hasil['scan'][0]['waktu']);
        $this->assertSame('scan', $hasil['scan'][0]['tipe']);
    }

    public function test_bridge_gagal_terhubung_mengembalikan_status_failed(): void
    {
        Http::fake([
            'http://192.168.0.50:8080/api/logs' => Http::response([
                'status' => 'failed',
                'message' => 'Gagal terhubung ke mesin.',
            ], 200),
        ]);

        $hasil = app(FingerImportService::class)->ambilDariBridge(
            'http://192.168.0.50:8080', '192.168.0.40', 5005, 0, 1,
            '2026-10-05', '2026-10-05'
        );

        $this->assertSame('failed', $hasil['status']);
        $this->assertSame([], $hasil['scan']);
    }

    // ── Controller: impor lewat bridge ────────────────────────────────

    public function test_impor_dari_mesin_memakai_bridge_saat_dikonfigurasi(): void
    {
        $this->admin();
        $pegawai = $this->pegawai();

        FingerPegawai::create(['user_id' => $pegawai->id, 'finger_id' => '12', 'created_at' => now()]);

        Pengaturan::simpan('finger_ip', '192.168.0.40');
        Pengaturan::simpan('finger_port', '5005');
        Pengaturan::simpan('finger_bridge_url', 'http://192.168.0.50:8080');

        Http::fake([
            'http://192.168.0.50:8080/api/logs' => Http::response([
                'status' => 'success',
                'action' => 'getlogs',
                'count' => 2,
                'logs' => [
                    ['user_id' => 12, 'timestamp' => '2026-10-05 08:00:00'],
                    ['user_id' => 12, 'timestamp' => '2026-10-05 17:00:00'],
                ],
            ], 200),
        ]);

        $this->post(route('admin.finger.import.url'), [
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-05',
        ])->assertRedirect(route('admin.finger', ['tab' => 'mesin']))
            ->assertSessionHas('finger_hasil');

        $rec = Absensi::where('user_id', $pegawai->id)->whereDate('tanggal', '2026-10-05')->first();
        $this->assertNotNull($rec);
        $this->assertSame('finger', $rec->sumber);
        $this->assertSame('08:00', $rec->waktu_masuk?->format('H:i'));
        $this->assertSame('17:00', $rec->waktu_pulang?->format('H:i'));

        Http::assertSent(fn ($req) => $req->url() === 'http://192.168.0.50:8080/api/logs'
            && $req['ip'] === '192.168.0.40'
            && $req['port'] === 5005);
    }

    public function test_impor_tanpa_data_scan_menampilkan_error(): void
    {
        $this->admin();

        Pengaturan::simpan('finger_bridge_url', 'http://192.168.0.50:8080');

        Http::fake([
            'http://192.168.0.50:8080/api/logs' => Http::response([
                'status' => 'success',
                'action' => 'getlogs',
                'count' => 0,
                'logs' => [],
            ], 200),
        ]);

        $this->post(route('admin.finger.import.url'), [
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-05',
        ])->assertRedirect(route('admin.finger', ['tab' => 'mesin']))
            ->assertSessionHas('error');
    }

    public function test_cek_koneksi_lewat_bridge(): void
    {
        $this->admin();

        Http::fake([
            'http://192.168.0.50:8080/api/ping' => Http::response([
                'status' => 'success',
                'action' => 'ping',
                'ip' => '192.168.0.40',
                'port' => 5005,
                'latency_ms' => 12.5,
                'message' => 'Port 5005 pada 192.168.0.40 terbuka.',
            ], 200),
        ]);

        $this->postJson(route('admin.finger.cek'), [
            'ip' => '192.168.0.40',
            'port' => 5005,
            'bridge_url' => 'http://192.168.0.50:8080',
        ])->assertOk()
            ->assertJson(['sukses' => true])
            ->assertJsonPath('ms', 13);
    }
}
