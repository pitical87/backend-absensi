<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_template_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_unit_id')->constrained('sub_unit')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->foreignId('shift_id')->constrained('shift')->cascadeOnDelete();
            $table->unique(['sub_unit_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_template_mingguan');
    }
};
