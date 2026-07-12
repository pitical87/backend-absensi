<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JabatanSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('jabatan')->count() > 0) {
            return;
        }

        DB::table('jabatan')->insert([
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
            ['id' => 12, 'nama' => 'Kabid Sarpras dan Penunjang',            'kategori' => 'Kepala Bidang',
             'induk_id' => 1,    'unit_label' => 'Bidang Sarpras dan Penunjang',     'urutan' => 4],
            ['id' => 13, 'nama' => 'Kasi Sarpras',                            'kategori' => 'Kepala Seksi',
             'induk_id' => 12,   'unit_label' => null,                                'urutan' => 1],
            ['id' => 14, 'nama' => 'Kasi Penunjang Medik',                    'kategori' => 'Kepala Seksi',
             'induk_id' => 12,   'unit_label' => null,                                'urutan' => 2],
        ]);
    }
}
