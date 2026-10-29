<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatans', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->boolean('untuk_struktur')->default(true)
                ->comment('1 = tampil pada pilihan kategori Struktur Organisasi / Master Jabatan');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        if (DB::table('jabatans')->count() === 0) {
            $waktu = now();
            foreach ([
                ['Direktur', 1, 1],
                ['Kepala Bidang', 1, 2],
                ['Kepala Bagian', 1, 3],
                ['Kepala Seksi', 1, 4],
                ['Kepala Sub Bagian', 1, 5],
                ['Staf/Pelaksana', 0, 6],
            ] as [$nama, $struktur, $urutan]) {
                DB::table('jabatans')->insert([
                    'nama' => $nama,
                    'untuk_struktur' => $struktur,
                    'urutan' => $urutan,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatans');
    }
};
