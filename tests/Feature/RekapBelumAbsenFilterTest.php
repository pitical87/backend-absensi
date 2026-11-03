<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapBelumAbsenFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_belum_absen_hanya_menampilkan_pegawai_tanpa_absensi_bulan_itu(): void
    {
        $admin = User::create([
            'nama_lengkap' => 'Admin Rekap Absen',
            'email' => 'admin.absen@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);
        $hadir = User::create([
            'nama_lengkap' => 'Pegawai Hadir',
            'email' => 'hadir.absen@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);
        $tanpa = User::create([
            'nama_lengkap' => 'Pegawai Tanpa Absen',
            'email' => 'tanpa.absen@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
        ]);

        Absensi::create([
            'user_id' => $hadir->id,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 08:00:00',
        ]);
        Absensi::create([
            'user_id' => $tanpa->id,
            'tanggal' => '2026-02-10',
            'waktu_masuk' => '2026-02-10 08:00:00',
        ]);

        $this->withSession([
            'uid' => (int) $admin->id,
            'role' => 'admin',
            'nama' => $admin->nama_lengkap,
        ]);

        $html = $this->get(route('admin.rekap.index', [
            'bulan' => 3, 'tahun' => 2026, 'absen' => 'belum',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Pegawai Tanpa Absen', $html);
        $this->assertStringNotContainsString('Pegawai Hadir', $html);

        $json = $this->getJson(route('admin.rekap.data', [
            'bulan' => 3, 'tahun' => 2026, 'absen' => 'belum',
        ]))->assertOk()->json();

        $this->assertStringContainsString('Pegawai Tanpa Absen', $json['html']);
        $this->assertStringNotContainsString('Pegawai Hadir', $json['html']);
    }
}
