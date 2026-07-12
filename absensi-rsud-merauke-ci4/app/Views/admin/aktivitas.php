<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('log') ?> Log Aktivitas</h2>
    <span class="badge badge-biru"><?= number_format($total, 0, ',', '.') ?> catatan</span>
  </div>

  <form method="get" action="<?= site_url('admin/aktivitas') ?>" class="bilah-alat">
    <input type="text" name="q" placeholder="Cari aksi / detail / nama…" value="<?= esc($q) ?>">
    <button type="submit" class="btn btn-navy btn-kecil">Cari</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Detail</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($daftar as $l): ?>
        <tr>
          <td class="log-waktu angka"><?= tgl_id($l['waktu'], false) ?> · <?= jam_id($l['waktu']) ?></td>
          <td><?= esc($l['nama_lengkap'] ?? 'Sistem') ?></td>
          <td><strong><?= esc($l['aksi']) ?></strong></td>
          <td class="teks-kecil"><?= esc($l['detail'] ?? '—') ?></td>
          <td class="teks-kecil angka"><?= esc($l['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $daftar): ?>
        <tr><td colspan="5" class="tengah teks-redup">Belum ada catatan aktivitas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalHal > 1): ?>
  <div class="paginasi">
    <?php
      $dasar = 'admin/aktivitas?' . http_build_query(array_filter(['q' => $q ?: null]));
      $pisah = str_contains($dasar, '?') && ! str_ends_with($dasar, '?') ? '&' : '';
      for ($h = max(1, $halaman - 3); $h <= min($totalHal, $halaman + 3); $h++):
        if ($h === $halaman): ?>
          <span class="aktif"><?= $h ?></span>
        <?php else: ?>
          <a href="<?= site_url($dasar . $pisah . 'hal=' . $h) ?>"><?= $h ?></a>
        <?php endif;
      endfor; ?>
    <span class="info">hal. <?= $halaman ?> / <?= $totalHal ?></span>
  </div>
  <?php endif; ?>
</section>

<?= $this->endSection() ?>
