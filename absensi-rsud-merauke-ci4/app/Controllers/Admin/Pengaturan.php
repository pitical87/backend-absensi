<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Pengaturan extends BaseController
{
    public function index()
    {
        return view('admin/pengaturan', [
            'judulHalaman' => 'Pengaturan',
            'menuAktif'    => 'pengaturan',
            'lat'          => pengaturan('lokasi_lat', '-8.4991120'),
            'lng'          => pengaturan('lokasi_lng', '140.4049840'),
            'rad'          => pengaturan('radius_meter', '100'),
            'tol'          => pengaturan('toleransi_menit', '5'),
            'izin'         => pengaturan('izinkan_pilih_shift', '1') === '1',
            'selfie'       => pengaturan('wajib_selfie', '1') === '1',
            'nama'         => pengaturan('nama_instansi', 'RSUD Merauke'),
            'apiKey'       => pengaturan('api_key', ''),
        ]);
    }

    public function simpan()
    {
        $lat  = trim((string) $this->request->getPost('lokasi_lat'));
        $lng  = trim((string) $this->request->getPost('lokasi_lng'));
        $rad  = (int) $this->request->getPost('radius_meter');
        $tol  = (int) $this->request->getPost('toleransi_menit');
        $nama = trim((string) $this->request->getPost('nama_instansi'));

        if (! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180) {
            return redirect()->to('admin/pengaturan')
                ->with('flash_gagal', 'Koordinat tidak valid. Gunakan angka desimal, mis. -8.4991120');
        }

        simpan_pengaturan('lokasi_lat', (string) round((float) $lat, 7));
        simpan_pengaturan('lokasi_lng', (string) round((float) $lng, 7));
        simpan_pengaturan('radius_meter', (string) max(10, min(5000, $rad)));
        simpan_pengaturan('toleransi_menit', (string) max(0, min(120, $tol)));
        simpan_pengaturan('izinkan_pilih_shift', $this->request->getPost('izinkan_pilih_shift') ? '1' : '0');
        simpan_pengaturan('wajib_selfie', $this->request->getPost('wajib_selfie') ? '1' : '0');
        simpan_pengaturan('nama_instansi', $nama !== '' ? $nama : 'RSUD Merauke');
        catat_aktivitas('Ubah Pengaturan', 'Titik/radius GPS, toleransi, atau opsi aplikasi diperbarui');

        return redirect()->to('admin/pengaturan')->with('flash_sukses', 'Pengaturan berhasil disimpan.');
    }

    public function gantiApiKey()
    {
        simpan_pengaturan('api_key', bin2hex(random_bytes(20)));
        catat_aktivitas('Ganti Kunci API', 'Kunci API integrasi SIMRS dibuat ulang');
        return redirect()->to('admin/pengaturan')
            ->with('flash_sukses', 'Kunci API baru telah dibuat. Perbarui konfigurasi pada SIMRS.');
    }

    /**
     * Backup database murni-PHP (tanpa mysqldump) — diunduh sebagai berkas .sql.
     */
    public function backup()
    {
        $db     = $this->db;
        $nama   = 'backup_absensi_' . date('Ymd_His') . '.sql';
        $keluar = "-- Backup Sistem Absensi Pegawai RSUD Merauke\n"
                . '-- Dibuat: ' . date('Y-m-d H:i:s') . " WIT\n"
                . "-- Pulihkan dengan mengimpor berkas ini melalui phpMyAdmin.\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tabel = array_map(static fn ($r) => array_values($r)[0],
            $db->query('SHOW TABLES')->getResultArray());

        foreach ($tabel as $t) {
            $create  = $db->query('SHOW CREATE TABLE `' . $t . '`')->getRowArray();
            $keluar .= "DROP TABLE IF EXISTS `{$t}`;\n"
                     . ($create['Create Table'] ?? '') . ";\n\n";

            $offset = 0;
            while (true) {
                $rows = $db->table($t)->get(500, $offset)->getResultArray();
                if (! $rows) break;
                $kolom = '`' . implode('`,`', array_keys($rows[0])) . '`';
                $nilai = [];
                foreach ($rows as $r) {
                    $sel = array_map(
                        static fn ($v) => $v === null ? 'NULL' : $db->escape($v),
                        array_values($r)
                    );
                    $nilai[] = '(' . implode(',', $sel) . ')';
                }
                $keluar .= "INSERT INTO `{$t}` ({$kolom}) VALUES\n"
                         . implode(",\n", $nilai) . ";\n";
                $offset += 500;
            }
            $keluar .= "\n";
        }
        $keluar .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        catat_aktivitas('Backup Database', $nama . ' (' . count($tabel) . ' tabel, '
            . number_format(strlen($keluar) / 1024, 0, ',', '.') . ' KB)');

        return $this->response
            ->setHeader('Content-Type', 'application/sql; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nama . '"')
            ->setBody($keluar);
    }
}
