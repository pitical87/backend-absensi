<?php

namespace Tests\Feature;

use App\Models\PengajuanLembur;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LemburTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

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

    private function pengajuan(array $atribut = [], ?User $pemohon = null): PengajuanLembur
    {
        return PengajuanLembur::create(array_merge([
            'user_id' => ($pemohon ?? $this->pegawai())->id,
            'tanggal' => now()->addDay()->toDateString(),
            'jam_mulai' => '18:00',
            'jam_selesai' => '21:00',
            'durasi_jam' => 3,
            'keterangan' => 'Rekap akhir bulan',
            'status' => 'Menunggu',
            'created_at' => now(),
        ], $atribut));
    }

    public function test_halaman_menampilkan_kategori_dengan_jumlah(): void
    {
        $this->admin();
        $this->pengajuan();
        $this->pengajuan(['status' => 'Disetujui']);

        $this->get(route('admin.lembur.index'))
            ->assertOk()
            ->assertSee('Menunggu')
            ->assertSee('Disetujui')
            ->assertSee('Semua')
            ->assertSee('data-status="Menunggu"', false)
            ->assertSee('Rekap akhir bulan');
    }

    public function test_pencarian_menyaring_nama_nip_dan_keterangan(): void
    {
        $this->admin();
        $this->pengajuan(['keterangan' => 'Rekap akhir bulan'], $this->pegawai('Budi Santoso', '19700101'));
        $this->pengajuan(['keterangan' => 'RapatKoordinator'], $this->pegawai('Siti Aminah', '19900102'));

        $this->get(route('admin.lembur.index', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('Rekap akhir bulan')
            ->assertDontSee('RapatKoordinator');

        $this->get(route('admin.lembur.index', ['q' => '19900102']))
            ->assertOk()
            ->assertSee('RapatKoordinator')
            ->assertDontSee('Rekap akhir bulan');

        $this->get(route('admin.lembur.index', ['q' => 'rekap']))
            ->assertOk()
            ->assertSee('Rekap akhir bulan')
            ->assertDontSee('RapatKoordinator');
    }

    public function test_endpoint_data_mengembalikan_tabel_dan_jumlah(): void
    {
        $this->admin();
        $this->pengajuan();
        $this->pengajuan(['status' => 'Ditolak']);

        $this->getJson(route('admin.lembur.data', ['status' => 'Menunggu', 'q' => 'rekap']))
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('status', 'Menunggu')
            ->assertJsonPath('q', 'rekap')
            ->assertJsonPath('jumlah.Menunggu', 1)
            ->assertJsonPath('jumlah.Ditolak', 1);
    }

    public function test_pagination_dan_filter_bersamaan(): void
    {
        $this->admin();
        for ($i = 1; $i <= 16; $i++) {
            $this->pengajuan(['keterangan' => 'Laporan '.$i]);
        }

        $respons = $this->getJson(route('admin.lembur.data', ['status' => 'Menunggu']));
        $respons->assertOk()->assertJsonPath('total', 16)->assertJsonPath('hal', 1)->assertJsonPath('totalHal', 2);
        $this->assertStringContainsString('Laporan 16', $respons->json('html'));

        $halaman2 = $this->getJson(route('admin.lembur.data', ['hal' => 2]));
        $halaman2->assertOk()->assertJsonPath('hal', 2)->assertJsonPath('total', 16);
        $this->assertStringContainsString('Laporan 1<', $halaman2->json('html'));
    }

    public function test_halaman_diisi_tidak_aktif_menonaktifkan_aksi(): void
    {
        $this->admin();
        Pengaturan::create(['kunci' => 'aktifkan_lembur', 'nilai' => '0']);
        $this->pengajuan();

        $this->get(route('admin.lembur.index'))
            ->assertOk()
            ->assertSee('Modul lembur sedang', false)
            ->assertSee('modul lembur tidak aktif');

        $this->postJson(route('admin.lembur.proses'), ['id' => 1, 'putusan' => 'setuju'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('pesan', 'Modul lembur sedang tidak aktif. Nyalakan lewat Pengaturan terlebih dahulu.');

        $this->assertDatabaseHas('pengajuan_lembur', ['id' => 1, 'status' => 'Menunggu']);
    }

    public function test_setujui_dan_tolak_lewat_json(): void
    {
        $admin = $this->admin();
        $satu = $this->pengajuan();
        $dua = $this->pengajuan();

        $this->postJson(route('admin.lembur.proses'), ['id' => $satu->id, 'putusan' => 'setuju', 'status' => 'Menunggu'])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('jumlah.Menunggu', 1);

        $this->postJson(route('admin.lembur.proses'), ['id' => $dua->id, 'putusan' => 'tolak', 'catatan' => 'Beban kerja', 'status' => 'Menunggu'])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('jumlah.Menunggu', 0);

        $this->assertDatabaseHas('pengajuan_lembur', ['id' => $satu->id, 'status' => 'Disetujui', 'diproses_oleh' => $admin->id]);
        $this->assertDatabaseHas('pengajuan_lembur', ['id' => $dua->id, 'status' => 'Ditolak', 'catatan_keputusan' => 'Beban kerja']);
    }

    public function test_tanpa_tindakan_menolak_proses(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan();

        $this->postJson(route('admin.lembur.proses'), ['id' => $pengajuan->id])
            ->assertStatus(422)
            ->assertJsonPath('pesan', 'Pilih tindakan Setujui atau Tolak lebih dulu.');

        $this->assertDatabaseHas('pengajuan_lembur', ['id' => $pengajuan->id, 'status' => 'Menunggu']);
    }

    public function test_aksi_menampilkan_tabel_yang_disaring(): void
    {
        $this->admin();
        $cocok = $this->pengajuan(['keterangan' => 'Rekap akhir bulan']);
        $ini = $this->pengajuan(['keterangan' => 'Lain lain']);

        $respons = $this->postJson(route('admin.lembur.proses'), [
            'id' => $cocok->id,
            'putusan' => 'setuju',
            'q' => 'rekap',
            'status' => 'Menunggu',
        ])->assertOk();

        $respons->assertJsonPath('q', 'rekap')->assertJsonPath('status', 'Menunggu')->assertJsonPath('jumlah.Menunggu', 1);
        $this->assertStringContainsString('Tidak ada pengajuan lembur berstatus Menunggu yang cocok dengan', $respons->json('html'));
        $this->assertStringNotContainsString('Lain lain', $respons->json('html'));
        $this->assertDatabaseHas('pengajuan_lembur', ['id' => $ini->id, 'status' => 'Menunggu']);
    }

    public function test_proses_tanpa_json_tetap_redirect(): void
    {
        $this->admin();
        $pengajuan = $this->pengajuan();

        $this->post(route('admin.lembur.proses'), ['id' => $pengajuan->id, 'putusan' => 'tolak'])
            ->assertRedirect(route('admin.lembur.index'))
            ->assertSessionHas('success');
    }

    public function test_halaman_bukan_admin_diarahkan(): void
    {
        $pegawai = $this->pegawai();
        $this->withSession(['uid' => (int) $pegawai->id, 'role' => 'pegawai', 'nama' => 'Pegawai Satu']);

        $this->get(route('admin.lembur.index'))->assertRedirect();
        $this->getJson(route('admin.lembur.data'))->assertStatus(302);
    }
}
