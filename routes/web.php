<?php

use App\Http\Controllers\AbsenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\StrukturController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'beranda']);
Route::get('install', [InstallController::class, 'index'])->name('install');
Route::post('install', [InstallController::class, 'buatAdmin']);
Route::get('login', [AuthController::class, 'login'])->name('login');
Route::post('login', [AuthController::class, 'prosesLogin']);
Route::get('register', [AuthController::class, 'register'])->name('register');
Route::post('register', [AuthController::class, 'prosesRegister']);
Route::get('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('verifikasi/{kode?}', [VerifikasiController::class, 'index'])->name('verifikasi');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('absen', [AbsenController::class, 'proses'])->name('absen.proses');
    Route::post('pilih-shift', [AbsenController::class, 'pilihShift'])->name('absen.shift');
    Route::get('struktur', [StrukturController::class, 'index'])->name('struktur');
    Route::get('izin', [IzinController::class, 'index'])->name('izin');
    Route::post('izin', [IzinController::class, 'ajukan'])->name('izin.ajukan');
    Route::post('izin/batal/{id}', [IzinController::class, 'batal'])->name('izin.batal');
    Route::get('izin/dokumen/{id}', [IzinController::class, 'dokumen'])->name('izin.dokumen');
    Route::post('izin/tanda-tangan/{id}', [IzinController::class, 'tandaTangan'])->name('izin.ttd');
    Route::get('persetujuan', [PersetujuanController::class, 'index'])->name('persetujuan');
    Route::post('persetujuan/proses', [PersetujuanController::class, 'proses'])->name('persetujuan.proses');
    Route::get('foto/{id}/{tipe}', [FotoController::class, 'tampil'])->name('foto');
    Route::get('lampiran-izin/{id}', [FotoController::class, 'lampiranIzin'])->name('lampiran');
});
