<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->enum('kategori', ['Pagi', 'Sore', 'Malam']);
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->boolean('lintas_hari')->default(false);
            $table->boolean('aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift');
    }
};
