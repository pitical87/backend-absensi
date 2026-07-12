<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PengaturanSeeder extends Seeder
{
    public function run(): void
    {
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
            DB::table('pengaturan')->insertOrIgnore([
                'kunci' => $kunci,
                'nilai' => $nilai,
            ]);
        }
    }
}
