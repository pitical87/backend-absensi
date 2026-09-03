<?php

use App\Http\Controllers\Admin\AktivitasController;
use App\Http\Controllers\Admin\AtasanLangsungController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DokumentasiController;
use App\Http\Controllers\Admin\FingerController;
use App\Http\Controllers\Admin\IzinController;
use App\Http\Controllers\Admin\LogbookController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\KehadiranController;
use App\Http\Controllers\Admin\LiburController;
use App\Http\Controllers\Admin\LemburController;
use App\Http\Controllers\Admin\MappingSIMRSController;
use App\Http\Controllers\Admin\PegawaiController;
use App\Http\Controllers\Admin\PegawaiTeladanController;
use App\Http\Controllers\Admin\PengajuanJadwalController;
use App\Http\Controllers\Admin\RekapKeterlambatanController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\RekapLogbookController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SimrsController;
use App\Http\Controllers\Admin\StrukturController;
use App\Http\Controllers\Admin\UnitController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard.index');

    Route::get('mapping_simrs/cek', [MappingSIMRSController::class, 'cek'])->name('admin.mapping_simrs.cek');
    Route::get('mapping_simrs/cari', [MappingSIMRSController::class, 'cari'])->name('admin.mapping_simrs.cari');
    Route::post('mapping_simrs/simpan', [MappingSIMRSController::class, 'simpan'])->name('admin.mapping_simrs.simpan');

    Route::get('simrs', [SimrsController::class, 'index'])->name('admin.simrs');
    Route::get('simrs/tindakan/ambil', [SimrsController::class, 'ambilTindakan'])->name('admin.simrs.tindakan.ambil');
    Route::get('simrs/lab/ambil', [SimrsController::class, 'ambilLab'])->name('admin.simrs.lab.ambil');

    Route::get('logbook', [LogbookController::class, 'index'])->name('admin.logbook.index');
    Route::post('logbook', [LogbookController::class, 'simpan'])->name('admin.logbook.simpan');
    Route::get('logbook/data', [LogbookController::class, 'data'])->name('admin.logbook.data');
    Route::post('logbook/hapus', [LogbookController::class, 'hapus'])->name('admin.logbook.hapus');
    Route::post('logbook/ubah', [LogbookController::class, 'ubah'])->name('admin.logbook.ubah');
    Route::post('logbook/template', [LogbookController::class, 'simpanTemplate'])->name('admin.logbook.template.simpan');
    Route::post('logbook/template/hapus', [LogbookController::class, 'hapusTemplate'])->name('admin.logbook.template.hapus');

    Route::get('pegawai', [PegawaiController::class, 'index'])->name('admin.pegawai');
    Route::get('pegawai/data', [PegawaiController::class, 'data'])->name('admin.pegawai.data');
    Route::get('pegawai/cetak', [PegawaiController::class, 'cetak'])->name('admin.pegawai.cetak');
    Route::get('pegawai/pdf', [PegawaiController::class, 'pdf'])->name('admin.pegawai.pdf');
    Route::get('pegawai/excel', [PegawaiController::class, 'excel'])->name('admin.pegawai.excel');
    Route::get('pegawai/form/{id?}', [PegawaiController::class, 'form'])->name('admin.pegawai.form');
    Route::post('pegawai/simpan', [PegawaiController::class, 'simpan'])->name('admin.pegawai.simpan');
    Route::post('pegawai/import', [PegawaiController::class, 'impor'])->name('admin.pegawai.import');
    Route::get('pegawai/template', [PegawaiController::class, 'template'])->name('admin.pegawai.template');
    Route::post('pegawai/status', [PegawaiController::class, 'ubahStatus'])->name('admin.pegawai.status');
    Route::post('pegawai/hapus', [PegawaiController::class, 'hapus'])->name('admin.pegawai.hapus');

    Route::get('unit', [UnitController::class, 'index'])->name('admin.unit.index');
    Route::post('unit/aksi', [UnitController::class, 'aksi'])->name('admin.unit.aksi');

    Route::get('atasan_langsung', [AtasanLangsungController::class, 'index'])->name('admin.atasan_langsung.index');
    Route::post('atasan_langsung/aksi', [AtasanLangsungController::class, 'aksi'])->name('admin.atasan_langsung.aksi');

    Route::get('struktur', [StrukturController::class, 'index'])->name('admin.struktur.index');
    Route::post('struktur/aksi', [StrukturController::class, 'aksi'])->name('admin.struktur.aksi');

    Route::get('shift', [ShiftController::class, 'index'])->name('admin.shift.index');
    Route::post('shift/aksi', [ShiftController::class, 'aksi'])->name('admin.shift.aksi');

    Route::get('jadwal', [JadwalController::class, 'index'])->name('admin.jadwal.index');
    Route::post('jadwal/aksi', [JadwalController::class, 'aksi'])->name('admin.jadwal.aksi');
    Route::post('jadwal/aksi-pegawai', [JadwalController::class, 'aksiPegawai'])->name('admin.jadwal.pegawai');

    Route::get('kehadiran', [KehadiranController::class, 'index'])->name('admin.kehadiran.index');
    Route::post('kehadiran/simpan', [KehadiranController::class, 'simpan'])->name('admin.kehadiran.simpan');
    Route::post('kehadiran/hapus', [KehadiranController::class, 'hapus'])->name('admin.kehadiran.hapus');

    Route::get('finger', [FingerController::class, 'index'])->name('admin.finger');
    Route::post('finger/mapping', [FingerController::class, 'simpanMapping'])->name('admin.finger.mapping.simpan');
    Route::post('finger/mapping/hapus', [FingerController::class, 'hapusMapping'])->name('admin.finger.mapping.hapus');
    Route::post('finger/import', [FingerController::class, 'imporCsv'])->name('admin.finger.import');
    Route::post('finger/import-url', [FingerController::class, 'ambilDariMesin'])->name('admin.finger.import.url');
    Route::post('finger/setting', [FingerController::class, 'simpanSetting'])->name('admin.finger.setting');

    Route::get('izin', [IzinController::class, 'index'])->name('admin.izin.index');
    Route::post('izin/proses', [IzinController::class, 'proses'])->name('admin.izin.proses');
    Route::post('izin/ambil-alih', [IzinController::class, 'ambilAlih'])->name('admin.izin.ambilalih');

    Route::get('jadwal_pengajuan', [PengajuanJadwalController::class, 'index'])->name('admin.jadwal.pengajuan');
    Route::post('jadwal_pengajuan/proses', [PengajuanJadwalController::class, 'proses'])->name('admin.jadwal_pengajuan.proses');

    Route::get('lembur', [LemburController::class, 'index'])->name('admin.lembur.index');
    Route::post('lembur/proses', [LemburController::class, 'proses'])->name('admin.lembur.proses');

    Route::get('libur', [LiburController::class, 'index'])->name('admin.libur.index');
    Route::post('libur/aksi', [LiburController::class, 'aksi'])->name('admin.libur.aksi');

    Route::get('rekap', [RekapController::class, 'index'])->name('admin.rekap.index');
    Route::get('rekap_keterlambatan', [RekapKeterlambatanController::class, 'index'])->name('admin.rekap_keterlambatan.index');
    Route::get('pegawai_teladan', [PegawaiTeladanController::class, 'index'])->name('admin.pegawai_teladan');
    Route::get('rekap_logbook', [RekapLogbookController::class, 'index'])->name('admin.rekap_logbook.index');
    Route::get('rekap_logbook/cetak', [RekapLogbookController::class, 'cetak'])->name('admin.rekap_logbook.cetak');
    Route::get('rekap_logbook/detail', [RekapLogbookController::class, 'detail'])->name('admin.rekap_logbook.detail');
    Route::post('rekap/generate', [RekapController::class, 'generate'])->name('admin.rekap.generate');
    Route::get('rekap/cetak', [RekapController::class, 'cetak'])->name('admin.rekap.cetak');
    Route::get('rekap/excel', [RekapController::class, 'excel'])->name('admin.rekap.excel');

    Route::get('pengaturan', [PengaturanController::class, 'index'])->name('admin.pengaturan.index');
    Route::post('pengaturan', [PengaturanController::class, 'simpan'])->name('admin.pengaturan.simpan');
    Route::post('pengaturan/api-key', [PengaturanController::class, 'gantiApiKey'])->name('admin.pengaturan.apikey');
    Route::get('pengaturan/backup', [PengaturanController::class, 'backup'])->name('admin.pengaturan.backup');
    Route::post('ubah-password', [PengaturanController::class, 'ubahPasswordSaya'])->name('admin.ubah-password');

    Route::get('aktivitas', [AktivitasController::class, 'index'])->name('admin.aktivitas.index');

    Route::get('documentation', [DokumentasiController::class, 'index'])->name('admin.documentation.index');
});
