<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('login_attempts', 'sumber')) {
                $table->string('sumber', 20)->nullable()->after('ip')->comment('web | api | google');
            }
            if (! Schema::hasColumn('login_attempts', 'user_agent')) {
                $table->string('user_agent', 255)->nullable()->after('sumber');
            }
        });
    }

    public function down(): void
    {
        Schema::table('login_attempts', function (Blueprint $table) {
            $cols = array_values(array_filter(
                ['sumber', 'user_agent'],
                fn ($c) => Schema::hasColumn('login_attempts', $c)
            ));

            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
