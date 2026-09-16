<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('perangkat', 150)->nullable()->after('token');
            $table->string('ip', 45)->nullable()->after('perangkat');
            $table->string('user_agent', 255)->nullable()->after('ip');
            $table->timestamp('last_aktivitas')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn(['perangkat', 'ip', 'user_agent', 'last_aktivitas']);
        });
    }
};