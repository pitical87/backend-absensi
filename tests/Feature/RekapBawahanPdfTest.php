<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\ApiToken;
use App\Models\AtasanLangsung;
use App\Models\Jabatan;
use App\Models\Logbook;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ParafQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapBawahanPdfTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(array $atribut = []): User
    {
        return User::create(array_merge([
            'nama_lengkap' => 'Pegawai Uji',
            'email' => uniqid('user_', true).'@contoh.test',
            'password_hash' => bcrypt('rahasia-kuat'),
            'role' => 'pegawai',
            'status' => 'aktif',
            'email_verified_at' => now(),
        ], $atribut));
    }

    private function sebagai(User $user): self
    {
        $token = bin2hex(random_bytes(32));
        ApiToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addDays(7),
            'last_aktivitas' => now(),
        ]);

        return $this->withUnencryptedCookie('auth_token', $token)->withCredentials();
    }

    public function test_atasan_dapat_mengunduh_pdf_rekap_multi_bawahan_satu_halaman_per_pegawai(): void
    {
        $unit = UnitKerja::create(['nama' => 'Penunjang dan Sarana Prasana', 'punya_sub' => true]);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'SIMRS']);

        $atasan = $this->buatUser([
            'nama_lengkap' => 'Firmansyah Diana',
            'email' => 'atasan@contoh.test',
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subUnit->id,
        ]);
        $bawahan1 = $this->buatUser([
            'nama_lengkap' => 'Firman',
            'email' => 'firman@contoh.test',
            'nip' => '199001012020011001',
            'unit_kerja_id' => $unit->id,
        ]);
        $bawahan2 = $this->buatUser([
            'nama_lengkap' => 'Rina Wijaya',
            'email' => 'rina@contoh.test',
            'nip' => '199202022021022002',
            'unit_kerja_id' => $unit->id,
        ]);

        AtasanLangsung::create(['user_id' => $bawahan1->id, 'atasan_id' => $atasan->id]);
        AtasanLangsung::create(['user_id' => $bawahan2->id, 'atasan_id' => $atasan->id]);

        for ($hari = 1; $hari <= 31; $hari++) {
            $tgl = sprintf('2026-10-%02d', $hari);
            Absensi::create([
                'user_id' => $bawahan1->id,
                'tanggal' => $tgl,
                'waktu_masuk' => $tgl.' 08:02:00',
                'waktu_pulang' => $tgl.' 14:05:00',
                'status_masuk' => 'Tepat Waktu',
                'menit_terlambat' => 0,
                'total_menit_kerja' => 363,
            ]);
        }

        Logbook::create([
            'user_id' => $bawahan1->id,
            'tanggal' => '2026-10-05',
            'jam' => '09:15:00',
            'isi' => 'Pengecekan server SIMRS harian',
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $atasan->id,
        ]);
        Logbook::create([
            'user_id' => $bawahan1->id,
            'tanggal' => '2026-10-06',
            'jam' => '11:00:00',
            'isi' => 'Perbaikan jaringan ruang rawat inap',
            'is_verified' => false,
        ]);
        // Logbook di bulan lain tidak boleh ikut tercetak di Oktober
        Logbook::create([
            'user_id' => $bawahan1->id,
            'tanggal' => '2026-09-25',
            'jam' => '10:00:00',
            'isi' => 'Kegiatan bulan sebelumnya',
            'is_verified' => true,
        ]);

        // Uji bulan 31 hari (Oktober) dengan 2 pegawai =>
        // 2 halaman absen harian diikuti 2 lembar daftar logbook (1 per pegawai) = 4 halaman
        $response = $this->sebagai($atasan)
            ->get('/api/mobile/rekap/bawahan/pdf?bulan=10&tahun=2026&user_ids='.$bawahan1->id.','.$bawahan2->id);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="rekap-bulanan-2026-10.pdf"');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        // Dompdf menulis "/Type /Page" (bukan "/Type /Pages") untuk tiap halaman
        $jumlahHalaman = preg_match_all('/\/Type\s*\/Page\b/', $pdf);
        $this->assertSame(4, $jumlahHalaman, '2 pegawai harus menghasilkan 2 halaman absen + 2 halaman logbook (4 halaman).');
    }

    public function test_urutan_halaman_semua_absen_dulu_baru_logbook_per_pegawai(): void
    {
        $lembarList = [
            [
                'pegawai' => [
                    'nama' => 'Firman',
                    'nip' => '199001012020011001',
                    'pangkat_gol' => '',
                    'jabatan' => 'Pranata Komputer',
                    'unit_kerja' => 'SIMRS',
                    'qr_paraf' => null,
                ],
                'baris' => [
                    ['no' => 1, 'hari_tanggal' => 'Kamis, 01/10/2026', 'minggu' => false, 'jam_masuk' => '08:00', 'jam_pulang' => '14:00', 'ket' => 'Hadir'],
                ],
                'logbook' => [
                    ['no' => 1, 'hari_tanggal' => 'Kamis, 01/10/2026', 'jam' => '09:00', 'isi' => 'Backup database SIMRS', 'terverifikasi' => true],
                ],
                'logbook_ringkasan' => ['total_entri' => 1, 'total_hari' => 1, 'terverifikasi' => 1],
            ],
            [
                'pegawai' => [
                    'nama' => 'Rina Wijaya',
                    'nip' => '199202022021022002',
                    'pangkat_gol' => '',
                    'jabatan' => 'Perawat',
                    'unit_kerja' => 'IGD',
                    'qr_paraf' => null,
                ],
                'baris' => [
                    ['no' => 1, 'hari_tanggal' => 'Kamis, 01/10/2026', 'minggu' => false, 'jam_masuk' => '08:00', 'jam_pulang' => '14:00', 'ket' => 'Hadir'],
                ],
                'logbook' => [],
                'logbook_ringkasan' => ['total_entri' => 0, 'total_hari' => 0, 'terverifikasi' => 0],
            ],
        ];

        $html = view('pdf.rekap_absen_harian', [
            'lembarList' => $lembarList,
            'bulan' => 10,
            'tahun' => 2026,
            'namaBulan' => 'Oktober',
            'tanggalCetak' => '25 November 2026',
            'atasan' => ['nama' => 'Firmansyah Diana', 'nip' => '198001012005011001'],
            'logoKiri' => null,
            'logoKanan' => null,
        ])->render();

        $this->assertSame(2, substr_count($html, '<div class="judul-utama">ABSEN HARIAN</div>'));
        $this->assertSame(2, substr_count($html, '<div class="judul-utama">DAFTAR LOGBOOK HARIAN</div>'));
        $this->assertSame(4, substr_count($html, '<div class="halaman">'));

        // Pastikan seluruh halaman ABSEN HARIAN muncul sebelum halaman DAFTAR LOGBOOK HARIAN pertama
        $posAbsenTerakhir = strrpos($html, '<div class="judul-utama">ABSEN HARIAN</div>');
        $posLogbookPertama = strpos($html, '<div class="judul-utama">DAFTAR LOGBOOK HARIAN</div>');
        $this->assertNotFalse($posAbsenTerakhir);
        $this->assertNotFalse($posLogbookPertama);
        $this->assertLessThan($posLogbookPertama, $posAbsenTerakhir);

        $this->assertStringContainsString('Backup database SIMRS', $html);
        $this->assertStringContainsString('Belum ada catatan logbook pada bulan Oktober 2026.', $html);
    }

    public function test_menolak_akses_jika_memilih_pegawai_bukan_bawahan(): void
    {
        $atasan = $this->buatUser(['nama_lengkap' => 'Atasan A']);
        $orangLain = $this->buatUser(['nama_lengkap' => 'Bukan Bawahan']);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=11&tahun=2026&user_ids='.$orangLain->id)
            ->assertStatus(403)
            ->assertJsonPath('sukses', false);
    }

    public function test_menolak_jika_user_ids_kosong_atau_bulan_tidak_valid(): void
    {
        $atasan = $this->buatUser(['nama_lengkap' => 'Atasan B']);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=11&tahun=2026')
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);

        $this->sebagai($atasan)
            ->getJson('/api/mobile/rekap/bawahan/pdf?bulan=13&tahun=2026&user_ids='.$atasan->id)
            ->assertStatus(422)
            ->assertJsonPath('sukses', false);
    }

    public function test_halaman_verifikasi_qr_paraf_dapat_diakses_publik_tanpa_login(): void
    {
        $unit = UnitKerja::create(['nama' => 'Penunjang dan Sarana Prasana', 'punya_sub' => true]);
        $subUnit = SubUnit::create(['unit_kerja_id' => $unit->id, 'nama' => 'SIMRS']);
        $jabatan = Jabatan::create([
            'nama' => 'Kepala Seksi Penunjang',
            'kategori' => 'Kepala Seksi',
        ]);

        $pegawai = $this->buatUser([
            'nama_lengkap' => 'Firman Santoso',
            'nip' => '199001012020011001',
            'unit_kerja_id' => $unit->id,
            'sub_unit_id' => $subUnit->id,
            'jabatan_id' => $jabatan->id,
        ]);

        $qrService = app(ParafQrService::class);
        $url = $qrService->urlPublik($pegawai->id);

        $this->assertStringContainsString('/p/'.$pegawai->id.'/', $url);
        $this->assertStringStartsWith('data:image/png;base64,', $qrService->qrDataUri($pegawai->id));

        // Akses publik tanpa login
        $this->get($url)
            ->assertOk()
            ->assertSee('RSUD MERAUKE')
            ->assertSee('Firman Santoso')
            ->assertSee('Kepala Seksi Penunjang')
            ->assertSee('Penunjang dan Sarana Prasana — SIMRS');

        // Tanda tangan QR tidak valid harus ditolak 404
        $this->get('/p/'.$pegawai->id.'/salah1234567')
            ->assertNotFound()
            ->assertDontSee('Firman Santoso');
    }
}
