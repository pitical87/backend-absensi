<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->foreignId('atasan_id')->nullable()->after('punya_sub')
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::table('sub_unit', function (Blueprint $table) {
            $table->foreignId('atasan_id')->nullable()->after('nama')
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_id');
        });

        Schema::table('sub_unit', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atasan_id');
        });
    }
};
