<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard dimuat dua tahap: kerangka + skeleton lebih dulu, lalu datanya
 * diambil lewat admin.dashboard.data setelah halaman tampil.
 */
class DashboardLazyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Uji',
            'email' => 'admin.dashboard@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);

        return $admin;
    }

    public function test_halaman_dashboard_hanya_kerangka_dan_skeleton(): void
    {
        $this->admin();

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('id="dashboard-isi"', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('admin\/dashboard\/data', $html);

        // Isi berat belum ada di kerangka.
        $this->assertStringNotContainsString('id="grafik-tren"', $html);
        $this->assertStringNotContainsString('Ketaatan Absen Bulan', $html);
    }

    public function test_endpoint_data_mengembalikan_isi_dashboard(): void
    {
        $this->admin();

        $html = $this->get(route('admin.dashboard.data'))->assertOk()->getContent();

        $this->assertStringContainsString('stat-admin', $html);
        $this->assertStringContainsString('Ketaatan Absen Bulan', $html);
        $this->assertStringContainsString('id="grafik-tren"', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    public function test_pegawai_tanpa_jadwal_dihitung_selalu_hadir(): void
    {
        $this->admin();

        User::create([
            'nama_lengkap' => 'Pegawai Tanpa Jadwal',
            'email' => 'pegawai.dashboard@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        $html = $this->get(route('admin.dashboard.data'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<span class="text-slate-700 truncate">Selalu Hadir<\/span>\s*<span class="ml-auto tabular-nums whitespace-nowrap"><strong>1<\/strong>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<span class="text-slate-700 truncate">Belum Pernah Absen<\/span>\s*<span class="ml-auto tabular-nums whitespace-nowrap"><strong>0<\/strong>/',
            $html
        );
    }

    public function test_endpoint_data_dilindungi_login(): void
    {
        $this->get(route('admin.dashboard.data'))->assertRedirect(route('login'));
    }

    public function test_grafik_tidak_absen_hanya_menghitung_pegawai_terjadwal_bukan_libur(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');
        try {
            $this->admin();

            $shift = Shift::create([
                'kategori' => 'Pagi',
                'jam_masuk' => '08:00:00',
                'jam_pulang' => '14:00:00',
                'lintas_hari' => false,
                'aktif' => true,
            ]);

            // 1. Pegawai A: terjadwal tgl 2, 3, 4; hadir tgl 2, tidak absen tgl 3 (hari libur) & tgl 4 (hari kerja)
            $pegawaiA = User::create([
                'nama_lengkap' => 'Pegawai Terjadwal',
                'email' => 'a@contoh.test',
                'password_hash' => bcrypt('rahasia-kuat'),
                'role' => 'pegawai',
                'status' => 'aktif',
            ]);
            foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $tgl) {
                JadwalShift::create([
                    'user_id' => $pegawaiA->id,
                    'shift_id' => $shift->id,
                    'tanggal_berlaku' => $tgl,
                ]);
            }
            Absensi::create([
                'user_id' => $pegawaiA->id,
                'tanggal' => '2026-11-02',
                'waktu_masuk' => '2026-11-02 07:55:00',
                'waktu_pulang' => '2026-11-02 14:05:00',
                'status_masuk' => 'Tepat Waktu',
                'menit_terlambat' => 0,
            ]);

            // 2. Pegawai B: tidak punya jadwal sama sekali -> tidak pernah dihitung Tidak Absen
            User::create([
                'nama_lengkap' => 'Pegawai Tanpa Jadwal',
                'email' => 'b@contoh.test',
                'password_hash' => bcrypt('rahasia-kuat'),
                'role' => 'pegawai',
                'status' => 'aktif',
            ]);

            // 3. Pegawai C: terjadwal tgl 4 tapi sedang Izin Disetujui -> tidak dihitung Tidak Absen
            $pegawaiC = User::create([
                'nama_lengkap' => 'Pegawai Izin',
                'email' => 'c@contoh.test',
                'password_hash' => bcrypt('rahasia-kuat'),
                'role' => 'pegawai',
                'status' => 'aktif',
            ]);
            JadwalShift::create([
                'user_id' => $pegawaiC->id,
                'shift_id' => $shift->id,
                'tanggal_berlaku' => '2026-11-04',
            ]);
            Izin::create([
                'user_id' => $pegawaiC->id,
                'jenis' => 'Izin',
                'tanggal_mulai' => '2026-11-04',
                'tanggal_selesai' => '2026-11-04',
                'lama_hari' => 1,
                'alasan' => 'Keperluan keluarga',
                'status' => 'Disetujui',
            ]);

            // Tanggal 3 adalah Hari Libur Nasional
            HariLibur::create([
                'tanggal' => '2026-11-03',
                'keterangan' => 'Libur Uji',
            ]);

            $html = $this->get(route('admin.dashboard.data'))->assertOk()->getContent();

            preg_match('/var TREN = (\{.*?\});/s', $html, $match);
            $this->assertNotEmpty($match[1] ?? null, 'Data JSON TREN harus ada di halaman dashboard.');
            $tren = json_decode($match[1], true);

            // Tgl 1 (Minggu, tidak ada jadwal): tidak=0
            // Tgl 2 (A hadir, B tanpa jadwal): tidak=0
            // Tgl 3 (Hari libur nasional): tidak=0
            // Tgl 4 (A terjadwal & tidak absen, B tanpa jadwal, C izin disetujui): tidak=1
            $this->assertSame([1, 2, 3, 4], $tren['labels']);
            $this->assertSame([0, 0, 0, 1], $tren['tidak']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
