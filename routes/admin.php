<?php

use App\Http\Controllers\Admin\AktivitasController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IzinController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\KehadiranController;
use App\Http\Controllers\Admin\LiburController;
use App\Http\Controllers\Admin\PegawaiController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\StrukturController;
use App\Http\Controllers\Admin\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('pegawai', [PegawaiController::class, 'index'])->name('admin.pegawai');
    Route::get('pegawai/form/{id?}', [PegawaiController::class, 'form'])->name('admin.pegawai.form');
    Route::post('pegawai/simpan', [PegawaiController::class, 'simpan'])->name('admin.pegawai.simpan');
    Route::post('pegawai/import', [PegawaiController::class, 'impor'])->name('admin.pegawai.import');
    Route::get('pegawai/template', [PegawaiController::class, 'template'])->name('admin.pegawai.template');
    Route::post('pegawai/status', [PegawaiController::class, 'ubahStatus'])->name('admin.pegawai.status');
    Route::post('pegawai/hapus', [PegawaiController::class, 'hapus'])->name('admin.pegawai.hapus');

    Route::get('unit', [UnitController::class, 'index'])->name('admin.unit');
    Route::post('unit/aksi', [UnitController::class, 'aksi'])->name('admin.unit.aksi');

    Route::get('struktur', [StrukturController::class, 'index'])->name('admin.struktur');
    Route::post('struktur/aksi', [StrukturController::class, 'aksi'])->name('admin.struktur.aksi');

    Route::get('shift', [ShiftController::class, 'index'])->name('admin.shift');
    Route::post('shift/aksi', [ShiftController::class, 'aksi'])->name('admin.shift.aksi');

    Route::get('jadwal', [JadwalController::class, 'index'])->name('admin.jadwal');
    Route::post('jadwal/aksi', [JadwalController::class, 'aksi'])->name('admin.jadwal.aksi');

    Route::get('kehadiran', [KehadiranController::class, 'index'])->name('admin.kehadiran');

    Route::get('izin', [IzinController::class, 'index'])->name('admin.izin');
    Route::post('izin/proses', [IzinController::class, 'proses'])->name('admin.izin.proses');
    Route::post('izin/ambil-alih', [IzinController::class, 'ambilAlih'])->name('admin.izin.ambilalih');

    Route::get('libur', [LiburController::class, 'index'])->name('admin.libur');
    Route::post('libur/aksi', [LiburController::class, 'aksi'])->name('admin.libur.aksi');

    Route::get('rekap', [RekapController::class, 'index'])->name('admin.rekap');
    Route::post('rekap/generate', [RekapController::class, 'generate'])->name('admin.rekap.generate');
    Route::get('rekap/cetak', [RekapController::class, 'cetak'])->name('admin.rekap.cetak');
    Route::get('rekap/excel', [RekapController::class, 'excel'])->name('admin.rekap.excel');

    Route::get('pengaturan', [PengaturanController::class, 'index'])->name('admin.pengaturan');
    Route::post('pengaturan', [PengaturanController::class, 'simpan'])->name('admin.pengaturan.simpan');
    Route::post('pengaturan/api-key', [PengaturanController::class, 'gantiApiKey'])->name('admin.pengaturan.apikey');
    Route::get('pengaturan/backup', [PengaturanController::class, 'backup'])->name('admin.pengaturan.backup');

    Route::get('aktivitas', [AktivitasController::class, 'index'])->name('admin.aktivitas');
});
