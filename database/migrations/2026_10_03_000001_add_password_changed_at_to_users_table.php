<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak waktu password terakhir diganti.
     *
     * Null berarti belum pernah diganti sejak akun dibuat, sehingga masih memakai
     * password awal. Dipakai untuk pengecekan "pernah ganti password atau belum"
     * di panel admin dan sebagai peringatan pada aplikasi mobile.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password_hash');
            }
            if (! Schema::hasIndex('users', 'users_password_changed_at_index')) {
                $table->index('password_changed_at', 'users_password_changed_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasIndex('users', 'users_password_changed_at_index')) {
                $table->dropIndex('users_password_changed_at_index');
            }
            if (Schema::hasColumn('users', 'password_changed_at')) {
                $table->dropColumn('password_changed_at');
            }
        });
    }
};
