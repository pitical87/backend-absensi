<?php

namespace Tests\Feature;

use App\Models\Jabatan;
use App\Models\Jabatans;
use App\Models\PosisiJabatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Kategori Jabatan & Posisi: halaman asinkron, aksi modal
 * (tambah/ubah/hapus), guard pemakaian, cascade rename, dan helper berbasis DB.
 */
class ReferensiMasterTest extends TestCase
{
    use RefreshDatabase;

    private static int $urut = 0;

    private function admin(): User
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Referensi',
            'email' => 'admin.referensi.'.(++self::$urut).'@contoh.test',
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

    private function pegawai(string $jabatanKategori, string $posisi = 'Staf'): User
    {
        return User::create([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => 'pegawai.referensi.'.(++self::$urut).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
            'jabatan_kategori' => $jabatanKategori,
            'posisi' => $posisi,
        ]);
    }

    private function aksi(string $jenis, array $data)
    {
        return $this->postJson(route('admin.referensi.aksi', $jenis), $data);
    }

    // ── HALAMAN & DAFTAR ASINKRON ────────────────────────────────────

    public function test_halaman_menampilkan_dua_tabel_dan_menu(): void
    {
        $this->admin();

        $this->get(route('admin.referensi.index'))
            ->assertOk()
            ->assertSee('Kategori Jabatan')
            ->assertSee('Posisi Jabatan')
            ->assertSee('Kepala Bidang')
            ->assertSee('Koordinator/Kepala Unit/Ruang/Instalasi')
            ->assertSee('Jabatan & Posisi');
    }

    public function test_endpoint_data_mengembalikan_html_dan_mencari(): void
    {
        $this->admin();

        $respons = $this->getJson(route('admin.referensi.data', ['jenis' => 'kategori']))
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertStringContainsString('Direktur', $respons->json('html'));

        $cari = $this->getJson(route('admin.referensi.data', [
            'jenis' => 'kategori',
            'q' => 'Kepala Bidang',
        ]))->assertOk();
        $this->assertStringContainsString('Kepala Bidang', $cari->json('html'));
        $this->assertStringNotContainsString('Staf/Pelaksana', $cari->json('html'));

        $posisi = $this->getJson(route('admin.referensi.data', ['jenis' => 'posisi']))
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertStringContainsString('HRD', $posisi->json('html'));
    }

    // ── KATEGORI ─────────────────────────────────────────────────────

    public function test_tambah_ubah_dan_hapus_kategori_dengan_cascade(): void
    {
        $this->admin();

        $this->aksi('kategori', ['aksi' => 'tambah', 'nama' => 'Kepala Divisi', 'urutan' => 7])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseHas('jabatans', ['nama' => 'Kepala Divisi']);

        // Nama ganda ditolak.
        $this->aksi('kategori', ['aksi' => 'tambah', 'nama' => 'Kepala Divisi'])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);

        $id = (int) Jabatans::where('nama', 'Kepala Divisi')->value('id');
        Jabatan::create(['nama' => 'Kasi Uji', 'kategori' => 'Kepala Divisi', 'urutan' => 1]);
        $peg = $this->pegawai('Kepala Divisi');

        // Rename mencascade ke users & jabatan.
        $this->aksi('kategori', [
            'aksi' => 'ubah', 'id' => $id, 'nama' => 'Kepala Divisi Umum',
            'untuk_struktur' => '1', 'urutan' => 7,
        ])->assertOk()->assertJson(['sukses' => true]);

        $this->assertDatabaseHas('users', ['id' => $peg->id, 'jabatan_kategori' => 'Kepala Divisi Umum']);
        $this->assertDatabaseHas('jabatan', ['nama' => 'Kasi Uji', 'kategori' => 'Kepala Divisi Umum']);

        // Masih dipakai → hapus ditolak.
        $this->aksi('kategori', ['aksi' => 'hapus', 'id' => $id])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);
        $this->assertDatabaseHas('jabatans', ['id' => $id]);

        // Lepas pemakaian → hapus berhasil.
        Jabatan::where('kategori', 'Kepala Divisi Umum')->delete();
        $peg->delete();
        $this->aksi('kategori', ['aksi' => 'hapus', 'id' => $id])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseMissing('jabatans', ['id' => $id]);
    }

    public function test_helper_membaca_dari_tabel_dan_menghormati_flag_struktur(): void
    {
        $this->assertContains('Direktur', kategori_jabatan_list());
        $this->assertContains('Staf/Pelaksana', kategori_jabatan_list());

        Jabatans::create(['nama' => 'Kepala Urusan', 'untuk_struktur' => false, 'urutan' => 99]);

        $this->assertContains('Kepala Urusan', kategori_jabatan_list());
        $this->assertNotContains('Kepala Urusan', kategori_jabatan_struktur_list());
        $this->assertContains('Kepala Bidang', kategori_jabatan_struktur_list());
        $this->assertNotContains('Staf/Pelaksana', kategori_jabatan_struktur_list());
    }

    // ── POSISI ───────────────────────────────────────────────────────

    public function test_tambah_ubah_dan_hapus_posisi_dengan_cascade(): void
    {
        $this->admin();

        $this->aksi('posisi', [
            'aksi' => 'tambah', 'nama' => 'Sekretaris', 'tingkat' => 0, 'urutan' => 9,
        ])->assertOk()->assertJson(['sukses' => true]);
        $this->assertDatabaseHas('posisi_jabatan', ['nama' => 'Sekretaris']);

        // Tingkat di luar batas ditolak.
        $this->aksi('posisi', ['aksi' => 'tambah', 'nama' => 'Uji', 'tingkat' => 99])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);

        $id = (int) PosisiJabatan::where('nama', 'Sekretaris')->value('id');
        $peg = $this->pegawai('Staf/Pelaksana', 'Sekretaris');

        $this->aksi('posisi', [
            'aksi' => 'ubah', 'id' => $id, 'nama' => 'Sekretaris Ahli',
            'tingkat' => 1, 'urutan' => 9,
        ])->assertOk()->assertJson(['sukses' => true]);
        $this->assertDatabaseHas('users', ['id' => $peg->id, 'posisi' => 'Sekretaris Ahli']);
        $this->assertDatabaseHas('posisi_jabatan', ['id' => $id, 'tingkat' => 1]);

        $this->aksi('posisi', ['aksi' => 'hapus', 'id' => $id])
            ->assertStatus(422)
            ->assertJson(['sukses' => false]);

        $peg->delete();
        $this->aksi('posisi', ['aksi' => 'hapus', 'id' => $id])
            ->assertOk()
            ->assertJson(['sukses' => true]);
        $this->assertDatabaseMissing('posisi_jabatan', ['id' => $id]);
    }

    public function test_posisi_drive_alur_izin(): void
    {
        $this->assertSame(0, posisi_index('Staf'));
        $this->assertSame(2, posisi_index('Kepala Seksi/Sub Bagian'));
        $this->assertSame(4, posisi_index('HRD'));
        $this->assertSame(0, posisi_index('Posisi Tidak Ada'));

        $this->assertSame('Koordinator/Kepala Unit/Ruang/Instalasi', label_tahap_izin(1));
        $this->assertSame('HRD', label_tahap_izin(4));
        $this->assertSame('—', label_tahap_izin(9));

        $this->assertContains('HRD', posisi_untuk_tingkat(4));

        PosisiJabatan::create(['nama' => 'Manajer', 'tingkat' => 3, 'urutan' => 99]);
        $this->assertSame(3, posisi_index('Manajer'));
        $this->assertContains('Manajer', posisi_untuk_tingkat(3));
    }
}
