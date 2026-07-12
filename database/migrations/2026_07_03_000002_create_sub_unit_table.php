<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerja')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('nama', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_unit');
    }
};
