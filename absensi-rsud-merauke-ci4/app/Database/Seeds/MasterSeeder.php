<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Data master awal. Aman dijalankan berulang (hanya mengisi bila kosong).
 */
class MasterSeeder extends Seeder
{
    public function run()
    {
        // ---------- Tempat kerja ----------
        if ($this->db->table('unit_kerja')->countAllResults() === 0) {
            $this->db->table('unit_kerja')->insertBatch([
                ['id' => 1, 'nama' => 'Rawat Inap',   'punya_sub' => 1],
                ['id' => 2, 'nama' => 'Rawat Jalan',  'punya_sub' => 1],
                ['id' => 3, 'nama' => 'Farmasi',      'punya_sub' => 0],
                ['id' => 4, 'nama' => 'Administrasi', 'punya_sub' => 0],
            ]);

            $sub = [];
            foreach (['Boha', 'Kuskus', 'Cendrawasih', 'Rusa 1', 'Rusa 2', 'Kangguru',
                      'Mambruk', 'VK', 'PICU', 'ICU'] as $n) {
                $sub[] = ['unit_kerja_id' => 1, 'nama' => $n];
            }
            foreach (['Poli Paru', 'Poli Penyakit Dalam', 'Poli Gastrohepato', 'Poli Bedah',
                      'Poli Jantung', 'Poli Anak', 'Poli Kandungan', 'Poli Mata', 'Poli Jiwa',
                      'Poli Gigi', 'Poli Orthopedi', 'Poli Kulit', 'Klinik Animha',
                      'Hemodialisa', 'IGD'] as $n) {
                $sub[] = ['unit_kerja_id' => 2, 'nama' => $n];
            }
            $this->db->table('sub_unit')->insertBatch($sub);
        }

        // ---------- Profesi ----------
        if ($this->db->table('profesi')->countAllResults() === 0) {
            $rows = [];
            foreach (['Dokter', 'Perawat', 'Bidan', 'Analis', 'Apoteker', 'Asisten Apoteker',
                      'Sanitasi', 'Radiografer', 'Rekam Medik', 'Administrasi', 'Tenaga Teknis',
                      'Security', 'Cleaning Service', 'Gardener', 'Parkiran'] as $n) {
                $rows[] = ['nama' => $n];
            }
            $this->db->table('profesi')->insertBatch($rows);
        }

        // ---------- Shift ----------
        if ($this->db->table('shift')->countAllResults() === 0) {
            $this->db->table('shift')->insertBatch([
                ['kategori' => 'Pagi',  'jam_masuk' => '08:00:00', 'jam_pulang' => '14:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Pagi',  'jam_masuk' => '06:00:00', 'jam_pulang' => '13:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Pagi',  'jam_masuk' => '05:00:00', 'jam_pulang' => '12:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Sore',  'jam_masuk' => '12:00:00', 'jam_pulang' => '21:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Sore',  'jam_masuk' => '13:00:00', 'jam_pulang' => '20:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Sore',  'jam_masuk' => '12:00:00', 'jam_pulang' => '20:00:00', 'lintas_hari' => 0],
                ['kategori' => 'Malam', 'jam_masuk' => '21:00:00', 'jam_pulang' => '08:00:00', 'lintas_hari' => 1],
                ['kategori' => 'Malam', 'jam_masuk' => '20:00:00', 'jam_pulang' => '06:00:00', 'lintas_hari' => 1],
            ]);
        }


        // ---------- Struktur organisasi (pohon jabatan) ----------
        if ($this->db->tableExists('jabatan')
            && $this->db->table('jabatan')->countAllResults() === 0) {
            $this->db->table('jabatan')->insertBatch([
                ['id' => 1,  'nama' => 'Direktur',                                'kategori' => 'Direktur',
                 'induk_id' => null, 'unit_label' => 'Direktorat',                        'urutan' => 1],
                ['id' => 2,  'nama' => 'Kabag Tata Usaha',                        'kategori' => 'Kepala Bagian',
                 'induk_id' => 1,    'unit_label' => 'Bagian Tata Usaha',                 'urutan' => 1],
                ['id' => 3,  'nama' => 'Kasubag Umum',                            'kategori' => 'Kepala Sub Bagian',
                 'induk_id' => 2,    'unit_label' => null,                                'urutan' => 1],
                ['id' => 4,  'nama' => 'Kasubag Kepegawaian',                     'kategori' => 'Kepala Sub Bagian',
                 'induk_id' => 2,    'unit_label' => null,                                'urutan' => 2],
                ['id' => 5,  'nama' => 'Kasubag Humas dan Hukum',                 'kategori' => 'Kepala Sub Bagian',
                 'induk_id' => 2,    'unit_label' => null,                                'urutan' => 3],
                ['id' => 6,  'nama' => 'Kabid Pelayanan',                         'kategori' => 'Kepala Bidang',
                 'induk_id' => 1,    'unit_label' => 'Bidang Pelayanan',                  'urutan' => 2],
                ['id' => 7,  'nama' => 'Kasi Pelayanan',                          'kategori' => 'Kepala Seksi',
                 'induk_id' => 6,    'unit_label' => null,                                'urutan' => 1],
                ['id' => 8,  'nama' => 'Kasi Keperawatan',                        'kategori' => 'Kepala Seksi',
                 'induk_id' => 6,    'unit_label' => null,                                'urutan' => 2],
                ['id' => 9,  'nama' => 'Kabid Program dan Keuangan',              'kategori' => 'Kepala Bidang',
                 'induk_id' => 1,    'unit_label' => 'Bidang Program dan Keuangan',       'urutan' => 3],
                ['id' => 10, 'nama' => 'Kasi Program',                            'kategori' => 'Kepala Seksi',
                 'induk_id' => 9,    'unit_label' => null,                                'urutan' => 1],
                ['id' => 11, 'nama' => 'Kasi Keuangan',                           'kategori' => 'Kepala Seksi',
                 'induk_id' => 9,    'unit_label' => null,                                'urutan' => 2],
                ['id' => 12, 'nama' => 'Kabid Sarpras dan Penunjang',   'kategori' => 'Kepala Bidang',
                 'induk_id' => 1,    'unit_label' => 'Bidang Sarpras dan Penunjang',   'urutan' => 4],
                ['id' => 13, 'nama' => 'Kasi Sarpras',               'kategori' => 'Kepala Seksi',
                 'induk_id' => 12,   'unit_label' => null,                                'urutan' => 1],
                ['id' => 14, 'nama' => 'Kasi Penunjang Medik',                    'kategori' => 'Kepala Seksi',
                 'induk_id' => 12,   'unit_label' => null,                                'urutan' => 2],
            ]);
        }

        // ---------- Kalender Hari Libur Nasional & Cuti Bersama 2026 ----------
        // Sumber: SKB 3 Menteri Nomor 1497/2/5 Tahun 2025 (17 hari libur nasional +
        // 8 hari cuti bersama). Dimasukkan otomatis agar hari Minggu & tanggal merah
        // langsung dikenali sistem; admin tetap bisa menambah/menghapus hari libur
        // lokal lain (mis. HUT RSUD, cuti bersama daerah) dari menu Hari Libur.
        if ($this->db->tableExists('hari_libur')) {
            $liburNasional2026 = [
                ['2026-01-01', 'Tahun Baru 2026 Masehi'],
                ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW'],
                ['2026-02-16', 'Cuti Bersama Tahun Baru Imlek'],
                ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili'],
                ['2026-03-18', 'Cuti Bersama Hari Suci Nyepi'],
                ['2026-03-19', 'Hari Suci Nyepi (Tahun Baru Saka 1948)'],
                ['2026-03-20', 'Cuti Bersama Idulfitri'],
                ['2026-03-21', 'Idulfitri 1447 H'],
                ['2026-03-22', 'Idulfitri 1447 H'],
                ['2026-03-23', 'Cuti Bersama Idulfitri'],
                ['2026-03-24', 'Cuti Bersama Idulfitri'],
                ['2026-04-03', 'Wafat Yesus Kristus'],
                ['2026-04-05', 'Kebangkitan Yesus Kristus (Paskah)'],
                ['2026-05-01', 'Hari Buruh Internasional'],
                ['2026-05-14', 'Kenaikan Yesus Kristus'],
                ['2026-05-15', 'Cuti Bersama Kenaikan Yesus Kristus'],
                ['2026-05-27', 'Iduladha 1447 H'],
                ['2026-05-28', 'Cuti Bersama Iduladha'],
                ['2026-05-31', 'Hari Raya Waisak 2570 BE'],
                ['2026-06-01', 'Hari Lahir Pancasila'],
                ['2026-06-16', '1 Muharam Tahun Baru Islam 1448 H'],
                ['2026-08-17', 'Proklamasi Kemerdekaan RI'],
                ['2026-08-25', 'Maulid Nabi Muhammad SAW'],
                ['2026-12-24', 'Cuti Bersama Hari Raya Natal'],
                ['2026-12-25', 'Kelahiran Yesus Kristus (Natal)'],
            ];
            foreach ($liburNasional2026 as [$tgl, $ket]) {
                $ada = $this->db->table('hari_libur')->where('tanggal', $tgl)->countAllResults() > 0;
                if (! $ada) {
                    $this->db->table('hari_libur')->insert(['tanggal' => $tgl, 'keterangan' => $ket]);
                }
            }
        }

        // ---------- Pengaturan awal ----------
        // Koordinat bawaan = pusat Kota Merauke; admin WAJIB menyesuaikan
        // ke titik persis RSUD melalui menu Pengaturan.
        $bawaan = [
            'lokasi_lat'          => '-8.4991120',
            'lokasi_lng'          => '140.4049840',
            'radius_meter'        => '100',
            'toleransi_menit'     => '5',
            'izinkan_pilih_shift' => '1',
            'wajib_selfie'        => '1',
            'minggu_libur'        => '0',
            'nama_instansi'       => 'RSUD Merauke',
            'api_key'             => bin2hex(random_bytes(20)),
        ];
        foreach ($bawaan as $kunci => $nilai) {
            $this->db->query(
                'INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)',
                [$kunci, $nilai]
            );
        }
    }
}
