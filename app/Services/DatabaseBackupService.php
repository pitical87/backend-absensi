<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupService
{
    public function backup(): StreamedResponse
    {
        $nama = 'backup_absensi_' . date('Ymd_His') . '.sql';

        $headers = [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nama . '"',
        ];

        return new StreamedResponse(function () {
            $output = fopen('php://output', 'w');

            fwrite($output, "-- Backup Sistem Absensi Pegawai RSUD Merauke\n");
            fwrite($output, '-- Dibuat: ' . date('Y-m-d H:i:s') . " WIT\n");
            fwrite($output, "-- Pulihkan dengan mengimpor berkas ini melalui phpMyAdmin.\n\n");
            fwrite($output, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map(fn ($r) => array_values((array) $r)[0], $tables);

            foreach ($tableNames as $table) {
                $create = DB::select("SHOW CREATE TABLE `{$table}`");
                $createSql = $create[0]->{'Create Table'} ?? '';

                fwrite($output, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($output, $createSql . ";\n\n");

                $offset = 0;
                while (true) {
                    $rows = DB::table($table)->skip($offset)->take(500)->get()->toArray();
                    if (empty($rows)) break;

                    $kolom = '`' . implode('`,`', array_keys((array) $rows[0])) . '`';
                    $nilai = [];
                    foreach ($rows as $row) {
                        $sel = array_map(function ($v) {
                            return $v === null ? 'NULL' : DB::getPdo()->quote($v);
                        }, array_values((array) $row));
                        $nilai[] = '(' . implode(',', $sel) . ')';
                    }
                    fwrite($output, "INSERT INTO `{$table}` ({$kolom}) VALUES\n");
                    fwrite($output, implode(",\n", $nilai) . ";\n");
                    $offset += 500;
                }
                fwrite($output, "\n");
            }

            fwrite($output, "SET FOREIGN_KEY_CHECKS = 1;\n");
            fclose($output);
        }, 200, $headers);
    }
}
