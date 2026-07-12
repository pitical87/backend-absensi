<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('shift')->count() > 0) {
            return;
        }

        DB::table('shift')->insert([
            ['kategori' => 'Pagi', 'jam_masuk' => '08:00:00', 'jam_pulang' => '14:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Pagi', 'jam_masuk' => '06:00:00', 'jam_pulang' => '13:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Pagi', 'jam_masuk' => '05:00:00', 'jam_pulang' => '12:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Sore', 'jam_masuk' => '12:00:00', 'jam_pulang' => '21:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Sore', 'jam_masuk' => '13:00:00', 'jam_pulang' => '20:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Sore', 'jam_masuk' => '12:00:00', 'jam_pulang' => '20:00:00', 'lintas_hari' => 0, 'aktif' => 1],
            ['kategori' => 'Malam', 'jam_masuk' => '21:00:00', 'jam_pulang' => '08:00:00', 'lintas_hari' => 1, 'aktif' => 1],
            ['kategori' => 'Malam', 'jam_masuk' => '20:00:00', 'jam_pulang' => '06:00:00', 'lintas_hari' => 1, 'aktif' => 1],
        ]);
    }
}
