<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin_persetujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_izin')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('tahap');
            $table->string('posisi_tahap', 50);
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak', 'Dilewati'])->default('Menunggu');
            $table->unsignedInteger('oleh_user_id')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->dateTime('waktu')->nullable();

            $table->index(['pengajuan_id', 'tahap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_persetujuan');
    }
};
