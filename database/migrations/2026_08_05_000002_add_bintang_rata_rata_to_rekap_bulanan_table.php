<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_bulanan', function (Blueprint $table) {
            $table->decimal('bintang_rata_rata', 2, 1)->nullable()
                ->after('persentase');
        });
    }

    public function down(): void
    {
        Schema::table('rekap_bulanan', function (Blueprint $table) {
            $table->dropColumn('bintang_rata_rata');
        });
    }
};
