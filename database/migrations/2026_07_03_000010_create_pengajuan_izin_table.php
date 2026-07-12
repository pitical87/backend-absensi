<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('jenis', ['Izin', 'Sakit', 'Cuti', 'Dinas Luar']);
            $table->string('jenis_cuti', 40)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->unsignedInteger('lama_hari')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('alamat_izin', 255)->nullable();
            $table->string('lampiran', 190)->nullable();
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedTinyInteger('tahap_aktif')->default(0);
            $table->unsignedInteger('diproses_oleh')->nullable();
            $table->string('catatan_admin', 255)->nullable();
            $table->string('nomor_surat', 80)->nullable();
            $table->string('kode_verifikasi', 16)->nullable();
            $table->unsignedTinyInteger('ttd_digital')->default(0);
            $table->unsignedInteger('ttd_oleh')->nullable();
            $table->dateTime('ttd_waktu')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('processed_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin');
    }
};
