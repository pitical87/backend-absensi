<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absen_lembur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_lembur_id')->constrained('pengajuan_lembur')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('tanggal');
            $table->dateTime('waktu_masuk')->nullable();
            $table->dateTime('waktu_pulang')->nullable();
            $table->decimal('lat_masuk', 10, 7)->nullable();
            $table->decimal('lng_masuk', 10, 7)->nullable();
            $table->decimal('lat_pulang', 10, 7)->nullable();
            $table->decimal('lng_pulang', 10, 7)->nullable();
            $table->string('foto_masuk', 190)->nullable();
            $table->string('foto_pulang', 190)->nullable();
            $table->unsignedInteger('durasi_menit')->nullable();
            $table->enum('status_masuk', ['Tepat Waktu', 'Terlambat'])->nullable();
            $table->unsignedInteger('menit_terlambat')->default(0);
            $table->tinyInteger('bintang_masuk')->nullable();
            $table->tinyInteger('bintang_pulang')->nullable();
            $table->decimal('bintang_harian', 2, 1)->nullable();
            $table->tinyInteger('flag_anomali')->default(0);
            $table->text('catatan_anomali')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->unique('pengajuan_lembur_id');
            $table->index(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absen_lembur');
    }
};
