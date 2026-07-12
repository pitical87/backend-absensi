<?= $this->extend('layout/otentikasi') ?>
<?= $this->section('isi') ?>

<?php if (! empty($galat)): ?>
  <div class="flash flash-gagal"><?= esc($galat) ?></div>
<?php endif; ?>

<form method="post" action="<?= site_url('login') ?>">
  <?= csrf_field() ?>
  <div class="form-grup">
    <label class="wajib" for="email">Email</label>
    <input type="email" id="email" name="email" required autofocus placeholder="nama@rsudmerauke.go.id">
  </div>
  <div class="form-grup">
    <label class="wajib" for="password">Password</label>
    <input type="password" id="password" name="password" required>
  </div>
  <button type="submit" class="btn btn-primer btn-blok"><?= ikon('masuk', 17) ?> Masuk</button>
</form>

<div class="pembatas"><span>belum punya akun?</span></div>
<a href="<?= site_url('register') ?>" class="btn btn-garis btn-blok">Daftar sebagai Pegawai</a>

<?= $this->endSection() ?>
