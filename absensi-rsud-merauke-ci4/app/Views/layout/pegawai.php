<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= csrf_hash() ?>">
<title><?= esc($judul ?? 'Dasbor Pegawai') ?> — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="<?= base_url('assets/img/logo.svg') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="latar-pola">
<header class="topbar">
  <div class="topbar-isi">
    <a class="topbar-judul" href="<?= site_url('dashboard') ?>">
      <img class="logo" src="<?= base_url('assets/img/logo.svg') ?>" alt="">
      <span class="topbar-merek">
        <span class="baris-kecil">Sistem Absensi</span>
        <strong>RSUD Merauke</strong>
      </span>
    </a>
    <nav class="aksi-baris">
      <?php if (session('posisi') && session('posisi') !== 'Staf'): ?>
        <a class="btn btn-garis btn-kecil" href="<?= site_url('persetujuan') ?>"><?= ikon('centang', 15) ?> Persetujuan</a>
      <?php endif; ?>
      <a class="btn btn-garis btn-kecil" href="<?= site_url('izin') ?>"><?= ikon('surat', 15) ?> Izin / Cuti</a>
      <a class="btn-keluar" href="<?= site_url('logout') ?>"><?= ikon('keluar', 16) ?> Keluar</a>
    </nav>
  </div>
</header>
<main class="wadah">
  <?php if (session('flash_sukses')): ?>
    <div class="flash flash-sukses"><?= esc(session('flash_sukses')) ?></div>
  <?php endif; ?>
  <?php if (session('flash_gagal')): ?>
    <div class="flash flash-gagal"><?= esc(session('flash_gagal')) ?></div>
  <?php endif; ?>
  <?= $this->renderSection('isi') ?>
</main>
<footer class="kaki">Sistem Absensi Pegawai RSUD Merauke · <?= date('Y') ?></footer>
<?= $this->renderSection('skrip') ?>
</body>
</html>
