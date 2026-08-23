<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 150);
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['Laki-Laki', 'Perempuan'])->nullable();
            $table->string('agama', 30)->nullable();
            $table->string('email', 150)->unique();
            $table->string('no_hp', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerja')->nullOnDelete()->nullOnUpdate();
            $table->foreignId('sub_unit_id')->nullable()->constrained('sub_unit')->nullOnDelete()->nullOnUpdate();
            $table->foreignId('profesi_id')->nullable()->constrained('profesi')->nullOnDelete()->nullOnUpdate();
            $table->string('jabatan_kategori', 30)->default('Staf/Pelaksana');
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->string('posisi', 50)->default('Staf');
            $table->string('status_pegawai', 20)->default('Non-PNS');
            $table->foreignId('seksi_pembina_id')->nullable()->constrained('jabatan')->nullOnDelete()->comment('Seksi/Sub Bagian pembina - jalur persetujuan Staf & Koordinator');
            $table->string('password_hash', 255);
            $table->enum('role', ['admin', 'pegawai'])->default('pegawai');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
