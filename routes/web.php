<?php

use App\Http\Controllers\AbsenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FotoController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\StrukturController;
use App\Http\Controllers\VerifikasiController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'beranda']);
Route::get('install', [InstallController::class, 'index'])->name('install');
Route::post('install', [InstallController::class, 'buatAdmin']);
Route::get('login', [AuthController::class, 'login'])->name('login');
Route::post('login', [AuthController::class, 'prosesLogin']);
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
    Route::post('ubah-password', [ProfilController::class, 'ubahPassword'])->name('pegawai.ubah-password');
    Route::get('update-data', [ProfilController::class, 'form'])->name('pegawai.update-data');
    Route::post('update-data', [ProfilController::class, 'updateData'])->name('pegawai.update-data.simpan');
    Route::get('update-data/cek-simrs', [ProfilController::class, 'cekSimrs'])->name('pegawai.update-data.cek-simrs');
    Route::get('update-data/check-simrs', [ProfilController::class, 'checkSimrsId'])->name('pegawai.update-data.check-simrs');
    Route::post('update-data/mapping', [ProfilController::class, 'simpanMapping'])->name('pegawai.update-data.mapping');
    Route::get('logbook', [LogbookController::class, 'index'])->name('logbook');
    Route::post('logbook', [LogbookController::class, 'simpan'])->name('logbook.simpan');
    Route::get('logbook/data', [LogbookController::class, 'data'])->name('logbook.data');
    Route::get('logbook/cetak-data', [LogbookController::class, 'cetakData'])->name('logbook.cetak-data');
    Route::post('logbook/hapus', [LogbookController::class, 'hapus'])->name('logbook.hapus');
    Route::post('logbook/ubah', [LogbookController::class, 'ubah'])->name('logbook.ubah');
    Route::get('logbook/simrs', [LogbookController::class, 'ambilSimrs'])->name('logbook.simrs');
    Route::post('logbook/template', [LogbookController::class, 'simpanTemplate'])->name('logbook.template.simpan');
    Route::post('logbook/template/hapus', [LogbookController::class, 'hapusTemplate'])->name('logbook.template.hapus');
});
