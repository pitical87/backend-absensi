<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->string('ip', 45);
            $table->boolean('sukses')->default(false);
            $table->dateTime('waktu');

            $table->index(['email', 'waktu']);
            $table->index(['ip', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
