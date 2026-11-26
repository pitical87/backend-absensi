<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\UnitKerja;
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

    // ── ALUR ATASAN LANGSUNG (BUKAN STRUKTUR) ──────────────────────────

    private function sebagaiMobile(User $user): self
    {
        $token = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'token' => ApiToken::hashToken($token),
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        return $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->withUnencryptedCookie('auth_token', $token)
            ->withCredentials();
    }

    public function test_pengajuan_izin_menggunakan_atasan_langsung_bukan_struktur(): void
    {
        $unitA = UnitKerja::create(['nama' => 'SIMRS', 'punya_sub' => false]);
        $unitB = UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => false]);

        $pemohon = User::create([
            'nama_lengkap' => 'Pemohon Staf',
            'email' => 'pemohon.al@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'posisi' => 'Staf',
            'unit_kerja_id' => $unitA->id,
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        // Pejabat sesuai struktur lama (Koordinator di unit yang sama) — BUKAN atasan_langsung
        $koordinatorStruktur = User::create([
            'nama_lengkap' => 'Koordinator Struktur',
            'email' => 'koord.struktur@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'posisi' => 'Koordinator/Kepala Unit/Ruang/Instalasi',
            'unit_kerja_id' => $unitA->id,
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        // Atasan langsung sebenarnya (posisi Staf & unit berbeda, tapi terdaftar di tabel atasan_langsung)
        $atasanLangsung1 = User::create([
            'nama_lengkap' => 'Atasan Langsung Satu',
            'email' => 'atasan1@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'posisi' => 'Staf',
            'unit_kerja_id' => $unitB->id,
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        $atasanLangsung2 = User::create([
            'nama_lengkap' => 'Atasan Langsung Dua',
            'email' => 'atasan2@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'posisi' => 'Staf',
            'unit_kerja_id' => $unitB->id,
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        AtasanLangsung::create(['user_id' => $pemohon->id, 'atasan_id' => $atasanLangsung1->id]);
        AtasanLangsung::create(['user_id' => $pemohon->id, 'atasan_id' => $atasanLangsung2->id]);

        // Pemohon mengajukan Izin
        $resAjukan = $this->sebagaiMobile($pemohon)
            ->postJson('/api/mobile/izin', [
                'jenis_pengajuan' => 'Izin',
                'tanggal_mulai' => now()->addDays(2)->toDateString(),
                'tanggal_selesai' => now()->addDays(2)->toDateString(),
                'alamat' => 'Jl. Merauke No. 1',
                'alasan' => 'Keperluan keluarga',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $izinId = (int) $resAjukan->json('izin_id');
        $this->assertDatabaseHas('izin_persetujuan', [
            'pengajuan_id' => $izinId,
            'tahap' => 1,
            'posisi_tahap' => 'Atasan Langsung',
            'status' => 'Menunggu',
        ]);
        // Tidak membuat tahap struktur lain (Kepala Seksi / Kepala Bidang / HRD)
        $this->assertSame(1, IzinPersetujuan::where('pengajuan_id', $izinId)->count());

        // Koordinator sesuai struktur TIDAK boleh melihat atau memproses pengajuan ini
        $this->sebagaiMobile($koordinatorStruktur)
            ->getJson('/api/mobile/izin/total')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->sebagaiMobile($koordinatorStruktur)
            ->postJson('/api/mobile/izin/proses', [
                'id' => $izinId,
                'putusan' => 'setuju',
            ])
            ->assertStatus(403);

        // Kedua atasan langsung menerima notifikasi (in-app + Web Push), bukan koordinator struktur
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $atasanLangsung1->id,
            'kategori' => 'izin',
            'url' => 'persetujuan',
        ]);
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $atasanLangsung2->id,
            'kategori' => 'izin',
            'url' => 'persetujuan',
        ]);
        $this->assertDatabaseMissing('notifikasis', [
            'user_id' => $koordinatorStruktur->id,
            'kategori' => 'izin',
        ]);

        // Kedua atasan langsung melihat pengajuan ini
        $this->sebagaiMobile($atasanLangsung1)
            ->getJson('/api/mobile/izin/detail')
            ->assertOk()
            ->assertJsonCount(1, 'izin')
            ->assertJsonPath('izin.0.id', $izinId)
            ->assertJsonPath('izin.0.posisi_tahap', 'Atasan Langsung');

        $this->sebagaiMobile($atasanLangsung2)
            ->getJson('/api/mobile/izin/total')
            ->assertOk()
            ->assertJsonPath('total', 1);

        // Salah satu atasan langsung menyetujui -> langsung Disetujui penuh (tanpa tahap HRD/struktur)
        $this->sebagaiMobile($atasanLangsung2)
            ->postJson('/api/mobile/izin/proses', [
                'id' => $izinId,
                'putusan' => 'setuju',
                'catatan' => 'Disetujui atasan langsung',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('hasil', 'Disetujui');

        $this->assertDatabaseHas('pengajuan_izin', [
            'id' => $izinId,
            'status' => 'Disetujui',
            'tahap_aktif' => 0,
            'diproses_oleh' => $atasanLangsung2->id,
        ]);
        $this->assertDatabaseHas('izin_persetujuan', [
            'pengajuan_id' => $izinId,
            'tahap' => 1,
            'status' => 'Disetujui',
            'oleh_user_id' => $atasanLangsung2->id,
        ]);
        // Pemohon menerima notifikasi hasil keputusan
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $pemohon->id,
            'kategori' => 'izin',
            'tipe' => 'success',
            'url' => 'izin',
        ]);
    }

    public function test_pengajuan_sakit_juga_masuk_ke_atasan_langsung_jika_terdaftar(): void
    {
        $pemohon = User::create([
            'nama_lengkap' => 'Pegawai Sakit',
            'email' => 'sakit.al@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        $atasan = User::create([
            'nama_lengkap' => 'Atasan Sakit',
            'email' => 'atasan.sakit@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);
        AtasanLangsung::create(['user_id' => $pemohon->id, 'atasan_id' => $atasan->id]);

        $res = $this->sebagaiMobile($pemohon)
            ->postJson('/api/mobile/izin', [
                'jenis_pengajuan' => 'Sakit',
                'tanggal_mulai' => now()->addDays(3)->toDateString(),
                'tanggal_selesai' => now()->addDays(3)->toDateString(),
                'alasan' => 'Demam tinggi',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $izinId = (int) $res->json('izin_id');

        $this->sebagaiMobile($atasan)
            ->getJson('/api/mobile/izin/total')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->sebagaiMobile($atasan)
            ->postJson('/api/mobile/izin/proses', [
                'id' => $izinId,
                'putusan' => 'setuju',
            ])
            ->assertOk()
            ->assertJsonPath('hasil', 'Disetujui');

        $this->assertDatabaseHas('pengajuan_izin', [
            'id' => $izinId,
            'status' => 'Disetujui',
            'diproses_oleh' => $atasan->id,
        ]);
    }

    public function test_pemohon_tanpa_atasan_langsung_tidak_otomatis_disetujui_dan_diteruskan_ke_admin(): void
    {
        $admin = $this->admin();
        $pemohon = User::create([
            'nama_lengkap' => 'Pegawai Tanpa Atasan',
            'email' => 'tanpa.atasan@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'posisi' => 'Direktur',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        $res = $this->sebagaiMobile($pemohon)
            ->postJson('/api/mobile/izin', [
                'jenis_pengajuan' => 'Izin',
                'tanggal_mulai' => now()->addDays(4)->toDateString(),
                'tanggal_selesai' => now()->addDays(4)->toDateString(),
                'alamat' => 'Merauke',
                'alasan' => 'Urusan pribadi',
            ])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $izinId = (int) $res->json('izin_id');
        $this->assertDatabaseHas('pengajuan_izin', [
            'id' => $izinId,
            'status' => 'Menunggu',
            'tahap_aktif' => 1,
        ]);
        $this->assertDatabaseHas('izin_persetujuan', [
            'pengajuan_id' => $izinId,
            'tahap' => 1,
            'posisi_tahap' => 'Admin',
            'status' => 'Menunggu',
        ]);
        $this->assertDatabaseHas('notifikasis', [
            'user_id' => $admin->id,
            'kategori' => 'izin',
            'url' => 'admin/izin',
        ]);
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
