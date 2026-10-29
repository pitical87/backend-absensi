<?php

namespace Tests\Feature;

use App\Models\AtasanLangsung;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitTest extends TestCase
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

    /** Isi panel tab yang sedang tampil (yakni panel tanpa class `hidden`). */
    private function teksPanelAktif(string $html): string
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        $xp = new \DOMXPath($doc);
        foreach ($xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " tab-panel ")]') as $node) {
            if (! preg_match('/\bhidden\b/', $node->getAttribute('class'))) {
                return (string) $node->textContent;
            }
        }

        return '';
    }

    // ── TAB ───────────────────────────────────────────────────────────

    public function test_tab_default_menampilkan_semua_unit(): void
    {
        $this->admin();
        UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 0]);

        $this->get(route('admin.unit.index'))
            ->assertOk()
            ->assertSee('Semua Unit Kerja')
            ->assertSee('Rawat Inap')
            ->assertSee('Farmasi')
            ->assertSee('Daftar Unit Kerja')
            ->assertSee('Tambah Unit');
    }

    public function test_setiap_unit_kerja_mempunyai_tab(): void
    {
        $this->admin();
        $inap = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 0]);

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('?tab='.(int) $inap->id, $html);
        $this->assertStringContainsString('tab-unit', $html);
    }

    public function test_tab_unit_hanya_menampilkan_sub_unit_unit_tersebut(): void
    {
        $this->admin();
        $inap = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $farmasi = UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 1]);

        SubUnit::create(['unit_kerja_id' => $inap->id, 'nama' => 'Anggrek']);
        SubUnit::create(['unit_kerja_id' => $farmasi->id, 'nama' => 'Apotek Utama']);

        $html = $this->get(route('admin.unit.index').'?tab='.$inap->id)
            ->assertOk()
            ->assertSee('Anggrek')
            ->assertSee('+ Tambah Sub Unit')
            ->assertSee('data-kelola-pegawai-sub', false)
            ->assertSee('modal-hapus-sub')
            ->assertSee('Pilih tindakan')
            ->assertSee('Nonaktifkan pegawai')
            ->assertSee('id="hapus-sub-tabel-bungkus" class="border border-garis rounded-lg p-3 mb-4 hidden"', false)
            ->assertSee('Pindahkan semua ke')
            ->assertSee('Terapkan ke semua')
            ->assertSee('<th>Nama</th>', false)
            ->assertSee('<th>Email</th>', false)
            ->assertSee('id="hapus-sub-kolom-tindakan">Pilih unit</th>', false)
            ->getContent();

        // Semua panel memang sudah ada (berpindah tab instan tanpa menunggu
        // jaringan), tetapi hanya panel tab aktif yang tampil — panel itu
        // hanya memuat sub unit milik unit tersebut.
        $this->assertStringNotContainsString('Apotek Utama', $this->teksPanelAktif($html));
    }

    public function test_tab_unit_kosong_menampilkan_pesan_dan_form_tambah(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 0]);

        $html = $this->get(route('admin.unit.index').'?tab='.$unit->id)
            ->assertOk()
            ->assertSee('+ Tambah Sub Unit')
            ->getContent();

        $this->assertStringContainsString('Belum ada sub unit pada unit ini.', $this->teksPanelAktif($html));
    }

    public function test_tab_tanpa_javascript_menampilkan_form_tambah(): void
    {
        $this->admin();

        $this->get(route('admin.unit.index').'?tab=tambah')
            ->assertOk()
            ->assertSee('Nama unit kerja baru')
            ->assertSee('+ Tambah Unit');
    }

    public function test_param_tab_ngawur_kembali_ke_semua(): void
    {
        $this->admin();
        UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);

        $this->get(route('admin.unit.index').'?tab=99999')->assertOk()->assertSee('Daftar Unit Kerja');
        $this->get(route('admin.unit.index').'?tab=ngawur')->assertOk()->assertSee('Daftar Unit Kerja');
    }

    // ── MODAL UBAH ────────────────────────────────────────────────────

    public function test_tombol_ubah_membawa_data_unit(): void
    {
        $this->admin();
        $atasan = User::create([
            'nama_lengkap' => 'Ka Rawat', 'email' => 'ka@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);
        UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1, 'atasan_id' => $atasan->id]);

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-buka-modal="modal-ubah"', $html);
        $this->assertStringContainsString('data-nama="Rawat Inap"', $html);
        $this->assertStringContainsString('data-atasan="'.$atasan->id.'"', $html);
        $this->assertStringContainsString('id="ubah-unit-atasan"', $html);
    }

    public function test_tombol_tambah_unit_membuka_modal(): void
    {
        $this->admin();

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-buka-modal="modal-tambah"', $html);
        $this->assertStringContainsString('id="modal-tambah"', $html);
    }

    // ── SIMPAN ────────────────────────────────────────────────────────

    public function test_tambah_unit(): void
    {
        $this->admin();

        $this->post(route('admin.unit.aksi'), [
            'aksi' => 'tambah_unit',
            'nama' => 'Rawat Inap',
            'punya_sub' => '1',
        ])->assertRedirect('admin/unit')->assertSessionHas('success');

        $this->assertDatabaseHas('unit_kerja', ['nama' => 'Rawat Inap', 'punya_sub' => 1]);
    }

    public function test_tambah_unit_lewat_json_membalas_isi_tab_baru(): void
    {
        $this->admin();

        $r = $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'tambah_unit',
            'nama' => 'Rawat Inap',
            'punya_sub' => '1',
        ])->assertOk();

        $r->assertJsonPath('sukses', true)->assertJsonPath('mode', 'semua');
        $this->assertStringContainsString('Rawat Inap', $r->getContent());
        $this->assertStringContainsString('tab-unit', $r->json('tabs'));
        $this->assertDatabaseHas('unit_kerja', ['nama' => 'Rawat Inap']);
    }

    public function test_aksi_gagal_lewat_json_mengembalikan_422(): void
    {
        $this->admin();

        $this->postJson(route('admin.unit.aksi'), ['aksi' => 'tambah_unit', 'nama' => '  '])
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonPath('mode', 'tambah');
    }

    public function test_hapus_sub_json_mewajibkan_pilihan_tujuan_jika_ada_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);

        User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(), 'sub_unit_id' => $sub->id,
        ]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub', 'id' => $sub->id, 'unit_kerja_id' => $unit->id,
        ])->assertStatus(422)->assertJsonPath('sukses', false);

        $this->assertDatabaseHas('sub_unit', ['id' => $sub->id]);
    }

    public function test_tambah_unit_tanpa_nama_ditolak(): void
    {
        $this->admin();

        $this->post(route('admin.unit.aksi'), ['aksi' => 'tambah_unit', 'nama' => '  '])
            ->assertRedirect('admin/unit')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('unit_kerja', 0);
    }

    public function test_ubah_unit_menyalakan_punya_sub(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 0]);
        $atasan = User::create([
            'nama_lengkap' => 'Ka Rawat', 'email' => 'ka@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $this->post(route('admin.unit.aksi'), [
            'aksi' => 'ubah_unit',
            'id' => $unit->id,
            'nama' => 'Rawat Inap Utama',
            'punya_sub' => '1',
            'atasan_id' => $atasan->id,
        ])->assertRedirect('admin/unit')->assertSessionHas('success');

        $this->assertDatabaseHas('unit_kerja', [
            'id' => $unit->id, 'nama' => 'Rawat Inap Utama', 'punya_sub' => 1, 'atasan_id' => $atasan->id,
        ]);
    }

    public function test_hapus_unit_menolak_yg_punya_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);

        User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(), 'unit_kerja_id' => $unit->id,
        ]);

        $this->post(route('admin.unit.aksi'), ['aksi' => 'hapus_unit', 'id' => $unit->id])
            ->assertRedirect('admin/unit')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id]);
    }

    public function test_tambah_sub_menyalakan_punya_sub(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 0]);

        $this->post(route('admin.unit.aksi'), [
            'aksi' => 'tambah_sub',
            'unit_kerja_id' => $unit->id,
            'nama' => 'Anggrek',
        ])->assertRedirect('admin/unit')->assertSessionHas('success');

        $this->assertDatabaseHas('sub_unit', ['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id, 'punya_sub' => 1]);
    }

    public function test_tambah_sub_lewat_json_tetap_di_tab_unit(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 0]);

        $r = $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'tambah_sub',
            'unit_kerja_id' => $unit->id,
            'nama' => 'Anggrek',
        ])->assertOk();

        $r->assertJsonPath('sukses', true)->assertJsonPath('mode', 'unit');
        $this->assertStringContainsString('Anggrek', $r->getContent());
        $this->assertDatabaseHas('sub_unit', ['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id, 'punya_sub' => 1]);
    }

    public function test_ubah_sub_lewat_json_menyimpan_atasan(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $atasan = User::create([
            'nama_lengkap' => 'Ka Rawat', 'email' => 'ka@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'ubah_sub',
            'id' => $sub->id,
            'nama' => 'Anggrek',
            'atasan_id' => $atasan->id,
            'unit_kerja_id' => $unit->id,
        ])->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('mode', 'unit');

        $this->assertDatabaseHas('sub_unit', ['id' => $sub->id, 'atasan_id' => $atasan->id]);
    }

    public function test_daftar_pegawai_sub_unit_hanya_memuat_akun_aktif(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $unitLain = UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 1]);
        $subAsal = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $subTujuan = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Melati']);
        $pegawai = $this->createPegawai($subAsal, 'pegawai-sub@example.test', 'Pegawai Unit');
        $pegawaiLain = User::create([
            'nama_lengkap' => 'Pegawai Unit Lain',
            'email' => 'pegawai-lain@example.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'nonaktif',
            'email_verified_at' => now(),
            'unit_kerja_id' => $unitLain->id,
        ]);

        $this->getJson(route('admin.unit.sub.pegawai-kelola', $subTujuan))
            ->assertOk()
            ->assertJsonPath('sub_unit.id', $subTujuan->id)
            ->assertJsonPath('pegawai.0.nama', 'Pegawai Unit')
            ->assertJsonPath('pegawai.0.email', 'pegawai-sub@example.test')
            ->assertJsonPath('pegawai.0.di_sub_unit', false)
            ->assertJsonCount(1, 'pegawai')
            ->assertJsonMissing(['nama' => 'Pegawai Unit Lain']);
    }

    public function test_admin_dapat_memindahkan_pegawai_ke_sub_unit_dari_popup(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $subAsal = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $subTujuan = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Melati']);
        $atasan = $this->createPegawai($subAsal, 'atasan@example.test', 'Atasan');
        $atasan->update(['unit_kerja_id' => $unit->id]);
        $subTujuan->update(['atasan_id' => $atasan->id]);
        $pegawai = $this->createPegawai($subAsal, 'pegawai-pindah@example.test', 'Pegawai Pindah');
        $atasanLama = $this->createPegawai($subAsal, 'atasan-lama@example.test', 'Atasan Lama');
        AtasanLangsung::create(['user_id' => $pegawai->id, 'atasan_id' => $atasanLama->id]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'pindahkan_pegawai_sub',
            'user_id' => $pegawai->id,
            'sub_unit_id' => $subTujuan->id,
            'unit_kerja_id' => $unit->id,
        ])->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('mode', 'unit');

        $this->assertDatabaseHas('users', [
            'id' => $pegawai->id,
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subTujuan->id,
        ]);
        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasan->id,
        ]);
        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasanLama->id,
        ]);
    }

    public function test_admin_dapat_memindahkan_pegawai_dari_unit_kerja_lain(): void
    {
        $this->admin();
        $unitTujuan = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $unitAsal = UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 1]);
        $atasanTujuan = $this->createPegawai(
            SubUnit::create(['unit_kerja_id' => $unitTujuan->id, 'nama' => 'Lain']),
            'atasan-tujuan@example.test',
            'Atasan Tujuan'
        );
        $atasanTujuan->update(['unit_kerja_id' => $unitTujuan->id]);
        $unitTujuan->update(['atasan_id' => $atasanTujuan->id]);
        $subTujuan = SubUnit::create(['unit_kerja_id' => $unitTujuan->id, 'nama' => 'Anggrek']);
        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai Farmasi',
            'email' => 'pegawai-farmasi@example.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
            'unit_kerja_id' => $unitAsal->id,
        ]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'pindahkan_pegawai_sub',
            'user_id' => $pegawai->id,
            'sub_unit_id' => $subTujuan->id,
            'unit_kerja_id' => $unitTujuan->id,
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseHas('users', [
            'id' => $pegawai->id,
            'unit_kerja_id' => $unitTujuan->id,
            'sub_unit_id' => $subTujuan->id,
        ]);
        $this->assertDatabaseHas('atasan_langsung', [
            'user_id' => $pegawai->id,
            'atasan_id' => $atasanTujuan->id,
        ]);
    }

    public function test_hapus_unit_lewat_json_kembali_ke_tab_semua(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);

        $this->postJson(route('admin.unit.aksi'), ['aksi' => 'hapus_unit', 'id' => $unit->id, 'tab_asal' => $unit->id])
            ->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('mode', 'semua');

        $this->assertDatabaseMissing('unit_kerja', ['id' => $unit->id]);
    }

    public function test_hapus_sub_memindahkan_pegawai_ke_sub_unit_tujuan(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $tujuan = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Melati']);

        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p2@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(), 'sub_unit_id' => $sub->id,
        ]);

        $this->getJson(route('admin.unit.sub.pegawai-hapus', $sub))
            ->assertOk()
            ->assertJsonPath('sub_unit.nama', 'Anggrek')
            ->assertJsonPath('pegawai.0.nama', 'Pegawai')
            ->assertJsonPath('pegawai.0.id', $pegawai->id)
            ->assertJsonPath('pegawai.0.email', 'p2@contoh.test')
            ->assertJsonPath('tujuan.0.id', $tujuan->id);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'pindahkan',
            'pegawai_ids' => [$pegawai->id],
            'pindah_ke_sub_unit_id' => [$pegawai->id => $tujuan->id],
        ])->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('mode', 'unit');

        $this->assertDatabaseMissing('sub_unit', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $pegawai->id, 'sub_unit_id' => $tujuan->id]);
    }

    public function test_hapus_sub_dapat_mempertahankan_pegawai_di_unit_tanpa_sub_unit(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $pegawai = User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p3@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(), 'sub_unit_id' => $sub->id,
        ]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'pindahkan',
            'pegawai_ids' => [$pegawai->id],
            'pindah_ke_sub_unit_id' => [$pegawai->id => 'none'],
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseMissing('sub_unit', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $pegawai->id, 'sub_unit_id' => null]);
    }

    public function test_hapus_sub_menolak_tujuan_dari_unit_kerja_lain(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $unitLain = UnitKerja::create(['nama' => 'Farmasi', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $tujuan = SubUnit::create(['unit_kerja_id' => $unitLain->id, 'nama' => 'Apotek']);
        $pegawai = $this->createPegawai($sub, 'p4@contoh.test');

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'pindahkan',
            'pegawai_ids' => [$pegawai->id],
            'pindah_ke_sub_unit_id' => [$pegawai->id => $tujuan->id],
        ])->assertStatus(422)->assertJsonPath('sukses', false);

        $this->assertDatabaseHas('sub_unit', ['id' => $sub->id]);
    }

    public function test_hapus_sub_mendukung_tujuan_berbeda_per_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $tujuanA = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Melati']);
        $tujuanB = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Mawar']);
        $firman = $this->createPegawai($sub, 'firman@example.test', 'Firman');
        $ical = $this->createPegawai($sub, 'ical@example.test', 'Ical');

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'pindahkan',
            'pegawai_ids' => [$firman->id, $ical->id],
            'pindah_ke_sub_unit_id' => [
                $firman->id => $tujuanA->id,
                $ical->id => $tujuanB->id,
            ],
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseMissing('sub_unit', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $firman->id, 'sub_unit_id' => $tujuanA->id]);
        $this->assertDatabaseHas('users', ['id' => $ical->id, 'sub_unit_id' => $tujuanB->id]);
    }

    public function test_hapus_sub_menerapkan_satu_tujuan_ke_semua_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $tujuan = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Melati']);
        $firman = $this->createPegawai($sub, 'firman-all@example.test', 'Firman');
        $ical = $this->createPegawai($sub, 'ical-all@example.test', 'Ical');

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'pindahkan',
            'pegawai_ids' => [$firman->id, $ical->id],
            'pindah_ke_sub_unit_id' => [
                $firman->id => $tujuan->id,
                $ical->id => $tujuan->id,
            ],
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseMissing('sub_unit', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $firman->id, 'sub_unit_id' => $tujuan->id]);
        $this->assertDatabaseHas('users', ['id' => $ical->id, 'sub_unit_id' => $tujuan->id]);
    }

    public function test_hapus_sub_dapat_menonaktifkan_seluruh_pegawai(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);
        $firman = $this->createPegawai($sub, 'firman-deaktivasi@example.test', 'Firman');
        $ical = $this->createPegawai($sub, 'ical-deaktivasi@example.test', 'Ical');

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
            'mode_hapus_sub' => 'nonaktifkan',
            'pegawai_ids' => [$firman->id, $ical->id],
        ])->assertOk()->assertJsonPath('sukses', true)
            ->assertJsonPath('pesan', 'Sub unit dihapus dan 2 pegawai dinonaktifkan.');

        $this->assertDatabaseMissing('sub_unit', ['id' => $sub->id]);
        $this->assertDatabaseHas('users', ['id' => $firman->id, 'status' => 'nonaktif', 'sub_unit_id' => null]);
        $this->assertDatabaseHas('users', ['id' => $ical->id, 'status' => 'nonaktif', 'sub_unit_id' => null]);
    }

    public function test_hapus_sub_menolak_submit_tanpa_mode_tindakan(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek']);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'hapus_sub',
            'id' => $sub->id,
            'unit_kerja_id' => $unit->id,
        ])->assertStatus(422)->assertJsonPath('sukses', false);

        $this->assertDatabaseHas('sub_unit', ['id' => $sub->id]);
    }

    public function test_halaman_tertutup_untuk_bukan_admin(): void
    {
        User::create([
            'nama_lengkap' => 'Pegawai', 'email' => 'p3@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $this->get(route('admin.unit.index'))->assertRedirect();
    }

    private function createPegawai(SubUnit $sub, string $email, string $nama = 'Pegawai'): User
    {
        return User::create([
            'nama_lengkap' => $nama,
            'email' => $email,
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
            'unit_kerja_id' => $sub->unit_kerja_id,
            'sub_unit_id' => $sub->id,
        ]);
    }

    // ── PENCARIAN NAMA (COMBO BOX PILIH PEGAWAI) ──────────────────────

    public function test_pilihan_atasan_unit_kerja_bisa_dicari(): void
    {
        $this->admin();
        User::create([
            'nama_lengkap' => 'Siti Rahayu', 'nip' => '198705122011012003', 'email' => 'siti@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'), 'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-pilih-pegawai', $html);
        $this->assertStringContainsString('pilih-pegawai-cari', $html);
        // Data pencarian (nama, NIP, email) dibagikan sekali via window.PEGAWAI_UNIT;
        // setiap combo mengisinya lewat JS agar tidak diduplikasi per baris.
        $this->assertStringContainsString('siti rahayu 198705122011012003 siti@contoh.test', $html);
    }

    public function test_select_asal_tetap_ada_dan_daftar_dibagikan_satu_kali(): void
    {
        $this->admin();
        User::create([
            'nama_lengkap' => 'Siti Rahayu', 'email' => 'siti@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();
        $Markup = preg_replace('/\s+/', ' ', $html);

        // <select> asli tetap dirender (sumber nilai form), dan seluruh daftar
        // pegawai untuk combo disediakan sekali lewat JS.
        $this->assertStringContainsString('id="ubah-unit-atasan"', $html);
        $this->assertStringContainsString('window.PEGAWAI_UNIT', $html);
        $this->assertStringContainsString('"nama":"Siti Rahayu"', $html);

        // Daftar lengkap hanya dirender di satu combo (modal ubah unit) supaya
        // nilai atasan dapat diatur lebih dulu; combo lainnya memakai JS.
        $this->assertSame(1, substr_count($Markup, 'Siti Rahayu</option>'));
    }

    public function test_combo_box_atasan_sub_unit_menampilkan_nama_yang_terpilih(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $atasan = User::create([
            'nama_lengkap' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);
        $sub = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'Anggrek', 'atasan_id' => $atasan->id]);

        $html = $this->get(route('admin.unit.index').'?tab='.$unit->id)->assertOk()->getContent();

        $this->assertStringContainsString('data-pilih-pegawai', $html);
        $this->assertStringContainsString('<option value="'.(int) $atasan->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'selected'), 'Hanya baris sub unit terpilih yang menandai opsi.');
        $this->assertStringContainsString('data-id="'.(int) $sub->id.'"', $html);
    }

    public function test_admin_tidak_muncul_sebagai_pilihan_atasan(): void
    {
        $admin = $this->admin();
        UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);

        $html = $this->get(route('admin.unit.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-cari="admin uji', $html);
        $this->assertStringContainsString('— Atasan unit —', $html);
        $this->assertNotNull($admin);
    }

    public function test_ubah_unit_lewat_json_menyimpan_atasan_yang_dicari(): void
    {
        $this->admin();
        $unit = UnitKerja::create(['nama' => 'Rawat Inap', 'punya_sub' => 1]);
        $atasan = User::create([
            'nama_lengkap' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai', 'status' => 'aktif', 'email_verified_at' => now(),
        ]);

        $this->postJson(route('admin.unit.aksi'), [
            'aksi' => 'ubah_unit', 'id' => $unit->id, 'nama' => 'Rawat Inap', 'atasan_id' => $atasan->id,
            'tab_asal' => $unit->id,
        ])->assertOk()->assertJsonPath('sukses', true)->assertJsonPath('mode', 'unit');

        $this->assertDatabaseHas('unit_kerja', ['id' => $unit->id, 'atasan_id' => $atasan->id]);
    }
}
