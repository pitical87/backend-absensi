<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
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
            $table->enum('status_masuk', ['Tepat Waktu', 'Terlambat'])->nullable();
            $table->unsignedInteger('menit_terlambat')->default(0);
            $table->unsignedInteger('total_menit_kerja')->nullable();
            $table->boolean('flag_anomali')->default(false);
            $table->text('catatan_anomali')->nullable();

            $table->unique(['user_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
