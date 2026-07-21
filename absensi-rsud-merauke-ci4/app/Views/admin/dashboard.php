<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<div class="stat-admin">
  <div class="stat"><span>Total Pegawai Aktif</span><strong><?= $totalPegawai ?></strong></div>
  <div class="stat hijau"><span>Hadir Hari Ini</span><strong><?= $hadir ?></strong></div>
  <div class="stat amber"><span>Terlambat Hari Ini</span><strong><?= $terlambat ?></strong></div>
  <div class="stat"><span>Izin/Sakit/Cuti Hari Ini</span><strong><?= $izinHariIni ?></strong></div>
  <div class="stat merah"><span>Belum Hadir</span><strong><?= $belum ?></strong></div>
</div>

<?php if ($menunggu > 0 || $anomali > 0): ?>
<section class="kartu">
  <div class="aksi-baris">
    <?php if ($menunggu > 0): ?>
      <a class="btn btn-navy btn-kecil" href="<?= site_url('admin/izin') ?>">
        <?= ikon('surat', 15) ?> <?= $menunggu ?> pengajuan izin menunggu persetujuan</a>
    <?php endif; ?>
    <?php if ($anomali > 0): ?>
      <a class="btn btn-garis btn-kecil" href="<?= site_url('admin/kehadiran?anomali=1') ?>">
        <?= ikon('peringatan', 15) ?> <?= $anomali ?> absensi hari ini terindikasi anomali GPS</a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('grafik') ?> Kehadiran 30 Hari Terakhir</h2>
    <span class="teks-redup teks-kecil">jumlah pegawai hadir per hari</span>
  </div>
  <div class="grafik-bungkus">
    <svg viewBox="0 0 660 150" role="img" aria-label="Grafik kehadiran 30 hari terakhir">
      <?php foreach ([0, .5, 1] as $p): $y = 120 - $p * 100; ?>
        <line x1="34" y1="<?= $y ?>" x2="654" y2="<?= $y ?>" stroke="#D7E5F2" stroke-width="1"/>
        <text x="28" y="<?= $y + 3 ?>" font-size="9" fill="#5C7189" text-anchor="end"><?= round($maks * $p) ?></text>
      <?php endforeach; ?>
      <?php foreach ($grafik30 as $i => $g):
          $x = 38 + $i * 20.5;
          $h = $g['jml'] > 0 ? max(3, $g['jml'] / $maks * 100) : 0; ?>
        <?php if ($h > 0): ?>
          <rect x="<?= $x ?>" y="<?= 120 - $h ?>" width="13" height="<?= $h ?>" rx="2.5" fill="#1568B8">
            <title><?= tgl_id($g['tgl'], false) ?>: <?= $g['jml'] ?> pegawai</title>
          </rect>
        <?php else: ?>
          <rect x="<?= $x ?>" y="117" width="13" height="3" rx="1.5" fill="#DCE8F4"/>
        <?php endif; ?>
        <?php if ($i % 5 === 0 || $i === 29): ?>
          <text x="<?= $x + 6.5 ?>" y="134" font-size="8.5" fill="#5C7189" text-anchor="middle"><?= (int) date('j', strtotime($g['tgl'])) ?>/<?= (int) date('n', strtotime($g['tgl'])) ?></text>
        <?php endif; ?>
      <?php endforeach; ?>
    </svg>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('kalender') ?> Absensi Hari Ini</h2>
    <a class="btn btn-garis btn-kecil" href="<?= site_url('admin/kehadiran') ?>">Lihat semua</a>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama Pegawai</th><th>Unit</th><th>Shift</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($terbaru as $r): ?>
        <tr <?= $r['flag_anomali'] ? 'class="anomali-baris"' : '' ?>>
          <td><?= esc($r['nama_lengkap']) ?>
            <?php if ($r['flag_anomali']): ?> <span class="badge badge-amber">⚠</span><?php endif; ?></td>
          <td><?= esc($r['unit_nama'] ?? '—') ?><?= $r['sub_nama'] ? ' — ' . esc($r['sub_nama']) : '' ?></td>
          <td><?= esc(label_shift((object)['kategori' => $r['shift_kategori'], 'jam_masuk' => $r['shift_masuk'], 'jam_pulang' => $r['shift_pulang']])) ?></td>
          <td class="angka"><?= jam_id($r['waktu_masuk']) ?></td>
          <td class="angka"><?= jam_id($r['waktu_pulang']) ?></td>
          <td><?= badge_status(! $r['waktu_pulang'] ? 'Belum Pulang'
                 : ($r['status_masuk'] === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu'),
                 (int) $r['menit_terlambat']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $terbaru): ?>
        <tr><td colspan="6" class="tengah teks-redup">Belum ada absensi hari ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?= $this->endSection() ?>
