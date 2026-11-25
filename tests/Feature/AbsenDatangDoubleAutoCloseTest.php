<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\JadwalShift;
use App\Models\Pengaturan;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\User;
use App\Services\AbsenService;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenDatangDoubleAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -8.4932;

    private const LNG = 140.4018;

    protected function setUp(): void
    {
        parent::setUp();

        Pengaturan::updateOrCreate(['kunci' => 'lokasi_lat'], ['nilai' => (string) self::LAT]);
        Pengaturan::updateOrCreate(['kunci' => 'lokasi_lng'], ['nilai' => (string) self::LNG]);
        Pengaturan::updateOrCreate(['kunci' => 'radius_meter'], ['nilai' => '200']);
        Pengaturan::updateOrCreate(['kunci' => 'batas_awal_absen_menit'], ['nilai' => '60']);
    }

    private function buatPegawai(string $profesiNama = 'Perawat'): array
    {
        $prof = Profesi::firstOrCreate(['nama' => $profesiNama]);
        $shift = Shift::create([
            'kategori' => 'Pagi',
            'jam_masuk' => '08:00:00',
            'jam_pulang' => '14:00:00',
            'aktif' => 1,
        ]);
        $user = User::create([
            'nama_lengkap' => 'Pegawai '.$profesiNama,
            'email' => uniqid('abs_', true).'@example.com',
            'password_hash' => bcrypt('rahasia'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'profesi_id' => $prof->id,
        ]);

        return [$user, $shift, [
            'id' => (int) $user->id,
            'shift_id' => (int) $shift->id,
            'shift_kategori' => 'Pagi',
            'shift_jam_masuk' => '08:00',
            'shift_jam_pulang' => '14:00',
            'role' => 'pegawai',
            'profesi_nama' => $profesiNama,
        ]];
    }

    public function test_absen_datang_ganda_di_hari_sama_tidak_menutup_otomatis_waktu_pulang(): void
    {
        [$user, , $u] = $this->buatPegawai('Perawat');
        $service = app(AbsenService::class);
        $now1 = new DateTime('2026-11-25 08:02:00');
        $now2 = new DateTime('2026-11-25 08:02:05');

        $res1 = json_decode($service->absenDatang($u, self::LAT, self::LNG, 15, $now1, null, false, [])->getContent(), true);
        $this->assertTrue($res1['sukses']);

        // Simulasi double-tap / retry absen masuk pada hari yang sama
        $res2 = json_decode($service->absenDatang($u, self::LAT, self::LNG, 15, $now2, null, false, [])->getContent(), true);
        $this->assertFalse($res2['sukses']);

        $absensi = Absensi::where('user_id', $user->id)->whereDate('tanggal', '2026-11-25')->first();
        $this->assertNotNull($absensi);
        $this->assertNull($absensi->waktu_pulang, 'waktu_pulang hari ini tidak boleh terisi otomatis oleh retry absen masuk');
    }

    public function test_absen_datang_hari_ini_tetap_menutup_otomatis_absensi_kemarin_yang_lupa_pulang(): void
    {
        [$user, $shift, $u] = $this->buatPegawai('Perawat');
        $shiftSore = Shift::create([
            'kategori' => 'Sore',
            'jam_masuk' => '14:00:00',
            'jam_pulang' => '20:00:00',
            'aktif' => 1,
        ]);
        // Kemarin shift sore (pulang 20:00), hari ini shift pagi (pulang 14:00)
        JadwalShift::create(['user_id' => $user->id, 'shift_id' => $shiftSore->id, 'tanggal_berlaku' => '2026-11-24']);
        JadwalShift::create(['user_id' => $user->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => '2026-11-25']);

        $kemarinId = Absensi::insertGetId([
            'user_id' => $user->id,
            'sesi' => 1,
            'tanggal' => '2026-11-24',
            'waktu_masuk' => '2026-11-24 14:05:00',
            'status_masuk' => 'Tepat Waktu',
            'menit_terlambat' => 0,
            'bintang_masuk' => 5,
            'bintang_harian' => 5,
        ]);

        $service = app(AbsenService::class);
        $now = new DateTime('2026-11-25 08:00:00');
        $res = json_decode($service->absenDatang($u, self::LAT, self::LNG, 15, $now, null, false, [])->getContent(), true);
        $this->assertTrue($res['sukses']);

        $kemarin = Absensi::find($kemarinId);
        $this->assertSame('2026-11-24 20:00:00', $kemarin->waktu_pulang->format('Y-m-d H:i:s'));

        $hariIni = Absensi::where('user_id', $user->id)->whereDate('tanggal', '2026-11-25')->first();
        $this->assertNotNull($hariIni);
        $this->assertNull($hariIni->waktu_pulang);
    }

    public function test_dokter_absen_datang_saat_sesi_aktif_hari_ini_tidak_menutup_otomatis_sesi_aktif(): void
    {
        [$dokter, , $u] = $this->buatPegawai('Dokter');
        $service = app(AbsenService::class);

        $res1 = json_decode($service->absenDatang($u, self::LAT, self::LNG, 15, new DateTime('2026-11-25 08:00:00'), null, false, [])->getContent(), true);
        $this->assertTrue($res1['sukses']);

        $res2 = json_decode($service->absenDatang($u, self::LAT, self::LNG, 15, new DateTime('2026-11-25 08:01:00'), null, false, [])->getContent(), true);
        $this->assertFalse($res2['sukses']);

        $absensi = Absensi::where('user_id', $dokter->id)->whereDate('tanggal', '2026-11-25')->first();
        $this->assertNotNull($absensi);
        $this->assertNull($absensi->waktu_pulang);
    }
}
