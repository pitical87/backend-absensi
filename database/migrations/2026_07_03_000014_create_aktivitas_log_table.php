<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('aksi', 60);
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('waktu');

            $table->index(['user_id', 'waktu']);
            $table->index('waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_log');
    }
};
