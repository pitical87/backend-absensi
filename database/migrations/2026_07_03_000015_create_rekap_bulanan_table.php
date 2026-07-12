<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('total_hari_efektif')->default(0);
            $table->unsignedInteger('total_hadir')->default(0);
            $table->unsignedInteger('total_tepat_waktu')->default(0);
            $table->unsignedInteger('total_terlambat')->default(0);
            $table->unsignedInteger('total_alpa')->default(0);
            $table->unsignedInteger('total_izin')->default(0);
            $table->unsignedInteger('total_sakit')->default(0);
            $table->unsignedInteger('total_cuti')->default(0);
            $table->unsignedInteger('total_dinas_luar')->default(0);
            $table->unsignedInteger('total_libur')->default(0);
            $table->unsignedInteger('total_menit_kerja')->default(0);
            $table->decimal('persentase', 5, 2)->default(0);
            $table->dateTime('generated_at')->nullable();

            $table->unique(['user_id', 'bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_bulanan');
    }
};
