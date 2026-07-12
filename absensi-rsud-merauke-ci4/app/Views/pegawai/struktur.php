<?php $judul = 'Struktur Organisasi'; ?>
<?= $this->extend('layout/pegawai') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('struktur') ?> Struktur Organisasi RSUD Merauke</h2>
    <a class="btn btn-garis btn-kecil" href="<?= site_url('dashboard') ?>">&larr; Dasbor</a>
  </div>
  <div class="tabel-bungkus">
    <?= view('partial/pohon', ['cabang' => $pohon, 'kelola' => false, 'akar' => true]) ?>
  </div>
  <p class="petunjuk">Bagan diperbarui otomatis berdasarkan penetapan jabatan pegawai oleh admin.</p>
</section>

<?= $this->endSection() ?>
