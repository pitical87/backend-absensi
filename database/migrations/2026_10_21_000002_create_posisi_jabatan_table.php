<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posisi_jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50)->unique();
            $table->unsignedTinyInteger('tingkat')->default(0)
                ->comment('Awal tahap persetujuan izin pemohon: 0 = tahap 1, dst. Penyetuju tahap t = posisi tingkat t.');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        if (DB::table('posisi_jabatan')->count() === 0) {
            $waktu = now();
            foreach ([
                ['Staf', 0, 1],
                ['Koordinator/Kepala Unit/Ruang/Instalasi', 1, 2],
                ['Kepala Seksi/Sub Bagian', 2, 3],
                ['Kepala Bidang/Bagian', 3, 4],
                ['HRD', 4, 5],
                ['Direktur', 5, 6],
            ] as [$nama, $tingkat, $urutan]) {
                DB::table('posisi_jabatan')->insert([
                    'nama' => $nama,
                    'tingkat' => $tingkat,
                    'urutan' => $urutan,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posisi_jabatan');
    }
};
