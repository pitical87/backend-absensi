<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite needs table rebuild to change unique constraints.
        DB::transaction(function () {
            DB::statement('CREATE TABLE "absensi_new" (
                "id" integer primary key autoincrement not null,
                "user_id" integer not null,
                "sesi" integer not null default 1,
                "tanggal" date not null,
                "waktu_masuk" datetime,
                "waktu_pulang" datetime,
                "lat_masuk" numeric,
                "lng_masuk" numeric,
                "lat_pulang" numeric,
                "lng_pulang" numeric,
                "foto_masuk" varchar,
                "foto_pulang" varchar,
                "status_masuk" varchar,
                "menit_terlambat" integer not null default 0,
                "total_menit_kerja" integer,
                "flag_anomali" tinyint(1) not null default 0,
                "catatan_anomali" text,
                "status_pulang" varchar,
                "menit_awal_pulang" integer not null default 0,
                "bintang_masuk" integer,
                "bintang_pulang" integer,
                "bintang_harian" numeric,
                foreign key("user_id") references users("id") on delete cascade on update cascade
            )');

            DB::statement('INSERT INTO "absensi_new"
                ("id","user_id","sesi","tanggal","waktu_masuk","waktu_pulang",
                 "lat_masuk","lng_masuk","lat_pulang","lng_pulang",
                 "foto_masuk","foto_pulang","status_masuk","menit_terlambat",
                 "total_menit_kerja","flag_anomali","catatan_anomali",
                 "status_pulang","menit_awal_pulang","bintang_masuk",
                 "bintang_pulang","bintang_harian")
                SELECT "id","user_id",1,"tanggal","waktu_masuk","waktu_pulang",
                 "lat_masuk","lng_masuk","lat_pulang","lng_pulang",
                 "foto_masuk","foto_pulang","status_masuk","menit_terlambat",
                 "total_menit_kerja","flag_anomali","catatan_anomali",
                 "status_pulang","menit_awal_pulang","bintang_masuk",
                 "bintang_pulang","bintang_harian"
                FROM "absensi"');

            DB::statement('DROP TABLE "absensi"');
            DB::statement('ALTER TABLE "absensi_new" RENAME TO "absensi"');
            DB::statement('CREATE UNIQUE INDEX "absensi_user_id_tanggal_sesi_unique" on "absensi" ("user_id", "tanggal", "sesi")');
            DB::statement('CREATE INDEX "absensi_tanggal_index" on "absensi" ("tanggal")');
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::statement('CREATE TABLE "absensi_old" (
                "id" integer primary key autoincrement not null,
                "user_id" integer not null,
                "tanggal" date not null,
                "waktu_masuk" datetime,
                "waktu_pulang" datetime,
                "lat_masuk" numeric,
                "lng_masuk" numeric,
                "lat_pulang" numeric,
                "lng_pulang" numeric,
                "foto_masuk" varchar,
                "foto_pulang" varchar,
                "status_masuk" varchar,
                "menit_terlambat" integer not null default 0,
                "total_menit_kerja" integer,
                "flag_anomali" tinyint(1) not null default 0,
                "catatan_anomali" text,
                "status_pulang" varchar,
                "menit_awal_pulang" integer not null default 0,
                "bintang_masuk" integer,
                "bintang_pulang" integer,
                "bintang_harian" numeric,
                foreign key("user_id") references users("id") on delete cascade on update cascade
            )');

            DB::statement('INSERT INTO "absensi_old"
                ("id","user_id","tanggal","waktu_masuk","waktu_pulang",
                 "lat_masuk","lng_masuk","lat_pulang","lng_pulang",
                 "foto_masuk","foto_pulang","status_masuk","menit_terlambat",
                 "total_menit_kerja","flag_anomali","catatan_anomali",
                 "status_pulang","menit_awal_pulang","bintang_masuk",
                 "bintang_pulang","bintang_harian")
                SELECT "id","user_id","tanggal","waktu_masuk","waktu_pulang",
                 "lat_masuk","lng_masuk","lat_pulang","lng_pulang",
                 "foto_masuk","foto_pulang","status_masuk","menit_terlambat",
                 "total_menit_kerja","flag_anomali","catatan_anomali",
                 "status_pulang","menit_awal_pulang","bintang_masuk",
                 "bintang_pulang","bintang_harian"
                FROM "absensi"');

            DB::statement('DROP TABLE "absensi"');
            DB::statement('ALTER TABLE "absensi_old" RENAME TO "absensi"');
            DB::statement('CREATE UNIQUE INDEX "absensi_user_id_tanggal_unique" on "absensi" ("user_id", "tanggal")');
            DB::statement('CREATE INDEX "absensi_tanggal_index" on "absensi" ("tanggal")');
        });
    }
};
