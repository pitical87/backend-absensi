<?php

namespace Tests\Feature;

use App\Models\JadwalShift;
use App\Models\PengajuanJadwal;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPengajuanTest extends TestCase
{
    use RefreshDatabase;

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

    private static int $urut = 0;

    private function pegawai(string $nama = 'Pegawai Satu', ?string $nip = null): User
    {
        $urut = ++self::$urut;

        return User::create([
            'nama_lengkap' => $nama,
            'nip' => $nip ?? '19800'.(100 + $urut),
            'email' => 'pegawai'.$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
    }

    private function shift(): Shift
    {
        return Shift::create(['kategori' => 'Pagi', 'jam_masuk' => '07:00', 'jam_pulang' => '15:00']);
    }

    private function pengajuan(array $atribut = [], ?User $pemohon = null): PengajuanJadwal
    {
        $pemohon ??= $this->pegawai();
        $shift = $this->shift();
        $tanggal = now()->addDay()->toDateString();

        $atribut = array_merge([
            'user_id' => $pemohon->id,
            'tanggal' => $tanggal,
            'jadwal_shift_id' => JadwalShift::create([
                'user_id' => $pemohon->id,
                'shift_id' => $shift->id,
                'tanggal_berlaku' => $tanggal,
            ])->id,
            'shift_baru_id' => $shift->id,
            'alasan' => 'Keperluan keluarga',
            'status' => 'Menunggu',
            'created_at' => now(),
        ], $atribut);

        if (isset($atribut['tanggal'])) {
            JadwalShift::where('user_id', $pemohon->id)->whereDate('tanggal_berlaku', $atribut['tanggal'])->update([
                'tanggal_berlaku' => $atribut['tanggal'],
            ]);
        }

        return PengajuanJadwal::create($atribut);
    }

    public function test_halaman_menampilkan_kategori_dengan_jumlah(): void
    {
        $this->admin();
        $this->pengajuan();
        $this->pengajuan(['status' => 'Disetujui']);

        $this->get(route('admin.jadwal.pengajuan'))
            ->assertOk()
            ->assertSee('Menunggu')
            ->assertSee('Disetujui')
            ->assertSee('Semua')
            ->assertSee('data-status="Menunggu"', false)
            ->assertSee('Keperluan keluarga');
    }

    public function test_pencarian_menyaring_berdasarkan_nama_nip_dan_alasan(): void
    {
        $this->admin();
        $this->pengajuan(['alasan' => 'Keperluan keluarga'], $this->pegawai('Budi Santoso', '19700101'));
        $this->pengajuan(['alasan' => 'Urusan pribadi'], $this->pegawai('Siti Aminah', '19900102'));

        $this->get(route('admin.jadwal.pengajuan', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('Keperluan keluarga')
            ->assertDontSee('Urusan pribadi');

        $this->get(route('admin.jadwal.pengajuan', ['q' => '19900102']))
            ->assertOk()
            ->assertSee('Urusan pribadi')
            ->assertDontSee('Keperluan keluarga');

        $this->get(route('admin.jadwal.pengajuan', ['q' => 'keluarga']))
            ->assertOk()
            ->assertSee('Keperluan keluarga')
            ->assertDontSee('Urusan pribadi');
    }

    public function test_pencarian_tidak_mencari_di_kategori_lain(): void
    {
        $this->admin();
        $this->pengajuan(['alasan' => 'Keperluan keluarga']);
        $this->pengajuan(['status' => 'Disetujui', 'alasan' => 'Urusan pribadi']);

        $this->get(route('admin.jadwal.pengajuan', ['status' => 'Menunggu', 'q' => 'keluarga']))
            ->assertOk()
            ->assertSee('Keperluan keluarga')
            ->assertDontSee('Urusan pribadi');
    }

    public function test_endpoint_data_mengembalikan_tabel_dan_jumlah(): void
    {
        $this->admin();
        $this->pengajuan();
        $this->pengajuan(['status' => 'Ditolak']);

        $this->getJson(route('admin.jadwal.pengajuan.data', ['status' => 'Menunggu']))
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('status', 'Menunggu')
            ->assertJsonPath('jumlah.Menunggu', 1)
            ->assertJsonPath('jumlah.Ditolak', 1)
            ->assertJsonPath('jumlah.Semua', 2);
    }

    public function test_pagination_membatasi_baris_per_halaman(): void
    {
        $this->admin();
        for ($i = 1; $i <= 18; $i++) {
            $this->pengajuan(['alasan' => 'Pengajuan '.$i]);
        }

        $respons = $this->getJson(route('admin.jadwal.pengajuan.data'));
        $respons->assertOk()->assertJsonPath('total', 18)->assertJsonPath('hal', 1)->assertJsonPath('totalHal', 2);

        $this->assertStringContainsString('Pengajuan 18', $respons->json('html'));
        $this->assertStringNotContainsString('Pengajuan 2<', $respons->json('html'));

        $halaman2 = $this->getJson(route('admin.jadwal.pengajuan.data', ['hal' => 2]));
        $halaman2->assertOk()->assertJsonPath('hal', 2);
        $this->assertStringContainsString('Pengajuan 2<', $halaman2->json('html'));
    }

    public function test_status_tidak_valid_kembali_ke_semua(): void
    {
        $this->admin();
        $this->pengajuan();

        $this->getJson(route('admin.jadwal.pengajuan.data', ['status' => 'Ngawur']))
            ->assertOk()
            ->assertJsonPath('status', 'Semua');
    }

    public function test_setujui_lewat_json_memperbarui_tabel(): void
    {
        $admin = $this->admin();
        $pengajuan = $this->pengajuan();

        $respons = $this->postJson(route('admin.jadwal_pengajuan.proses'), [
            'id' => $pengajuan->id,
            'putusan' => 'setuju',
            'catatan' => 'Disetujui admin',
            'status' => 'Menunggu',
        ]);

        $respons->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('jumlah.Menunggu', 0);
        $this->assertDatabaseHas('pengajuan_jadwal', ['id' => $pengajuan->id, 'status' => 'Disetujui', 'diproses_oleh' => $admin->id]);
        $this->assertStringContainsString('Tidak ada pengajuan berstatus Menunggu', $respons->json('html'));
    }

    public function test_tolak_lewat_json(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan();

        $this->postJson(route('admin.jadwal_pengajuan.proses'), [
            'id' => $pengajuan->id,
            'putusan' => 'tolak',
            'status' => 'Menunggu',
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseHas('pengajuan_jadwal', ['id' => $pengajuan->id, 'status' => 'Ditolak']);
    }

    public function test_tanpa_tindakan_menolak_proses(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan();

        $this->postJson(route('admin.jadwal_pengajuan.proses'), ['id' => $pengajuan->id, 'status' => 'Menunggu'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Pilih tindakan Setujui atau Tolak lebih dulu.');

        $this->assertDatabaseHas('pengajuan_jadwal', ['id' => $pengajuan->id, 'status' => 'Menunggu']);
    }

    public function test_pengajuan_sudah_diproses_tidak_bisa_diproses_lagi(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan(['status' => 'Disetujui']);

        $this->postJson(route('admin.jadwal_pengajuan.proses'), ['id' => $pengajuan->id, 'putusan' => 'setuju'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }

    public function test_proses_tanpa_json_tetap_redirect(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan();

        $this->post(route('admin.jadwal_pengajuan.proses'), ['id' => $pengajuan->id, 'putusan' => 'setuju'])
            ->assertRedirect(route('admin.jadwal.pengajuan'))
            ->assertSessionHas('success');
    }

    public function test_halaman_bukan_admin_diarahkan(): void
    {
        $pegawai = $this->pegawai();
        $this->withSession(['uid' => (int) $pegawai->id, 'role' => 'pegawai', 'nama' => 'Pegawai Satu']);

        $this->get(route('admin.jadwal.pengajuan'))->assertRedirect();
        $this->getJson(route('admin.jadwal.pengajuan.data'))->assertStatus(302);
    }
}
