<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\Izin;
use App\Models\JadwalShift;
use App\Models\Keterlambatan;
use App\Models\Notifikasi;
use App\Models\PengajuanLupaAbsen;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LupaAbsenTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function buatUser(string $nama, string $role = 'pegawai', string $status = 'aktif'): User
    {
        $urut = ++self::$urut;

        return User::create([
            'nama_lengkap' => $nama,
            'nip' => '19900'.(100 + $urut),
            'email' => str($nama)->slug().$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => now(),
        ]);
    }

    private function buatShift(string $kategori = 'Pagi', string $masuk = '07:30:00', string $pulang = '14:00:00', bool $lintas = false): Shift
    {
        return Shift::create([
            'kategori' => $kategori,
            'jam_masuk' => $masuk,
            'jam_pulang' => $pulang,
            'lintas_hari' => $lintas,
            'aktif' => true,
        ]);
    }

    private function sebagai(User $user): self
    {
        $token = ApiToken::create([
            'user_id' => $user->id,
            'token' => 'tok-lupa-'.$user->id.'-'.++self::$urut,
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withUnencryptedCookie('auth_token', $token->token)
            ->withCredentials();
    }

    private function hubungkan(User $bawahan, User $atasan): void
    {
        AtasanLangsung::create(['user_id' => $bawahan->id, 'atasan_id' => $atasan->id]);
    }

    public function test_cek_tidak_menampilkan_tombol_bila_tidak_ada_jadwal(): void
    {
        $pegawai = $this->buatUser('Andi Tanpa Jadwal');

        $this->sebagai($pegawai)
            ->getJson('/api/mobile/lupa-absen/cek')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('ada_jadwal', false)
            ->assertJsonPath('ada_alpa', false)
            ->assertJsonPath('tampil_tombol', false)
            ->assertJsonPath('total_alpa', 0);
    }

    public function test_cek_tidak_menampilkan_tombol_bila_semua_jadwal_hadir_atau_izin(): void
    {
        $pegawai = $this->buatUser('Budi Rajin');
        $shift = $this->buatShift();
        $tgl1 = now()->subDays(3)->toDateString();
        $tgl2 = now()->subDays(2)->toDateString();

        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tgl1]);
        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tgl2]);

        Absensi::create([
            'user_id' => $pegawai->id,
            'tanggal' => $tgl1,
            'waktu_masuk' => $tgl1.' 07:25:00',
            'waktu_pulang' => $tgl1.' 14:05:00',
            'status_masuk' => 'Tepat Waktu',
            'bintang_masuk' => 5,
            'bintang_pulang' => 5,
            'bintang_harian' => 5.0,
        ]);

        Izin::create([
            'user_id' => $pegawai->id,
            'jenis' => 'Sakit',
            'tanggal_mulai' => $tgl2,
            'tanggal_selesai' => $tgl2,
            'keterangan' => 'Demam',
            'status' => 'Disetujui',
            'tahap_aktif' => 0,
            'created_at' => now(),
        ]);

        $this->sebagai($pegawai)
            ->getJson('/api/mobile/lupa-absen/cek')
            ->assertOk()
            ->assertJsonPath('ada_jadwal', true)
            ->assertJsonPath('ada_alpa', false)
            ->assertJsonPath('tampil_tombol', false)
            ->assertJsonPath('total_alpa', 0);
    }

    public function test_cek_menampilkan_tombol_bila_ada_jadwal_dan_alpa_dalam_1_bulan(): void
    {
        $pegawai = $this->buatUser('Citra Lupa');
        $shift = $this->buatShift();
        $tglAlpa = now()->subDays(4)->toDateString();

        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tglAlpa]);

        $this->sebagai($pegawai)
            ->getJson('/api/mobile/lupa-absen/cek')
            ->assertOk()
            ->assertJsonPath('ada_jadwal', true)
            ->assertJsonPath('ada_alpa', true)
            ->assertJsonPath('tampil_tombol', true)
            ->assertJsonPath('total_alpa', 1)
            ->assertJsonPath('tanggal_alpa.0.tanggal', $tglAlpa);
    }

    public function test_ajukan_lupa_absen_mengirim_notifikasi_ke_atasan_langsung_dan_admin(): void
    {
        $admin = $this->buatUser('Admin RSUD', 'admin');
        $atasan = $this->buatUser('Kepala Ruang');
        $pegawai = $this->buatUser('Dedi Staf');
        $this->hubungkan($pegawai, $atasan);

        $shift = $this->buatShift();
        $tglAlpa = now()->subDays(2)->toDateString();
        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tglAlpa]);

        $res = $this->sebagai($pegawai)
            ->postJson('/api/mobile/lupa-absen', [
                'tanggal' => $tglAlpa,
                'alasan' => 'Ponsel tertinggal saat dinas pagi',
            ])
            ->assertCreated()
            ->assertJsonPath('sukses', true);

        $pengajuanId = $res->json('pengajuan_id');
        $this->assertNotNull($pengajuanId);

        // Notifikasi terkirim ke atasan langsung
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $atasan->id,
            'kategori' => 'lupa_absen',
            'url' => 'persetujuan',
        ]);

        // Notifikasi juga terkirim ke admin
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $admin->id,
            'kategori' => 'lupa_absen',
            'url' => 'admin/lupa_absen',
        ]);

        // Atasan melihat badge total menunggu = 1
        $this->sebagai($atasan)
            ->getJson('/api/mobile/lupa-absen/total')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_verifikasi_oleh_atasan_langsung_menyimpan_kehadiran_tanpa_foto_koordinat_dan_bintang_1(): void
    {
        $atasan = $this->buatUser('Atasan Verifikator');
        $pegawai = $this->buatUser('Eka Pegawai');
        $this->hubungkan($pegawai, $atasan);

        $shift = $this->buatShift('Pagi', '07:30:00', '14:00:00');
        $tglAlpa = now()->subDays(3)->toDateString();
        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tglAlpa]);

        $pengajuan = PengajuanLupaAbsen::create([
            'user_id' => $pegawai->id,
            'tanggal' => $tglAlpa,
            'shift_id' => $shift->id,
            'alasan' => 'Lupa absen karena sibuk IGD',
            'status' => 'Menunggu',
            'created_at' => now(),
        ]);

        $this->sebagai($atasan)
            ->postJson('/api/mobile/lupa-absen/proses', [
                'id' => $pengajuan->id,
                'putusan' => 'setuju',
                'catatan' => 'Terverifikasi hadir di ruangan',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('status', 'Disetujui');

        $absensi = Absensi::where('user_id', $pegawai->id)->whereDate('tanggal', $tglAlpa)->first();
        $this->assertNotNull($absensi);
        $this->assertNull($absensi->foto_masuk);
        $this->assertNull($absensi->foto_pulang);
        $this->assertNull($absensi->lat_masuk);
        $this->assertNull($absensi->lng_masuk);
        $this->assertNull($absensi->lat_pulang);
        $this->assertNull($absensi->lng_pulang);
        $this->assertSame(1, $absensi->bintang_masuk);
        $this->assertSame(1, $absensi->bintang_pulang);
        $this->assertEquals(1.0, (float) $absensi->bintang_harian);
        $this->assertSame('lupa_absen', $absensi->sumber);
        $this->assertSame($tglAlpa.' 07:30:00', $absensi->waktu_masuk->format('Y-m-d H:i:s'));
        $this->assertSame($tglAlpa.' 14:00:00', $absensi->waktu_pulang->format('Y-m-d H:i:s'));

        // Tercatat juga pada tabel keterlambatan dengan bintang 1
        $ket = Keterlambatan::where('absensi_id', $absensi->id)->first();
        $this->assertNotNull($ket);
        $this->assertSame(1, (int) $ket->bintang_masuk);
        $this->assertSame(1, (int) $ket->bintang_pulang);
        $this->assertEquals(1.0, (float) $ket->total_bintang);

        // Setelah diverifikasi, tanggal tersebut tidak lagi alpa -> tombol hilang
        $this->sebagai($pegawai)
            ->getJson('/api/mobile/lupa-absen/cek')
            ->assertOk()
            ->assertJsonPath('ada_alpa', false)
            ->assertJsonPath('tampil_tombol', false);

        // Pemohon menerima notifikasi keputusan
        $this->assertTrue(
            Notifikasi::where('user_id', $pegawai->id)
                ->where('kategori', 'lupa_absen')
                ->where('tipe', 'success')
                ->exists()
        );
    }

    public function test_verifikasi_oleh_admin_menyimpan_kehadiran_tanpa_foto_koordinat_dan_bintang_1(): void
    {
        $admin = $this->buatUser('Admin Utama', 'admin');
        $pegawai = $this->buatUser('Fajar Pegawai');
        $shift = $this->buatShift('Malam', '21:00:00', '07:00:00', true);
        $tglAlpa = now()->subDays(5)->toDateString();
        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tglAlpa]);

        $pengajuan = PengajuanLupaAbsen::create([
            'user_id' => $pegawai->id,
            'tanggal' => $tglAlpa,
            'shift_id' => $shift->id,
            'alasan' => 'HP mati saat jaga malam',
            'status' => 'Menunggu',
            'created_at' => now(),
        ]);

        // Halaman admin dapat diakses
        $this->withSession(['uid' => $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap])
            ->get('/admin/lupa_absen')
            ->assertOk()
            ->assertSee('Fajar Pegawai');

        // Admin memverifikasi via POST admin/lupa_absen/proses
        $this->withSession(['uid' => $admin->id, 'role' => 'admin', 'nama' => $admin->nama_lengkap])
            ->postJson('/admin/lupa_absen/proses', [
                'id' => $pengajuan->id,
                'putusan' => 'setuju',
                'catatan' => 'Diverifikasi admin',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $absensi = Absensi::where('user_id', $pegawai->id)->whereDate('tanggal', $tglAlpa)->first();
        $this->assertNotNull($absensi);
        $this->assertNull($absensi->foto_masuk);
        $this->assertNull($absensi->foto_pulang);
        $this->assertNull($absensi->lat_masuk);
        $this->assertNull($absensi->lng_masuk);
        $this->assertSame(1, $absensi->bintang_masuk);
        $this->assertSame(1, $absensi->bintang_pulang);
        $this->assertEquals(1.0, (float) $absensi->bintang_harian);
        // Shift malam lintas hari -> pulang keesokan harinya
        $besok = now()->subDays(4)->toDateString();
        $this->assertSame($besok.' 07:00:00', $absensi->waktu_pulang->format('Y-m-d H:i:s'));
    }

    public function test_penolakan_tidak_menyimpan_kehadiran_dan_pegawai_lain_tidak_berwenang(): void
    {
        $atasan = $this->buatUser('Atasan Asli');
        $orangLain = $this->buatUser('Bukan Atasan');
        $pegawai = $this->buatUser('Gita Pegawai');
        $this->hubungkan($pegawai, $atasan);

        $shift = $this->buatShift();
        $tglAlpa = now()->subDays(2)->toDateString();
        JadwalShift::create(['user_id' => $pegawai->id, 'shift_id' => $shift->id, 'tanggal_berlaku' => $tglAlpa]);

        $pengajuan = PengajuanLupaAbsen::create([
            'user_id' => $pegawai->id,
            'tanggal' => $tglAlpa,
            'shift_id' => $shift->id,
            'alasan' => 'Lupa absen',
            'status' => 'Menunggu',
            'created_at' => now(),
        ]);

        // Orang lain ditolak 403
        $this->sebagai($orangLain)
            ->postJson('/api/mobile/lupa-absen/proses', [
                'id' => $pengajuan->id,
                'putusan' => 'setuju',
            ])
            ->assertForbidden();

        // Atasan menolak -> tidak ada absensi tercipta
        $this->sebagai($atasan)
            ->postJson('/api/mobile/lupa-absen/proses', [
                'id' => $pengajuan->id,
                'putusan' => 'tolak',
                'catatan' => 'Tidak masuk kerja pada tanggal tersebut',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Ditolak');

        $this->assertDatabaseMissing('absensi', [
            'user_id' => $pegawai->id,
            'tanggal' => $tglAlpa,
        ]);
    }

    public function test_hak_akses_me_menyertakan_verifikasi_lupa_absen(): void
    {
        $atasan = $this->buatUser('Atasan Dengan Bawahan');
        $pegawai = $this->buatUser('Hadi Bawahan');
        $this->hubungkan($pegawai, $atasan);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('hak_akses.verifikasi_lupa_absen', true);

        $this->sebagai($pegawai)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('hak_akses.verifikasi_lupa_absen', false);
    }
}
