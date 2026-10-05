<?php

namespace Tests\Feature;

use App\Models\Logbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogbookTest extends TestCase
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

    private function pegawai(string $nama = 'Pegawai Satu', string $status = 'aktif'): User
    {
        $urut = ++self::$urut;

        return User::create([
            'nama_lengkap' => $nama,
            'nip' => '19800'.(100 + $urut),
            'email' => 'pegawai'.$urut.'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => $status,
            'email_verified_at' => now(),
        ]);
    }

    private function entri(User $u, array $atribut = []): Logbook
    {
        return Logbook::create(array_merge([
            'user_id' => $u->id,
            'tanggal' => now()->startOfMonth()->addDays(2)->toDateString(),
            'jam' => '08:00',
            'isi' => 'Memeriksa pasien',
        ], $atribut));
    }

    // ── Sidebar / navigasi ──────────────────────────
    public function test_grup_kinerja_memuat_dua_sub_menu(): void
    {
        $this->admin();

        $html = $this->get(route('admin.logbook.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Kinerja', $html);
        $this->assertStringContainsString('Buat Logbook', $html);
        $this->assertStringContainsString('Data Logbook', $html);
        $this->assertStringContainsString(url('admin/logbook-data'), $html);
    }

    public function test_halaman_buat_logbook_menampilkan_pilihan_pegawai(): void
    {
        $this->admin();
        $aktif = $this->pegawai('Budi Santoso', 'aktif');
        $nonaktif = $this->pegawai('Siti Aminah', 'nonaktif');

        $html = $this->get(route('admin.logbook.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="user_id"', $html);
        $this->assertStringContainsString('Pilih pegawai', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('value="'.(int) $aktif->id.'"', $html);
        $this->assertStringNotContainsString('Siti Aminah', $html);
    }

    // ── Simpan logbook untuk pegawai tertentu ───────
    public function test_simpan_logbook_tercatat_untuk_pegawai_yang_dipilih(): void
    {
        $admin = $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');

        $this->postJson(route('admin.logbook.simpan'), [
            'user_id' => $pegawai->id,
            'tanggal' => ['2026-08-03', '2026-08-04'],
            'jam' => ['08:00', '09:30'],
            'isi' => ['Kontrol pagi', 'Rawat jalan'],
        ])->assertOk()->assertJsonPath('sukses', true);

        $this->assertDatabaseHas('logbooks', ['user_id' => $pegawai->id, 'isi' => 'Kontrol pagi', 'jam' => '08:00']);
        $this->assertDatabaseHas('logbooks', ['user_id' => $pegawai->id, 'isi' => 'Rawat jalan']);

        // bukan milik admin
        $this->assertDatabaseMissing('logbooks', ['user_id' => $admin->id]);
    }

    public function test_simpan_logbook_menolak_tanpa_pegawai(): void
    {
        $this->admin();

        $this->postJson(route('admin.logbook.simpan'), [
            'tanggal' => ['2026-08-03'],
            'jam' => ['08:00'],
            'isi' => ['Kontrol pagi'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('logbooks', 0);
    }

    public function test_simpan_logbook_menolak_pegawai_tidak_ada(): void
    {
        $this->admin();

        $this->postJson(route('admin.logbook.simpan'), [
            'user_id' => 9999,
            'tanggal' => ['2026-08-03'],
            'jam' => ['08:00'],
            'isi' => ['Kontrol pagi'],
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_data_logbook_menampilkan_entri_pegawai_yang_dipilih(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $dua = $this->pegawai('Siti Aminah');
        $this->entri($satu, ['isi' => 'Entri Budi']);
        $this->entri($dua, ['isi' => 'Entri Siti']);

        $respons = $this->getJson(route('admin.logbook.data', [
            'user_id' => $satu->id,
            'bulan' => now()->month,
            'tahun' => now()->year,
        ]));

        $respons->assertOk()->assertJsonPath('total', 1);
        $this->assertSame('Entri Budi', $respons->json('data.0.isi'));
    }

    // ── Data Logbook: daftar ────────────────────────
    public function test_halaman_data_logbook_menampilkan_rekap_per_pegawai(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $this->entri($satu);
        $this->entri($satu, ['tanggal' => now()->startOfMonth()->addDays(3)->toDateString()]);

        $this->get(route('admin.logbook_data.index', ['bulan' => now()->month, 'tahun' => now()->year]))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Verifikasi', false);
    }

    public function test_halaman_data_logbook_menampilkan_progres_verifikasi(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $admin = User::where('role', 'admin')->first();
        $satuEntri = $this->entri($satu);
        $this->entri($satu, ['jam' => '09:00']);

        $satuEntri->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $this->get(route('admin.logbook_data.index', ['bulan' => now()->month, 'tahun' => now()->year]))
            ->assertOk()
            ->assertSee('1/2', false)
            ->assertSee('1 belum diverifikasi', false);
    }

    public function test_halaman_data_logbook_mencari_nama_pegawai(): void
    {
        $this->admin();
        $this->pegawai('Budi Santoso');
        $this->pegawai('Siti Aminah');

        $this->get(route('admin.logbook_data.index', ['q' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Budi Santoso');
    }

    // ── Data Logbook: detail ────────────────────────
    public function test_detail_mengembalikan_entri_pegawai_yang_dipilih(): void
    {
        $this->admin();
        $satu = $this->pegawai('Budi Santoso');
        $dua = $this->pegawai('Siti Aminah');
        $entri = $this->entri($satu, ['isi' => 'Entri Budi']);
        $this->entri($dua, ['isi' => 'Entri Siti']);

        $respons = $this->getJson(route('admin.logbook_data.detail', [
            'user_id' => $satu->id,
            'bulan' => (int) now()->month,
            'tahun' => (int) now()->year,
        ]));

        $respons->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('total_entri', 1)
            ->assertJsonPath('terverifikasi', 0);

        $data = $respons->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($entri->id, $data[$entri->tanggal->format('Y-m-d')][0]['id']);
        $this->assertSame('Entri Budi', $data[$entri->tanggal->format('Y-m-d')][0]['isi']);
    }

    public function test_detail_pegawai_tidak_ada_mengembalikan_404(): void
    {
        $this->admin();

        $this->getJson(route('admin.logbook_data.detail', [
            'user_id' => 9999,
            'bulan' => (int) now()->month,
            'tahun' => (int) now()->year,
        ]))->assertStatus(404)->assertJsonPath('sukses', false);
    }

    // ── Data Logbook: verifikasi ────────────────────
    public function test_verifikasi_menandai_entri_sebagai_terverifikasi(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai);

        $this->postJson(route('admin.logbook_data.verifikasi'), [
            'ids' => [$entri->id],
            'aksi' => 'verifikasi',
        ])->assertOk()->assertJsonPath('sukses', true);

        $entri->refresh();
        $this->assertTrue($entri->is_verified);
        $this->assertNotNull($entri->verified_at);
        $this->assertSame((int) session('uid'), (int) $entri->verified_by);
    }

    public function test_verifikasi_massa_beberapa_entri(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $a = $this->entri($pegawai);
        $b = $this->entri($pegawai, ['jam' => '10:00']);

        $this->postJson(route('admin.logbook_data.verifikasi'), [
            'ids' => [$a->id, $b->id],
            'aksi' => 'verifikasi',
        ])->assertOk();

        $this->assertTrue($a->fresh()->is_verified);
        $this->assertTrue($b->fresh()->is_verified);
    }

    public function test_batal_verifikasi_melepas_status(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai, ['is_verified' => true, 'verified_at' => now()]);

        $this->postJson(route('admin.logbook_data.verifikasi'), [
            'ids' => [$entri->id],
            'aksi' => 'batal',
        ])->assertOk();

        $entri->refresh();
        $this->assertFalse($entri->is_verified);
        $this->assertNull($entri->verified_at);
        $this->assertNull($entri->verified_by);
    }

    public function test_verifikasi_menolak_aksi_tidak_valid(): void
    {
        $this->admin();
        $entri = $this->entri($this->pegawai());

        $this->postJson(route('admin.logbook_data.verifikasi'), [
            'ids' => [$entri->id],
            'aksi' => 'hapus',
        ])->assertStatus(422)->assertJsonValidationErrors('aksi');
    }

    public function test_verifikasi_menolak_tanpa_entri(): void
    {
        $this->admin();

        $this->postJson(route('admin.logbook_data.verifikasi'), [
            'ids' => [],
            'aksi' => 'verifikasi',
        ])->assertStatus(422)->assertJsonValidationErrors('ids');
    }

    // ── Data Logbook: ubah ──────────────────────────
    public function test_admin_dapat_mengubah_entri_pegawai_lain(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai, ['isi' => 'Isi lama']);

        $this->postJson(route('admin.logbook_data.ubah'), [
            'id' => $entri->id,
            'tanggal' => '2026-08-10',
            'jam' => '11:00',
            'isi' => 'Isi baru',
        ])->assertOk()->assertJsonPath('sukses', true);

        $entri->refresh();
        $this->assertSame('Isi baru', $entri->isi);
        $this->assertSame('11:00', substr((string) $entri->jam, 0, 5));
    }

    public function test_mengubah_entri_terverifikasi_melepas_verifikasinya(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai, [
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $pegawai->id,
        ]);

        $this->postJson(route('admin.logbook_data.ubah'), [
            'id' => $entri->id,
            'tanggal' => '2026-08-10',
            'jam' => '11:00',
            'isi' => 'Isi baru',
        ])->assertOk();

        $entri->refresh();
        $this->assertFalse($entri->is_verified);
        $this->assertNull($entri->verified_at);
        $this->assertNull($entri->verified_by);
    }

    public function test_ubah_entri_tidak_ada_mengembalikan_404(): void
    {
        $this->admin();

        $this->postJson(route('admin.logbook_data.ubah'), [
            'id' => 9999,
            'tanggal' => '2026-08-10',
            'jam' => '11:00',
            'isi' => 'Isi baru',
        ])->assertStatus(404)->assertJsonPath('sukses', false);
    }

    public function test_ubah_menolak_isi_kosong(): void
    {
        $this->admin();
        $entri = $this->entri($this->pegawai());

        $this->postJson(route('admin.logbook_data.ubah'), [
            'id' => $entri->id,
            'tanggal' => '2026-08-10',
            'jam' => '11:00',
            'isi' => '',
        ])->assertStatus(422)->assertJsonValidationErrors('isi');
    }

    // ── Data Logbook: hapus ─────────────────────────
    public function test_admin_dapat_menghapus_entri_pegawai_lain(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai);

        $this->postJson(route('admin.logbook_data.hapus'), ['ids' => [$entri->id]])
            ->assertOk()
            ->assertJsonPath('sukses', true);

        $this->assertDatabaseCount('logbooks', 0);
    }

    public function test_admin_dapat_menghapus_entri_yang_sudah_terverifikasi(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $entri = $this->entri($pegawai, ['is_verified' => true, 'verified_at' => now()]);

        $this->postJson(route('admin.logbook_data.hapus'), ['ids' => [$entri->id]])
            ->assertOk();

        $this->assertDatabaseCount('logbooks', 0);
    }

    public function test_hapus_massa_beberapa_entri(): void
    {
        $this->admin();
        $pegawai = $this->pegawai('Budi Santoso');
        $a = $this->entri($pegawai);
        $b = $this->entri($pegawai, ['jam' => '10:00']);
        $c = $this->entri($pegawai, ['jam' => '11:00']);

        $this->postJson(route('admin.logbook_data.hapus'), ['ids' => [$a->id, $b->id]])
            ->assertOk();

        $this->assertDatabaseMissing('logbooks', ['id' => $a->id]);
        $this->assertDatabaseMissing('logbooks', ['id' => $b->id]);
        $this->assertDatabaseHas('logbooks', ['id' => $c->id]);
    }

    public function test_hapus_menolak_tanpa_entri(): void
    {
        $this->admin();

        $this->postJson(route('admin.logbook_data.hapus'), ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    // ──_Otorisasi ───────────────────────────────────
    public function test_halaman_bukan_admin_diarahkan(): void
    {
        $pegawai = $this->pegawai();
        $this->withSession(['uid' => (int) $pegawai->id, 'role' => 'pegawai', 'nama' => 'Pegawai Satu']);

        $this->get(route('admin.logbook_data.index'))->assertRedirect();
        $this->postJson(route('admin.logbook_data.verifikasi'), ['ids' => [1], 'aksi' => 'verifikasi'])
            ->assertStatus(302);
    }
}