<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Subjek akun Google (id yang stabil, tidak berubah walau email
            // diganti). Dipakai memastikan id_token yang masuk benar milik
            // akun Google yang pernah menaut ke pengguna ini.
            $table->string('google_sub', 191)->nullable()->after('email_verified_at');
            $table->unique('google_sub');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
            $table->dropColumn('google_sub');
        });
    }
};
