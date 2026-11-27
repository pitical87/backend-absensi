<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_lupa_absen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('tanggal');
            $table->foreignId('shift_id')->nullable()->constrained('shift')->nullOnDelete()->cascadeOnUpdate();
            $table->text('alasan');
            $table->string('lampiran', 255)->nullable();
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->string('catatan_keputusan', 255)->nullable();
            $table->dateTime('diproses_pada')->nullable();
            $table->foreignId('absensi_id')->nullable()->constrained('absensi')->nullOnDelete()->cascadeOnUpdate();
            $table->dateTime('created_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['tanggal', 'status']);
            $table->index(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_lupa_absen');
    }
};
