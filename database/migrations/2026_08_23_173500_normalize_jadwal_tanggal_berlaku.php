<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menyeragamkan kolom tanggal_berlaku menjadi string polos 'YYYY-MM-DD'.
     * Sebelumnya formatnya campur ('2026-08-23', '2026-08-23 00:00:00',
     * '2026-08-22T15:00:00.000000Z') sehingga pencocokan tanggal hari ini gagal.
     */
    public function up(): void
    {
        DB::table('jadwal_shift')->orderBy('id')->get()->each(function ($row) {
            $normal = \Carbon\Carbon::parse($row->tanggal_berlaku)->toDateString();

            if ($normal !== $row->tanggal_berlaku) {
                DB::table('jadwal_shift')
                    ->where('id', $row->id)
                    ->update(['tanggal_berlaku' => $normal]);
            }
        });
    }

    public function down(): void
    {
        // Tidak dapat dikembalikan ke format campuran sebelumnya.
    }
};
