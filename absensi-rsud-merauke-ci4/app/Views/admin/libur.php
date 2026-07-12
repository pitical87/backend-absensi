<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('kalender') ?> Kalender Hari Libur <?= (int) $tahun ?></h2>
    <form method="get" action="<?= site_url('admin/libur') ?>" class="bilah-alat" style="margin:0">
      <select name="tahun" onchange="this.form.submit()">
        <?php for ($t = (int) date('Y') + 1; $t >= 2024; $t--): ?>
          <option <?= $t === (int) $tahun ? 'selected' : '' ?>><?= $t ?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>

  <form method="post" action="<?= site_url('admin/libur/aksi') ?>" class="bilah-alat">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <input type="date" name="tanggal" required>
    <input type="text" name="keterangan" placeholder="cth. Hari Kemerdekaan RI / Cuti Bersama…" required>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Hari Libur</button>
  </form>
  <p class="petunjuk">Tanggal yang terdaftar di sini tidak dihitung sebagai Alpa bagi pegawai yang
    tidak absen, sehingga rekap bulanan tetap adil. Pegawai yang tetap masuk pada hari libur
    tetap tercatat hadir beserta jam kerjanya.</p>
  <p class="petunjuk">Tanggal 1 Januari, 1 Mei, 1 Juni, 17 Agustus, dan 25 Desember
    <strong>otomatis tercatat setiap tahun</strong> (bertanda <span class="badge badge-teal teks-kecil">Otomatis</span>
    di bawah). Hari libur nasional/cuti bersama lain yang mengikuti penanggalan Hijriah, Imlek,
    Saka, atau Paskah (Idulfitri, Iduladha, Nyepi, Imlek, Waisak, dll.) baru diumumkan pemerintah
    lewat SKB 3 Menteri sekitar 3–4 bulan sebelum tahun berjalan, sehingga perlu ditambahkan manual
    begitu SKB terbit — tanggal tahun <?= (int) date('Y') ?> sudah dimasukkan sejak pemasangan.</p>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Keterangan</th><th style="width:100px">Aksi</th></tr></thead>
      <tbody>
        <?php $tetap = hari_libur_tetap((int) $tahun); ?>
        <?php foreach ($daftar as $h): ?>
        <tr>
          <td class="angka"><?= tgl_id($h['tanggal']) ?></td>
          <td><?= esc($h['keterangan']) ?>
            <?php if (isset($tetap[$h['tanggal']])): ?>
              <span class="badge badge-teal teks-kecil">Otomatis</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= site_url('admin/libur/aksi') ?>"
                  onsubmit="return confirm('Hapus hari libur ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
              <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $daftar): ?>
        <tr><td colspan="3" class="tengah teks-redup">Belum ada hari libur terdaftar pada tahun ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2><?= ikon('atur') ?> Hari Minggu</h2></div>
  <form method="post" action="<?= site_url('admin/libur/aksi') ?>" class="bilah-alat">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="minggu">
    <label class="teks-kecil" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" name="minggu_libur" value="1" style="width:auto" <?= $mingguLibur ? 'checked' : '' ?>>
      Perlakukan setiap hari Minggu sebagai hari libur
    </label>
    <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
  </form>
  <p class="petunjuk">Bawaan: <strong>nonaktif</strong>, karena rumah sakit beroperasi 7 hari dengan
    sistem shift. Aktifkan hanya bila mayoritas pegawai memang libur setiap Minggu.</p>
</section>

<?= $this->endSection() ?>
