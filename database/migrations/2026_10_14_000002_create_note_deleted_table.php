<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_deleted', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('User yang dihapus; null bila sudah dihapus permanen');
            $table->string('nama', 150)->nullable()->comment('Snapshot nama saat dihapus');
            $table->string('email', 150)->nullable()->comment('Snapshot email saat dihapus');
            $table->text('catatan')->comment('Alasan penghapusan: pegawai keluar, meninggal, dll');
            $table->foreignId('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Admin yang menghapus');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_deleted');
    }
};
