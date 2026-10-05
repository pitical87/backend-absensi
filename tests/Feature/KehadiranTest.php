<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\LogLokasi;
use App\Models\Shift;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KehadiranTest extends TestCase
{
    use RefreshDatabase;

    private string $tanggal = '2026-03-10';

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession(['uid' => (int) $admin->id, 'role' => 'admin', 'nama' => 'Admin Uji']);

        return $admin;
    }

    private function pegawai(string $nama, array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => $nama,
            'nip' => '19800101',
            'email' => strtolower(str_replace(' ', '.', $nama)).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    private function absensi(User $u, array $atribut = []): Absensi
    {
        return Absensi::create(array_merge([
            'user_id' => $u->id,
            'tanggal' => $this->tanggal,
            'waktu_masuk' => $this->tanggal.' 07:55:00',
            'waktu_pulang' => $this->tanggal.' 16:05:00',
            'status_masuk' => 'Tepat Waktu',
            'menit_terlambat' => 0,
        ], $atribut));
    }

    public function test_halaman_kehadiran_memuat_semua_target_tombol(): void
    {
        $this->admin();

        $this->get(route('admin.kehadiran.index', ['tanggal' => $this->tanggal]))
            ->assertOk()
            ->assertSee('id="tombol-tambah"', false)
            ->assertSee('id="tombol-peta"', false)
            ->assertSee('id="modal-absen"', false)
            ->assertSee('id="form-absen"', false)
            ->assertSee('id="modal-absen-tutup"', false)
            ->assertSee('id="modal-absen-batal"', false)
            ->assertSee('id="btn-terapkan-semua"', false)
            ->assertSee('id="modal-peta-absen"', false)
            ->assertSee('id="modal-anomali"', false)
            ->assertSee('id="panel-percobaan"', false)
            ->assertSee('id="tbody-percobaan"', false);
    }

    public function test_endpoint_data_mengembalikan_baris_lengkap_dengan_aksi(): void
    {
        $this->admin();
        $u = $this->pegawai('Siti Aminah');
        $a = $this->absensi($u, [
            'waktu_masuk' => $this->tanggal.' 08:20:00',
            'status_masuk' => 'Terlambat',
            'menit_terlambat' => 20,
            'flag_anomali' => true,
            'catatan_anomali' => 'Lokasi jauh dari titik absen',
        ]);

        $html = $this->getJson(route('admin.kehadiran.data', ['tanggal' => $this->tanggal]))
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('jumlah', 1)
            ->json('html');

        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertStringContainsString('Terlambat 20 mnt', $html);
        $this->assertStringContainsString('data-anomali-nama="Siti Aminah"', $html);
        $this->assertStringContainsString('data-anomali-keterangan="Lokasi jauh dari titik absen"', $html);
        $this->assertStringContainsString('data-id="'.$a->id.'"', $html);
        $this->assertStringContainsString('admin/kehadiran/hapus', $html);
        $this->assertStringContainsString('data-masuk="08:20"', $html);
    }

    public function test_endpoint_data_menghormati_pencarian_dan_filter_status(): void
    {
        $this->admin();
        $siti = $this->pegawai('Siti Aminah');
        $budi = $this->pegawai('Budi Santoso', ['nip' => '19900202']);
        $this->absensi($siti, ['status_masuk' => 'Terlambat', 'menit_terlambat' => 12]);
        $this->absensi($budi, ['status_masuk' => 'Tepat Waktu']);

        $this->getJson(route('admin.kehadiran.data', ['tanggal' => $this->tanggal, 'q' => '19900202']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('jumlah', 1);

        $terlambat = $this->getJson(route('admin.kehadiran.data', [
            'tanggal' => $this->tanggal, 'status' => 'terlambat',
        ]))->assertOk();
        $terlambat->assertJsonPath('total', 1);
        $this->assertStringContainsString('Siti Aminah', $terlambat->json('html'));
        $this->assertStringNotContainsString('Budi Santoso', $terlambat->json('html'));
    }

    public function test_endpoint_percobaan_menampilkan_tabel_ditolak(): void
    {
        $this->admin();
        $u = $this->pegawai('Rina Dewi');
        $a = $this->absensi($u);
        LogLokasi::create([
            'user_id' => $u->id,
            'absensi_id' => $a->id,
            'tipe' => 'datang',
            'latitude' => -8.49000,
            'longitude' => 140.40500,
            'jarak_meter' => 320.4,
            'ditolak' => true,
            'waktu' => $this->tanggal.' 07:58:00',
        ]);

        $this->getJson(route('admin.kehadiran.percobaan', ['tanggal' => $this->tanggal]))
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total', 1);

        $html = $this->getJson(route('admin.kehadiran.percobaan', ['tanggal' => $this->tanggal]))->json('html');
        $this->assertStringContainsString('Rina Dewi', $html);
        $this->assertStringContainsString('320 m', $html);
    }

    public function test_label_shift_mengikuti_jadwal_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Bagian Keuangan']);
        $u = $this->pegawai('Dewi Lestari', ['unit_kerja_id' => $unit->id]);
        $shift = Shift::create([
            'kategori' => 'Pagi', 'jam_masuk' => '07:00', 'jam_pulang' => '15:00',
        ]);
        $u->jadwalShift()->create(['shift_id' => $shift->id, 'tanggal_berlaku' => $this->tanggal]);
        $this->absensi($u);

        $this->get(route('admin.kehadiran.index', ['tanggal' => $this->tanggal]))
            ->assertOk()
            ->assertSee('Dewi Lestari')
            ->assertSee('Pagi (07.00 - 15.00)')
            ->assertSee('Bagian Keuangan');
    }

    public function test_hapus_balasan_json_dan_menghapus_data(): void
    {
        $this->admin();
        $u = $this->pegawai('Toni Saputra');
        $a = $this->absensi($u);

        $this->getJson(route('admin.kehadiran.index', ['tanggal' => $this->tanggal]))->assertOk();

        $this->postJson(route('admin.kehadiran.hapus'), ['id' => $a->id])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $this->assertDatabaseMissing('absensi', ['id' => $a->id]);
    }

    public function test_tambah_absensi_manfaat_melalui_formulir(): void
    {
        $this->admin();
        $u = $this->pegawai('Maya Puspita');

        $this->post(route('admin.kehadiran.simpan'), [
            'user_id' => $u->id,
            'hari' => [
                ['tanggal' => '2026-03-02', 'masuk' => '08:00', 'pulang' => '16:00'],
                ['tanggal' => '2026-03-03', 'masuk' => '', 'pulang' => ''],
                ['tanggal' => '2026-03-04', 'masuk' => '08:30', 'pulang' => '17:00'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('absensi', [
            'user_id' => $u->id, 'tanggal' => '2026-03-02', 'waktu_masuk' => '2026-03-02 08:00:00',
        ]);
        $this->assertDatabaseHas('absensi', [
            'user_id' => $u->id, 'tanggal' => '2026-03-04', 'waktu_masuk' => '2026-03-04 08:30:00',
        ]);
        $this->assertDatabaseMissing('absensi', ['user_id' => $u->id, 'tanggal' => '2026-03-03']);
    }
}
