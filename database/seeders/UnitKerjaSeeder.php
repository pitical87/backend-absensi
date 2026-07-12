<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('unit_kerja')->count() > 0) {
            return;
        }

        DB::table('unit_kerja')->insert([
            ['id' => 1, 'nama' => 'Rawat Inap', 'punya_sub' => 1],
            ['id' => 2, 'nama' => 'Rawat Jalan', 'punya_sub' => 1],
            ['id' => 3, 'nama' => 'Farmasi', 'punya_sub' => 0],
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
        DB::table('sub_unit')->insert($sub);
    }
}
