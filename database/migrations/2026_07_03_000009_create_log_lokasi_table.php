<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_lokasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('absensi_id')->nullable()->constrained('absensi')->nullOnDelete()->nullOnUpdate();
            $table->enum('tipe', ['datang', 'pulang']);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('akurasi', 8, 2)->nullable();
            $table->decimal('jarak_meter', 10, 2)->nullable();
            $table->boolean('ditolak')->default(false);
            $table->dateTime('waktu');

            $table->index(['user_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_lokasi');
    }
};
