<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atasan_langsung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('atasan_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['user_id', 'atasan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atasan_langsung');
    }
};
