<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keterlambatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_id')->constrained('absensi')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedSmallInteger('menit_telat')->default(0);
            $table->unsignedTinyInteger('bintang_masuk')->nullable();
            $table->unsignedSmallInteger('menit_awal_pulang')->default(0);
            $table->unsignedTinyInteger('bintang_pulang')->nullable();
            $table->decimal('total_bintang', 4, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keterlambatan');
    }
};
