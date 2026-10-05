<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IzinTest extends TestCase
{
    use RefreshDatabase;

    private ?User $pemohonLain = null;

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

    private function pengajuan(array $atribut = [], ?User $pemohon = null): Izin
    {
        $pemohon ??= User::create([
            'nama_lengkap' => 'Pegawai Satu',
            'email' => 'p1@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        return Izin::create(array_merge([
            'user_id' => $pemohon->id,
            'jenis' => 'Sakit',
            'tanggal_mulai' => now()->addDay()->toDateString(),
            'tanggal_selesai' => now()->addDays(2)->toDateString(),
            'keterangan' => 'Demam',
            'status' => 'Menunggu',
        ], $atribut));
    }

    // ── TAMPILAN & TAB ─────────────────────────────────────────────────

    public function test_halaman_menampilkan_tab_status_dengan_jumlah(): void
    {
        $this->admin();
        $this->pengajuan();
        $this->pengajuan(['status' => 'Disetujui'], $this->pemohonLain());

        $this->get(route('admin.izin.index'))
            ->assertOk()
            ->assertSee('Menunggu')
            ->assertSee('Disetujui')
            ->assertSee('Semua')
            ->assertSee('data-status="Menunggu"', false)
            ->assertSee('Demam');

        $this->assertStringContainsString('chip aktif', $this->get(route('admin.izin.index'))->getContent());
    }

    public function test_tab_menunggu_menampilkan_pengajuan_yang_menunggu(): void
    {
        $this->admin();
        $menunggu = $this->pengajuan();
        $selesai = $this->pengajuan(['status' => 'Disetujui'], $this->pemohonLain());

        $html = $this->get(route('admin.izin.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Demam', $html);
        $this->assertStringNotContainsString('id="'.$selesai->id.'"', $html);
        $this->assertStringContainsString('name="id" value="'.(int) $menunggu->id.'"', $html);
    }

    public function test_status_tidak_valid_kembali_ke_menunggu(): void
    {
        $this->admin();

        $this->get(route('admin.izin.index').'?status=ApaSaja')
            ->assertOk()
            ->assertSee('Tidak ada pengajuan berstatus Menunggu.');
    }

    // ── ENDPOINT ASINKRON ──────────────────────────────────────────────

    public function test_endpoint_data_membalas_tabs_dan_isi(): void
    {
        $this->admin();
        $this->pengajuan();

        $json = $this->getJson(route('admin.izin.data').'?status=Menunggu')
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('status', 'Menunggu')
            ->json();

        $this->assertStringContainsString('data-status="Menunggu"', $json['tabs']);
        $this->assertStringContainsString('data-status="Semua"', $json['tabs']);
        $this->assertStringContainsString('Demam', $json['isi']);
    }

    public function test_endpoint_data_mengikuti_perubahan_status(): void
    {
        $this->admin();
        $this->pengajuan();
        $disetujui = $this->pengajuan(['status' => 'Disetujui', 'keterangan' => 'Sudah disetujui'], $this->pemohonLain());

        $isi = $this->getJson(route('admin.izin.data').'?status=Disetujui')->assertOk()->json('isi');

        $this->assertStringContainsString('Sudah disetujui', $isi);
        $this->assertStringContainsString('Disetujui', $isi);
        $this->assertNotEmpty($disetujui);
    }

    public function test_endpoint_data_menampilkan_tahapan_berjenjang(): void
    {
        $this->admin();
        $izin = $this->pengajuan(['jenis' => 'Izin', 'tahap_aktif' => 1]);
        IzinPersetujuan::create(['pengajuan_id' => $izin->id, 'tahap' => 1, 'posisi_tahap' => 'Kepala Seksi', 'status' => 'Menunggu']);

        $isi = $this->getJson(route('admin.izin.data').'?status=Menunggu')->assertOk()->json('isi');

        $this->assertStringContainsString('Ambil Alih: Setujui', $isi);
        $this->assertStringContainsString('Ambil Alih: Tolak', $isi);
        $this->assertStringNotContainsString('>Setujui</button>', $isi);
    }

    public function test_endpoint_data_menampilkan_tindakan_satu_tahap(): void
    {
        $this->admin();
        $this->pengajuan(['jenis' => 'Sakit']);

        $isi = $this->getJson(route('admin.izin.data').'?status=Menunggu')->assertOk()->json('isi');

        $this->assertStringContainsString('data-izin="proses"', $isi);
        $this->assertStringContainsString('value="setuju"', $isi);
        $this->assertStringContainsString('value="tolak"', $isi);
    }

    public function test_halaman_tertutup_untuk_bukan_admin(): void
    {
        User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p9@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $this->get(route('admin.izin.index'))->assertRedirect();
        $this->getJson(route('admin.izin.data'))->assertStatus(302);
    }

    // ── AKSI LEWAT JSON ────────────────────────────────────────────────

    public function test_setujui_lewat_json_memperbarui_status_dan_mengembalikan_tabs(): void
    {
        $this->admin();
        $izin = $this->pengajuan();

        $this->postJson(route('admin.izin.proses'), [
            'id' => $izin->id, 'putusan' => 'setuju', 'catatan' => 'CepatConfigurations', 'status_aktif' => 'Menunggu',
        ])->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('status', 'Menunggu');

        $this->assertStringContainsString(
            'Tidak ada pengajuan berstatus Menunggu.',
            $this->getJson(route('admin.izin.data').'?status=Menunggu')->json('isi')
        );

        $this->assertDatabaseHas('pengajuan_izin', [
            'id' => $izin->id, 'status' => 'Disetujui', 'catatan_admin' => 'CepatConfigurations', 'diproses_oleh' => session('uid'),
        ]);
    }

    public function test_tolak_lewat_json_menandai_ditolak(): void
    {
        $this->admin();
        $izin = $this->pengajuan();

        $this->postJson(route('admin.izin.proses'), [
            'id' => $izin->id, 'putusan' => 'tolak', 'status_aktif' => 'Semua',
        ])->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('status', 'Semua');

        $this->assertDatabaseHas('pengajuan_izin', ['id' => $izin->id, 'status' => 'Ditolak']);
    }

    public function test_aksi_gagal_lewat_json_mengembalikan_422_dengan_tabs_baru(): void
    {
        $this->admin();
        $izin = $this->pengajuan(['status' => 'Disetujui']);

        $this->postJson(route('admin.izin.proses'), ['id' => $izin->id, 'putusan' => 'setuju'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonStructure(['sukses', 'pesan', 'status', 'tabs', 'isi']);
    }

    public function test_tanpa_putusan_lewat_json_ditolak_server(): void
    {
        $this->admin();
        $izin = $this->pengajuan();

        $this->postJson(route('admin.izin.proses'), ['id' => $izin->id])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);

        $this->assertDatabaseHas('pengajuan_izin', ['id' => $izin->id, 'status' => 'Menunggu']);
    }

    public function test_pengajuan_berjenjang_tidak_bisa_diproses_langsung(): void
    {
        $this->admin();
        $izin = $this->pengajuan(['jenis' => 'Cuti', 'tahap_aktif' => 1]);

        $this->postJson(route('admin.izin.proses'), ['id' => $izin->id, 'putusan' => 'setuju'])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);

        $this->assertDatabaseHas('pengajuan_izin', ['id' => $izin->id, 'status' => 'Menunggu']);
    }

    public function test_ambil_alih_lewat_json_memproses_tahap_aktif(): void
    {
        $admin = $this->admin();
        $izin = $this->pengajuan(['jenis' => 'Izin', 'tahap_aktif' => 1]);
        $tahap = IzinPersetujuan::create(['pengajuan_id' => $izin->id, 'tahap' => 1, 'posisi_tahap' => 'Kepala Seksi', 'status' => 'Menunggu']);

        $this->postJson(route('admin.izin.ambilalih'), [
            'id' => $izin->id, 'putusan' => 'setuju', 'status_aktif' => 'Menunggu',
        ])->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('status', 'Menunggu');

        $this->assertDatabaseHas('izin_persetujuan', [
            'id' => $tahap->id, 'status' => 'Disetujui', 'oleh_user_id' => $admin->id,
        ]);
    }

    public function test_ambil_alih_gagal_lewat_json_tetap_di_tab_awal(): void
    {
        $this->admin();
        $izin = $this->pengajuan(['jenis' => 'Izin', 'tahap_aktif' => 0]);

        $this->postJson(route('admin.izin.ambilalih'), [
            'id' => $izin->id, 'putusan' => 'setuju', 'status_aktif' => 'Menunggu',
        ])->assertStatus(422)->assertJsonPath('sukses', false);
    }

    // ── TANPA JAVASCRIPT (FORM POST BIASA) ─────────────────────────────

    public function test_form_post_biasa_tetah_mengalihkan(): void
    {
        $this->admin();
        $izin = $this->pengajuan();

        $this->post(route('admin.izin.proses'), ['id' => $izin->id, 'putusan' => 'setuju'])
            ->assertRedirect('admin/izin')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pengajuan_izin', ['id' => $izin->id, 'status' => 'Disetujui']);
    }

    private function pemohonLain(): User
    {
        if ($this->pemohonLain === null) {
            $this->pemohonLain = User::create([
                'nama_lengkap' => 'Pegawai Dua',
                'email' => 'p2@contoh.test',
                'password_hash' => bcrypt('rahasia-kuat'),
                'role' => 'pegawai',
                'status' => 'aktif',
                'email_verified_at' => now(),
            ]);
        }

        return $this->pemohonLain;
    }
}
