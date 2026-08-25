<?php

use App\Http\Controllers\Api\AbsenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IzinController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\LogbookController;
use App\Http\Controllers\Api\PerubahanJadwalController;
use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (){
    // Auth
    Route::post('login',[AuthController::class, 'login']);
    Route::get('register/master',[AuthController::class, 'registerDataMaster']);
    Route::post('register',[AuthController::class, 'register']);
    Route::middleware('mobile.auth')->group(function(){
        Route::get('me',[AuthController::class, 'me']);
        Route::post('logout',[AuthController::class, 'logout']);

        // Absensi
        Route::post('absen',[AbsenController::class, 'absen']);
        Route::get('status',[AbsenController::class,'status']);
        Route::get('riwayat',[AbsenController::class,'riwayatAbsensi']);

        // Rekap & statistik
        Route::get('statistik', [RekapController::class, 'statistik']);
        Route::get('performa/bulan', [RekapController::class, 'performaBulan']);
        Route::get('rekap', [RekapController::class, 'rekapBulanan']);
        Route::get('keterlambatan', [RekapController::class, 'rekapKeterlambatan']);
        Route::get('pegawai-teladan', [RekapController::class, 'pegawaiTeladan']);

        // Jadwal shift
        Route::get('jadwal', [JadwalController::class, 'jadwal']);
        Route::get('jadwal/hari-ini', [JadwalController::class, 'jadwalHariIni']);
        Route::get('jadwal/mingguan', [JadwalController::class, 'jadwalMingguan']);
        Route::get('jadwal/bulanan', [JadwalController::class, 'jadwalBulanan']);

        // Izin / cuti / sakit
        Route::get('izin',[IzinController::class,"riwayatIzin"]);
        Route::post('izin',[IzinController::class,"pengajuanIzin"]);
        Route::delete('izin/{id}',[IzinController::class,"deleteIzin"]);
        Route::get('izin/today',[IzinController::class,"getTodayIzin"]);
        Route::get('izin/total',[IzinController::class,"getIzinMenungguTotal"]);
        Route::get('izin/detail',[IzinController::class,"getDetailIzinMenunggu"]);
        Route::post('izin/proses',[IzinController::class,"prosesIzinMenunggu"]);
        Route::get('izin/riwayat-persetujuan',[IzinController::class,"getRiwayatPersetujuan"]);

        // Pengajuan perubahan jadwal shift
        Route::get('perubahan-jadwal', [PerubahanJadwalController::class, 'daftar']);
        Route::post('perubahan-jadwal', [PerubahanJadwalController::class, 'ajukan']);
        Route::delete('perubahan-jadwal/{id}', [PerubahanJadwalController::class, 'batal']);
        Route::get('perubahan-jadwal/total', [PerubahanJadwalController::class, 'menungguTotal']);
        Route::get('perubahan-jadwal/menunggu', [PerubahanJadwalController::class, 'menungguDaftar']);
        Route::post('perubahan-jadwal/proses', [PerubahanJadwalController::class, 'proses']);
        Route::get('perubahan-jadwal/riwayat-persetujuan', [PerubahanJadwalController::class, 'riwayatPersetujuan']);

        // Logbook & template
        Route::get('logbook/simrs',[LogbookController::class,"logbookSimrs"]);
        Route::get('logbook/simrs/{jenis}',[LogbookController::class,"logbookSimrs"]);
        Route::get('logbook',[LogbookController::class,"logbookData"]);
        Route::post('logbook/simpan',[LogbookController::class,"logbookSimpan"]);
        Route::post('logbook/simpan-bulk',[LogbookController::class,"logbookSimpanBulk"]);
        Route::post('logbook/ubah',[LogbookController::class,"logbookUbah"]);
        Route::get('logbook/template',[LogbookController::class,"templateData"]);
        Route::delete('logbook/template/{id}',[LogbookController::class,"templateHapus"]);
        Route::post('logbook/template',[LogbookController::class,"templateSimpan"]);
        Route::post('logbook/template/ubah',[LogbookController::class,"templateUbah"]);
        Route::delete('logbook/{id}',[LogbookController::class,"logbookHapus"]);
    });
});

Route::prefix('api/v1')->middleware('api.key')->group(function () {
    Route::get('ping', [V1Controller::class, 'ping'])->name('api.ping');
    Route::get('pegawai', [V1Controller::class, 'pegawai'])->name('api.pegawai');
    Route::get('pegawai/{id}', [V1Controller::class, 'getPegawai'])->name('api.pegawai.show');
    Route::get('absensi', [V1Controller::class, 'absensi'])->name('api.absensi');
    Route::get('rekap', [V1Controller::class, 'rekap'])->name('api.rekap');
    Route::get('izin', [V1Controller::class, 'izin'])->name('api.izin');
});
