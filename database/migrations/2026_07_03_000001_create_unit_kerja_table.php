<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->boolean('punya_sub')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};
