<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori notifikasi, terpisah dari "tipe" yang hanya menggambarkan warna
     * (info/warning/danger/success). Dipakai aplikasi untuk mengelompokkan
     * notifikasi, mis. tab "Keamanan" untuk pengingat password.
     */
    public function up(): void
    {
        Schema::table('notifikasis', function (Blueprint $table) {
            if (! Schema::hasColumn('notifikasis', 'kategori')) {
                $table->string('kategori', 30)->nullable()->after('tipe')->comment('password, keamanan, jadwal, izin, lembur, sistem');
            }
            if (! Schema::hasIndex('notifikasis', 'notifikasis_user_kategori_index')) {
                $table->index(['user_id', 'kategori'], 'notifikasis_user_kategori_index');
            }
            if (! Schema::hasIndex('notifikasis', 'notifikasis_user_read_index')) {
                $table->index(['user_id', 'is_read'], 'notifikasis_user_read_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifikasis', function (Blueprint $table) {
            if (Schema::hasIndex('notifikasis', 'notifikasis_user_read_index')) {
                $table->dropIndex('notifikasis_user_read_index');
            }
            if (Schema::hasIndex('notifikasis', 'notifikasis_user_kategori_index')) {
                $table->dropIndex('notifikasis_user_kategori_index');
            }
            if (Schema::hasColumn('notifikasis', 'kategori')) {
                $table->dropColumn('kategori');
            }
        });
    }
};
