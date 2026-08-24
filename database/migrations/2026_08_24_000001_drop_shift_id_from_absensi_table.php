<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('absensi', 'shift_id')) {
            return;
        }

        Schema::table('absensi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('shift')->nullOnDelete()->nullOnUpdate();
        });
    }
};
