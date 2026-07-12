<?php
$judul      = 'Pemasangan — Absensi RSUD Merauke';
$judulKartu = 'Pemasangan Aplikasi<br>Absensi RSUD Merauke';
$subJudul   = 'Persiapan database & akun admin';
?>
<?= $this->extend('layout/otentikasi') ?>
<?= $this->section('isi') ?>

<?php if (! empty($galat)): ?>
  <div class="flash flash-gagal"><?= esc($galat) ?></div>
<?php endif; ?>

<?php if ($tahap === 'admin'): ?>
  <div class="flash flash-sukses">
    Database beserta seluruh tabel dan data master berhasil disiapkan.
    Langkah terakhir: buat akun <strong>Admin</strong> pertama.
  </div>
  <form method="post" action="<?= site_url('install') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grup">
      <label class="wajib">Nama Lengkap Admin</label>
      <input type="text" name="nama" required autofocus placeholder="cth. Administrator Kepegawaian">
    </div>
    <div class="form-grup">
      <label class="wajib">Email (untuk masuk)</label>
      <input type="email" name="email" required placeholder="admin@rsudmerauke.go.id">
    </div>
    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Password</label>
        <input type="password" name="password" required minlength="6">
      </div>
      <div class="form-grup">
        <label class="wajib">Konfirmasi Password</label>
        <input type="password" name="password2" required minlength="6">
      </div>
    </div>
    <button type="submit" class="btn btn-primer btn-blok"><?= ikon('centang', 17) ?> Selesaikan Pemasangan</button>
  </form>

<?php elseif ($tahap === 'selesai'): ?>
  <div class="flash flash-sukses">Aplikasi <strong>sudah terpasang</strong>. Database dan akun admin tersedia.</div>
  <p class="teks-kecil teks-redup">Menjalankan halaman ini kembali tidak menghapus data apa pun —
    skema hanya dilengkapi bila ada tabel yang hilang.</p>
  <a href="<?= site_url('login') ?>" class="btn btn-primer btn-blok"><?= ikon('masuk', 17) ?> Ke Halaman Masuk</a>
<?php endif; ?>

<?= $this->endSection() ?>
