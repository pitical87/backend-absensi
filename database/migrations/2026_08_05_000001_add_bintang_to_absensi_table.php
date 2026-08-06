<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->enum('status_pulang', ['Tepat Waktu', 'Lebih Awal'])->nullable()
                ->after('status_masuk');
            $table->unsignedInteger('menit_awal_pulang')->default(0)
                ->after('menit_terlambat');
            $table->unsignedTinyInteger('bintang_masuk')->nullable()
                ->after('menit_awal_pulang');
            $table->unsignedTinyInteger('bintang_pulang')->nullable()
                ->after('bintang_masuk');
            $table->decimal('bintang_harian', 2, 1)->nullable()
                ->after('bintang_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn([
                'status_pulang', 'menit_awal_pulang',
                'bintang_masuk', 'bintang_pulang', 'bintang_harian',
            ]);
        });
    }
};
