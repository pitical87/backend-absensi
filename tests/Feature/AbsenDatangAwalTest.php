<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Shift;
use App\Models\User;
use App\Services\AbsenService;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenDatangAwalTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function pengaturanDasar(): void
    {
        simpan_pengaturan('lokasi_lat', '-8.4991120');
        simpan_pengaturan('lokasi_lng', '140.4049840');
        simpan_pengaturan('radius_meter', '100');
        simpan_pengaturan('toleransi_menit', '5');
        simpan_pengaturan('minggu_libur', '0');
    }

    private function pegawai(string $kategori = 'Pagi'): array
    {
        $urut = ++self::$urut;

        $user = User::create([
            'nama_lengkap' => 'Pegawai '.$urut,
            'nip' => '19800'.(100 + $urut),
            'email' => 'pegawai'.$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $shift = Shift::create([
            'kategori'   => $kategori,
            'jam_masuk'  => '08:00',
            'jam_pulang' => '16:00',
            'aktif'      => 1,
        ]);

        $user->jadwalShift()->create([
            'shift_id'         => $shift->id,
            'tanggal_berlaku'  => now()->toDateString(),
            'diubah_oleh'      => $user->id,
        ]);

        return [
            'id'                => (int) $user->id,
            'shift_id'          => (int) $shift->id,
            'shift_kategori'    => $shift->kategori,
            'shift_jam_masuk'   => '08:00',
            'shift_jam_pulang'  => '16:00',
            'role'              => 'pegawai',
            'profesi_nama'      => 'Perawat',
        ];
    }

    private function absenDatang(array $u, string $jam): array
    {
        $respons = app(AbsenService::class)->absenDatang(
            $u,
            -8.4991120,
            140.4049840,
            12.0,
            new DateTime(now()->toDateString().' '.$jam),
            null,
            false,
            []
        );

        return (array) $respons->getData(true);
    }

    public function test_datang_lebih_awal_dari_batas_ditolak(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $hasil = $this->absenDatang($u, '06:00');

        $this->assertFalse($hasil['sukses']);
        $this->assertStringContainsString('belum sesuai jadwal', $hasil['pesan']);
        $this->assertStringContainsString('2 jam lebih awal', $hasil['keterangan']);
        $this->assertSame(0, Absensi::count());
    }

    public function test_datang_dalam_batas_diterima_dan_dapat_lima_bintang(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $hasil = $this->absenDatang($u, '07:30');

        $this->assertTrue($hasil['sukses']);
        $this->assertSame(5, $hasil['bintang']);
        $this->assertSame('Tepat Waktu', $hasil['status']);
        $this->assertSame(1, Absensi::count());
    }

    public function test_tepat_di_batas_batas_atas_diterima(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $this->assertTrue($this->absenDatang($u, '07:00')['sukses']);
    }

    public function test_satu_menit_di_luar_batas_ditolak(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $hasil = $this->absenDatang($u, '06:59');

        $this->assertFalse($hasil['sukses']);
        $this->assertSame(0, Absensi::count());
    }

    public function test_batas_nol_menolak_absen_sebelum_jam_masuk(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '0');
        $u = $this->pegawai();

        $hasil = $this->absenDatang($u, '07:59');

        $this->assertFalse($hasil['sukses']);
        $this->assertStringContainsString('tepat pada jam masuk', $hasil['keterangan']);
        $this->assertSame(0, Absensi::count());
    }

    public function test_batas_nol_masih_menerima_tepat_jam_masuk(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '0');
        $u = $this->pegawai();

        $this->assertTrue($this->absenDatang($u, '08:00')['sukses']);
    }

    public function test_batas_bawaan_saat_kunci_tidak_ada_adalah_enam_puluh_menit(): void
    {
        $this->pengaturanDasar();
        pengaturan('batas_awal_absen_menit', 60);

        $u = $this->pegawai();

        $this->assertFalse($this->absenDatang($u, '06:59')['sukses']);
        $this->assertTrue($this->absenDatang($u, '07:00')['sukses']);
    }

    public function test_penolakan_mencatat_log_lokasi_ditolak(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $this->absenDatang($u, '06:00');

        $this->assertDatabaseHas('log_lokasi', [
            'user_id' => $u['id'],
            'tipe'    => 'datang',
            'ditolak' => 1,
        ]);
    }

    public function test_penolakan_tidak_menutup_kehadiran_sebelumnya(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');
        $u = $this->pegawai();

        $kemis = now()->subDay()->toDateString();
        Absensi::create([
            'user_id'       => $u['id'],
            'sesi'          => 1,
            'tanggal'       => $kemis,
            'waktu_masuk'   => $kemis.' 08:00:00',
            'waktu_pulang'  => null,
        ]);

        $this->assertFalse($this->absenDatang($u, '06:00')['sukses']);

        $this->assertNull(Absensi::where('tanggal', $kemis)->first()->waktu_pulang);
    }

    private function pegawaiMalam(): array
    {
        $user = User::create([
            'nama_lengkap' => 'Pegawai Malam',
            'nip'          => '19800999',
            'email'        => 'malam@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role'         => 'pegawai',
            'status'       => 'aktif',
            'email_verified_at' => now(),
        ]);

        $shift = Shift::create([
            'kategori'    => 'Malam',
            'jam_masuk'   => '20:00',
            'jam_pulang'  => '08:00',
            'lintas_hari' => 1,
            'aktif'       => 1,
        ]);

        $user->jadwalShift()->create([
            'shift_id'        => $shift->id,
            'tanggal_berlaku' => now()->toDateString(),
            'diubah_oleh'     => $user->id,
        ]);

        return [
            'id'               => (int) $user->id,
            'shift_id'         => (int) $shift->id,
            'shift_kategori'   => 'Malam',
            'shift_jam_masuk'  => '20:00',
            'shift_jam_pulang' => '08:00',
            'role'             => 'pegawai',
            'profesi_nama'     => 'Perawat',
        ];
    }

    public function test_shift_malam_ditolak_bila_masuk_terlalu_awal(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');

        $hasil = $this->absenDatang($this->pegawaiMalam(), '15:00');

        $this->assertFalse($hasil['sukses']);
        $this->assertStringContainsString('belum sesuai jadwal', $hasil['pesan']);
        $this->assertStringContainsString('5 jam lebih awal', $hasil['keterangan']);
        $this->assertSame(0, Absensi::count());
    }

    public function test_shift_malam_dalam_batas_diterima(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');

        $hasil = $this->absenDatang($this->pegawaiMalam(), '19:00');

        $this->assertTrue($hasil['sukses']);
        $this->assertSame(5, $hasil['bintang']);
    }

    public function test_shift_malam_setelah_tengah_malam_tidak_terkena_batas_awal(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');

        $hasil = $this->absenDatang($this->pegawaiMalam(), '05:00');

        $this->assertTrue($hasil['sukses'], json_encode($hasil));
        $this->assertSame(1, Absensi::count());
    }

    public function test_dokter_tidak_terkena_batas_awal(): void
    {
        $this->pengaturanDasar();
        simpan_pengaturan('batas_awal_absen_menit', '60');

        $user = User::create([
            'nama_lengkap' => 'Dokter Uji',
            'nip'          => '19800777',
            'email'        => 'dokter@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role'         => 'pegawai',
            'status'       => 'aktif',
            'email_verified_at' => now(),
        ]);

        $u = [
            'id'               => (int) $user->id,
            'shift_id'         => null,
            'shift_kategori'   => null,
            'shift_jam_masuk'  => null,
            'shift_jam_pulang' => null,
            'role'             => 'pegawai',
            'profesi_nama'     => 'Dokter',
        ];

        $respons = app(AbsenService::class)->absenDatang(
            $u, -8.4991120, 140.4049840, 12.0,
            new DateTime(now()->toDateString().' 06:00'),
            null, false, []
        );
        $hasil = (array) $respons->getData(true);

        $this->assertTrue($hasil['sukses']);
    }
}