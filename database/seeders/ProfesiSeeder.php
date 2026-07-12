<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfesiSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('profesi')->count() > 0) {
            return;
        }

        $rows = [];
        foreach (['Dokter', 'Perawat', 'Bidan', 'A nalis', 'Apoteker', 'Asisten Apoteker',
                  'Sanitasi', 'Radiografer', 'Rekam Medik', 'Administrasi', 'Tenaga Teknis',
                  'Security', 'Cleaning Service', 'Gardener', 'Parkiran'] as $n) {
            $rows[] = ['nama' => $n];
        }
        DB::table('profesi')->insert($rows);
    }
}
