<?php
$menuAktif = $menuAktif ?? '';
$badgeIzin = (int) \Config\Database::connect()->table('pengajuan_izin')
    ->where('status', 'Menunggu')->countAllResults();
$menus = [
    'dashboard'  => ['admin',            'beranda',  'Dashboard'],
    'pegawai'    => ['admin/pegawai',    'pegawai',  'Data Pegawai'],
    'unit'       => ['admin/unit',       'gedung',   'Data Unit Kerja'],
    'struktur'   => ['admin/struktur',   'struktur', 'Struktur Organisasi'],
    'shift'      => ['admin/shift',      'jam',      'Pengaturan Shift'],
    'kehadiran'  => ['admin/kehadiran',  'peta',     'Data Kehadiran'],
    'izin'       => ['admin/izin',       'surat',    'Persetujuan Izin'],
    'libur'      => ['admin/libur',      'kalender', 'Hari Libur'],
    'rekap'      => ['admin/rekap',      'grafik',   'Rekap Bulanan'],
    'aktivitas'  => ['admin/aktivitas',  'log',      'Log Aktivitas'],
    'pengaturan' => ['admin/pengaturan', 'atur',     'Pengaturan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= csrf_hash() ?>">
<title><?= esc($judulHalaman ?? 'Admin') ?> — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="<?= base_url('assets/img/logo.svg') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>
<div class="admin-kerangka">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-merek">
      <img class="logo" src="<?= base_url('assets/img/logo.svg') ?>" alt="Logo">
      <div><strong>RSUD Merauke</strong><span>Sistem Absensi — Admin</span></div>
    </div>
    <nav>
      <?php foreach ($menus as $kunci => [$jalur, $namaIkon, $label]): ?>
        <a class="nav-item <?= $menuAktif === $kunci ? 'aktif' : '' ?>" href="<?= site_url($jalur) ?>">
          <?= ikon($namaIkon, 16) ?><span><?= esc($label) ?></span>
          <?php if ($kunci === 'izin' && $badgeIzin > 0): ?>
            <span class="badge badge-amber" style="margin-left:auto"><?= $badgeIzin ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-kaki">
      <a class="nav-item" href="<?= site_url('dashboard') ?>"><?= ikon('pegawai', 18) ?><span>Tampilan Pegawai</span></a>
      <a class="nav-item" href="<?= site_url('logout') ?>"><?= ikon('keluar', 18) ?><span>Keluar</span></a>
    </div>
  </aside>

  <div class="tirai" id="tirai"></div>

  <div class="admin-utama">
    <header class="admin-atas">
      <button type="button" class="tombol-menu" id="tombol-menu" aria-label="Buka menu"><?= ikon('menu', 20) ?></button>
      <h1><?= esc($judulHalaman ?? '') ?></h1>
      <div class="pengguna">
        <span class="teks-kecil teks-redup"><?= tgl_id(date('Y-m-d')) ?></span>
        <strong><?= esc(session('nama') ?? 'Admin') ?></strong>
      </div>
    </header>
    <main class="admin-isi">
      <?php if (session('flash_sukses')): ?>
        <div class="flash flash-sukses"><?= esc(session('flash_sukses')) ?></div>
      <?php endif; ?>
      <?php if (session('flash_gagal')): ?>
        <div class="flash flash-gagal"><?= esc(session('flash_gagal')) ?></div>
      <?php endif; ?>
      <?= $this->renderSection('isi') ?>
    </main>
  </div>
</div>
<script>
(function () {
  var t = document.getElementById('tombol-menu'), s = document.getElementById('sidebar'),
      r = document.getElementById('tirai');
  function tutup(){ s.classList.remove('terbuka'); r.classList.remove('terbuka'); }
  if (t) t.addEventListener('click', function(){ s.classList.toggle('terbuka'); r.classList.toggle('terbuka'); });
  if (r) r.addEventListener('click', tutup);
})();
</script>
<?= $this->renderSection('skrip') ?>
</body>
</html>
