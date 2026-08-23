<?php

use App\Http\Controllers\Api\MobileController;
use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (){
    Route::post('login',[MobileController::class, 'login']);
    Route::get('register/master',[MobileController::class, 'registerDataMaster']);
    Route::post('register',[MobileController::class, 'register']);
    Route::middleware('mobile.auth')->group(function(){
        Route::get('me',[MobileController::class, 'me']);
        Route::post('logout',[MobileController::class, 'logout']);
        Route::post('absen',[MobileController::class, 'absen']);
        Route::get('status',[MobileController::class,'status']);
        Route::get('riwayat',[MobileController::class,'riwayatAbsensi']);
        Route::get('statistik', [MobileController::class, 'statistik']);
        Route::get('performa/bulan', [MobileController::class, 'performaBulan']);
        Route::get('jadwal', [MobileController::class, 'jadwal']);
        Route::get('izin',[MobileController::class,"riwayatIzin"]);
        Route::post('izin',[MobileController::class,"pengajuanIzin"]);
        Route::delete('izin/{id}',[MobileController::class,"deleteIzin"]);
        Route::get('izin/today',[MobileController::class,"getTodayIzin"]);
        Route::get('izin/total',[MobileController::class,"getIzinMenungguTotal"]);
        Route::get('izin/detail',[MobileController::class,"getDetailIzinMenunggu"]);
        Route::post('izin/proses',[MobileController::class,"prosesIzinMenunggu"]);
        Route::get('izin/riwayat-persetujuan',[MobileController::class,"getRiwayatPersetujuan"]);
        Route::get('logbook/simrs',[MobileController::class,"logbookSimrs"]);
        Route::get('logbook/simrs/{jenis}',[MobileController::class,"logbookSimrs"]);
        Route::get('logbook',[MobileController::class,"logbookData"]);
        Route::post('logbook/simpan',[MobileController::class,"logbookSimpan"]);
        Route::post('logbook/simpan-bulk',[MobileController::class,"logbookSimpanBulk"]);
        Route::post('logbook/ubah',[MobileController::class,"logbookUbah"]);
        Route::delete('logbook/template/{id}',[MobileController::class,"templateHapus"]);
        Route::post('logbook/template',[MobileController::class,"templateSimpan"]);
        Route::post('logbook/template/ubah',[MobileController::class,"templateUbah"]);
        Route::delete('logbook/{id}',[MobileController::class,"logbookHapus"]);
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

