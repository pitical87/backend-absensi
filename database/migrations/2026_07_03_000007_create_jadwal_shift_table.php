<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('shift_id')->constrained('shift')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('tanggal_berlaku');
            $table->unsignedInteger('diubah_oleh')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['user_id', 'tanggal_berlaku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_shift');
    }
};
