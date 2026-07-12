<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HariLiburSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('hari_libur')->count() > 0) {
            return;
        }

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
            DB::table('hari_libur')->insertOrIgnore([
                'tanggal' => $tgl,
                'keterangan' => $ket,
            ]);
        }
    }
}
