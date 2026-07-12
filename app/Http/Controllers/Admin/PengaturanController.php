<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengaturanController extends Controller
{
    public function index()
    {
        return view('admin.pengaturan', [
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

    public function simpan(Request $request)
    {
        $lat  = trim((string) $request->input('lokasi_lat'));
        $lng  = trim((string) $request->input('lokasi_lng'));
        $rad  = (int) $request->input('radius_meter');
        $tol  = (int) $request->input('toleransi_menit');
        $nama = trim((string) $request->input('nama_instansi'));

        if (! is_numeric($lat) || ! is_numeric($lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180) {
            return redirect('admin/pengaturan')
                ->with('flash_gagal', 'Koordinat tidak valid. Gunakan angka desimal, mis. -8.4991120');
        }

        simpan_pengaturan('lokasi_lat', (string) round((float) $lat, 7));
        simpan_pengaturan('lokasi_lng', (string) round((float) $lng, 7));
        simpan_pengaturan('radius_meter', (string) max(10, min(5000, $rad)));
        simpan_pengaturan('toleransi_menit', (string) max(0, min(120, $tol)));
        simpan_pengaturan('izinkan_pilih_shift', $request->input('izinkan_pilih_shift') ? '1' : '0');
        simpan_pengaturan('wajib_selfie', $request->input('wajib_selfie') ? '1' : '0');
        simpan_pengaturan('nama_instansi', $nama !== '' ? $nama : 'RSUD Merauke');
        catat_aktivitas('Ubah Pengaturan', 'Titik/radius GPS, toleransi, atau opsi aplikasi diperbarui');

        return redirect('admin/pengaturan')->with('flash_sukses', 'Pengaturan berhasil disimpan.');
    }

    public function gantiApiKey()
    {
        simpan_pengaturan('api_key', bin2hex(random_bytes(20)));
        catat_aktivitas('Ganti Kunci API', 'Kunci API integrasi SIMRS dibuat ulang');
        return redirect('admin/pengaturan')
            ->with('flash_sukses', 'Kunci API baru telah dibuat. Perbarui konfigurasi pada SIMRS.');
    }

    public function backup()
    {
        $nama   = 'backup_absensi_' . now()->format('Ymd_His') . '.sql';
        $keluar = "-- Backup Sistem Absensi Pegawai RSUD Merauke\n"
                . '-- Dibuat: ' . now()->format('Y-m-d H:i:s') . " WIT\n"
                . "-- Pulihkan dengan mengimpor berkas ini melalui phpMyAdmin.\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tabel = array_map(static fn ($r) => array_values((array) $r)[0],
            DB::select('SHOW TABLES'));

        foreach ($tabel as $t) {
            $create  = DB::select('SHOW CREATE TABLE `' . $t . '`');
            $createRow = (array) $create[0];
            $keluar .= "DROP TABLE IF EXISTS `{$t}`;\n"
                     . ($createRow['Create Table'] ?? '') . ";\n\n";

            $offset = 0;
            while (true) {
                $rows = DB::table($t)->skip($offset)->take(500)->get()->all();
                if (! $rows) break;
                $rowsArr = array_map(fn($r) => (array) $r, $rows);
                $kolom = '`' . implode('`,`', array_keys($rowsArr[0])) . '`';
                $nilai = [];
                foreach ($rowsArr as $r) {
                    $sel = array_map(
                        static fn ($v) => $v === null ? 'NULL' : DB::connection()->getPdo()->quote($v),
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

        return response($keluar, 200, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nama . '"',
        ]);
    }
}
