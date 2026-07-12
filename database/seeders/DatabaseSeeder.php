<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitKerjaSeeder::class,
            ProfesiSeeder::class,
            ShiftSeeder::class,
            JabatanSeeder::class,
            HariLiburSeeder::class,
            PengaturanSeeder::class,
        ]);
    }
}
