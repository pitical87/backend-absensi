<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('tanggal');
            $table->foreignId('jadwal_shift_id')->nullable()->constrained('jadwal_shift')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('shift_lama_id')->nullable()->constrained('shift')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('shift_baru_id')->constrained('shift')->cascadeOnDelete()->cascadeOnUpdate();
            $table->text('alasan');
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedInteger('diproses_oleh')->nullable();
            $table->string('catatan_keputusan', 255)->nullable();
            $table->dateTime('diproses_pada')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['tanggal', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_jadwal');
    }
};
