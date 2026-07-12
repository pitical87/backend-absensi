<?php $judul = 'Persetujuan'; ?>
<?= $this->extend('layout/pegawai') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('centang') ?> Menunggu Persetujuan Saya</h2>
    <span class="badge badge-amber"><?= count($tugasSaya) ?> pengajuan</span>
  </div>
  <p class="petunjuk">Anda melihat halaman ini karena posisi Anda (<strong><?= esc($u['posisi']) ?></strong>)
    berperan dalam alur persetujuan Izin/Cuti pegawai.</p>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Lama</th>
            <th>Alamat</th><th>Alasan</th><th style="min-width:260px">Tindakan</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tugasSaya as $r): ?>
        <tr>
          <td>
            <strong><?= esc($r['nama_lengkap']) ?></strong>
            <?php if ($r['nip']): ?><br><span class="teks-kecil teks-redup">NIP <?= esc($r['nip']) ?></span><?php endif; ?>
            <br><span class="teks-kecil teks-redup"><?= esc($r['unit_nama'] ?? '—') ?><?=
                $r['sub_nama'] ? ' — ' . esc($r['sub_nama']) : '' ?></span>
          </td>
          <td><strong><?= esc($r['jenis_cuti'] ?: $r['jenis']) ?></strong></td>
          <td class="angka"><?= tgl_id($r['tanggal_mulai'], false) ?><?=
              $r['tanggal_selesai'] !== $r['tanggal_mulai']
                ? '<br>s.d. ' . tgl_id($r['tanggal_selesai'], false) : '' ?></td>
          <td class="angka"><?= $r['lama_hari'] ?> hr kerja</td>
          <td class="teks-kecil"><?= esc($r['alamat_izin'] ?? '—') ?></td>
          <td class="teks-kecil">
            <?= esc($r['keterangan']) ?>
            <?php if ($r['lampiran']): ?>
              <br><a href="<?= site_url('lampiran-izin/' . (int) $r['id']) ?>" target="_blank"
                     rel="noopener">Lihat lampiran</a>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= site_url('persetujuan/proses') ?>" class="bilah-alat" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="text" name="catatan" placeholder="Catatan (opsional)…" style="min-width:120px">
              <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil">Setujui</button>
              <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                      onclick="return confirm('Tolak pengajuan ini? Seluruh tahap berikutnya akan dibatalkan.');">Tolak</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $tugasSaya): ?>
        <tr><td colspan="7" class="tengah teks-redup">Tidak ada pengajuan yang menunggu keputusan Anda saat ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2><?= ikon('log') ?> Riwayat Keputusan Saya</h2></div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Putusan</th><th>Catatan</th></tr></thead>
      <tbody>
        <?php foreach ($riwayatSaya as $r): ?>
        <tr>
          <td class="angka teks-kecil"><?= tgl_id($r['waktu'], false) ?> · <?= jam_id($r['waktu']) ?></td>
          <td><?= esc($r['nama_lengkap']) ?></td>
          <td><?= esc($r['jenis_cuti'] ?: $r['jenis']) ?></td>
          <td class="angka"><?= tgl_id($r['tanggal_mulai'], false) ?></td>
          <td><?= badge_tahap($r['status']) ?></td>
          <td class="teks-kecil"><?= esc($r['catatan'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $riwayatSaya): ?>
        <tr><td colspan="6" class="tengah teks-redup">Belum ada riwayat keputusan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?= $this->endSection() ?>
