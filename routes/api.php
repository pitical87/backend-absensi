<?php

use App\Http\Controllers\Api\AbsenController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\IzinController;
use App\Http\Controllers\Api\JadwalController;
use App\Http\Controllers\Api\LogbookController;
use App\Http\Controllers\Api\LemburController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\PerubahanJadwalController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\V1Controller;
use App\Http\Controllers\Api\VerifikasiEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function (){
    // Auth
    Route::post('login',[AuthController::class, 'login']);
    Route::post('login/google',[GoogleAuthController::class, 'login']);
    Route::get('register/master',[AuthController::class, 'registerDataMaster']);
    Route::post('register',[AuthController::class, 'register']);
    Route::post('lupa-password',[AuthController::class, 'lupaPassword']);
    Route::middleware('mobile.auth')->group(function(){
        Route::get('me',[AuthController::class, 'me']);
        Route::post('logout',[AuthController::class, 'logout']);

        // Profil
        Route::post('profil',[ProfilController::class, 'update']);

        // Verifikasi email
        Route::get('verifikasi-email',[VerifikasiEmailController::class, 'status']);
        Route::post('verifikasi-email',[VerifikasiEmailController::class, 'kirim']);

        // Notifikasi (tarik per user dari sesi login)
        Route::get('notifikasi',[NotifikasiController::class, 'daftar']);
        Route::get('notifikasi/total',[NotifikasiController::class, 'total']);
        Route::post('notifikasi/baca-semua',[NotifikasiController::class, 'tandaiSemuaDibaca']);
        Route::post('notifikasi/{id}/baca',[NotifikasiController::class, 'tandaiDibaca']);
        Route::delete('notifikasi/{id}',[NotifikasiController::class, 'hapus']);

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
        Route::get('jadwal/kelola', [JadwalController::class, 'kelola']);
        Route::post('jadwal/kelola/unit', [JadwalController::class, 'simpanUnit']);
        Route::post('jadwal/kelola/pegawai', [JadwalController::class, 'simpanPegawai']);
        Route::get('jadwal/template', [JadwalController::class, 'template']);
        Route::post('jadwal/import', [JadwalController::class, 'impor']);

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
        Route::get('perubahan-jadwal/shift', [PerubahanJadwalController::class, 'daftarShift']);
        Route::post('perubahan-jadwal', [PerubahanJadwalController::class, 'ajukan']);
        Route::delete('perubahan-jadwal/{id}', [PerubahanJadwalController::class, 'batal']);
        Route::get('perubahan-jadwal/total', [PerubahanJadwalController::class, 'menungguTotal']);
        Route::get('perubahan-jadwal/menunggu', [PerubahanJadwalController::class, 'menungguDaftar']);
        Route::post('perubahan-jadwal/proses', [PerubahanJadwalController::class, 'proses']);
        Route::get('perubahan-jadwal/riwayat-persetujuan', [PerubahanJadwalController::class, 'riwayatPersetujuan']);

        // Lembur
        Route::get('lembur', [LemburController::class, 'daftar']);
        Route::post('lembur', [LemburController::class, 'ajukan']);
        Route::delete('lembur/{id}', [LemburController::class, 'batal']);
        Route::get('lembur/total', [LemburController::class, 'menungguTotal']);
        Route::get('lembur/menunggu', [LemburController::class, 'menungguDaftar']);
        Route::post('lembur/proses', [LemburController::class, 'proses']);
        Route::get('lembur/riwayat-persetujuan', [LemburController::class, 'riwayatPersetujuan']);
        Route::post('absen-lembur', [LemburController::class, 'absenMasuk']);
        Route::put('absen-lembur/pulang', [LemburController::class, 'absenPulang']);
        Route::get('absen-lembur/status', [LemburController::class, 'statusLembur']);

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
        // Verifikasi logbook oleh atasan langsung (relasi atasan_langsung)
        Route::get('logbook/bawahan',[LogbookController::class,"bawahan"]);
        Route::get('logbook/bawahan/{user_id}',[LogbookController::class,"bawahanDetail"]);
        Route::post('logbook/verifikasi',[LogbookController::class,"verifikasi"]);
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
