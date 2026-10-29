<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->string('kategori', 100)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('jabatan_kategori', 100)->default('Staf/Pelaksana')->change();
        });
    }

    public function down(): void
    {
        Schema::table('jabatan', function (Blueprint $table) {
            $table->enum('kategori', [
                'Direktur', 'Kepala Bidang', 'Kepala Bagian',
                'Kepala Seksi', 'Kepala Sub Bagian',
            ])->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('jabatan_kategori', 30)->default('Staf/Pelaksana')->change();
        });
    }
};
