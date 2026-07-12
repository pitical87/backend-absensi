<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ---------- Publik ----------
$routes->get('/', 'Auth::beranda');
$routes->get('install', 'Install::index');
$routes->post('install', 'Install::buatAdmin');
$routes->get('login', 'Auth::login');
$routes->get('verifikasi', 'Verifikasi::index');
$routes->get('verifikasi/(:any)', 'Verifikasi::index/$1');
$routes->post('login', 'Auth::prosesLogin');
$routes->get('register', 'Auth::register');
$routes->post('register', 'Auth::prosesRegister');
$routes->get('logout', 'Auth::logout');

// ---------- Pegawai (wajib masuk) ----------
$routes->group('', ['filter' => 'authuser'], static function ($routes) {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->post('absen', 'Absen::proses');
    $routes->post('pilih-shift', 'Absen::pilihShift');
    $routes->get('struktur', 'Struktur::index');
    $routes->get('izin', 'Izin::index');
    $routes->post('izin', 'Izin::ajukan');
    $routes->post('izin/batal/(:num)', 'Izin::batal/$1');
    $routes->get('izin/dokumen/(:num)', 'Izin::dokumen/$1');
    $routes->post('izin/tanda-tangan/(:num)', 'Izin::tandaTangan/$1');

    $routes->get('persetujuan', 'Persetujuan::index');
    $routes->post('persetujuan/proses', 'Persetujuan::proses');
    $routes->get('foto/(:num)/(:alpha)', 'Foto::tampil/$1/$2');
    $routes->get('lampiran-izin/(:num)', 'Foto::lampiranIzin/$1');
});

// ---------- Admin ----------
$routes->group('admin', ['filter' => 'authadmin'], static function ($routes) {
    $routes->get('/', 'Admin\Dashboard::index');

    $routes->get('pegawai', 'Admin\Pegawai::index');
    $routes->get('pegawai/form', 'Admin\Pegawai::form');
    $routes->get('pegawai/form/(:num)', 'Admin\Pegawai::form/$1');
    $routes->post('pegawai/simpan', 'Admin\Pegawai::simpan');
    $routes->post('pegawai/status', 'Admin\Pegawai::ubahStatus');
    $routes->post('pegawai/hapus', 'Admin\Pegawai::hapus');

    $routes->get('unit', 'Admin\Unit::index');
    $routes->post('unit/aksi', 'Admin\Unit::aksi');

    $routes->get('struktur', 'Admin\Struktur::index');
    $routes->post('struktur/aksi', 'Admin\Struktur::aksi');

    $routes->get('shift', 'Admin\Shift::index');
    $routes->post('shift/aksi', 'Admin\Shift::aksi');

    $routes->get('kehadiran', 'Admin\Kehadiran::index');

    $routes->get('izin', 'Admin\Izin::index');
    $routes->post('izin/proses', 'Admin\Izin::proses');
    $routes->post('izin/ambil-alih', 'Admin\Izin::ambilAlih');

    $routes->get('libur', 'Admin\Libur::index');
    $routes->post('libur/aksi', 'Admin\Libur::aksi');

    $routes->get('rekap', 'Admin\Rekap::index');
    $routes->post('rekap/generate', 'Admin\Rekap::generate');
    $routes->get('rekap/cetak', 'Admin\Rekap::cetak');
    $routes->get('rekap/excel', 'Admin\Rekap::excel');

    $routes->get('pengaturan', 'Admin\Pengaturan::index');
    $routes->post('pengaturan', 'Admin\Pengaturan::simpan');
    $routes->post('pengaturan/api-key', 'Admin\Pengaturan::gantiApiKey');
    $routes->get('pengaturan/backup', 'Admin\Pengaturan::backup');

    $routes->get('aktivitas', 'Admin\Aktivitas::index');
});

// ---------- API untuk integrasi SIMRS (kunci X-API-KEY) ----------
$routes->group('api/v1', ['filter' => 'apikey'], static function ($routes) {
    $routes->get('ping', 'Api\V1::ping');
    $routes->get('pegawai', 'Api\V1::pegawai');
    $routes->get('absensi', 'Api\V1::absensi');
    $routes->get('rekap', 'Api\V1::rekap');
    $routes->get('izin', 'Api\V1::izin');
    $routes->get('pegawai/(:num)','Api\V1::getPegawai/$1');
});
