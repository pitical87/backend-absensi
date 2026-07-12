<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('surat') ?> Pengajuan Izin / Sakit / Cuti / Dinas Luar</h2>
  </div>

  <div class="chips">
    <?php foreach (['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st): ?>
      <a class="chip <?= $status === $st ? 'aktif' : '' ?>"
         href="<?= site_url('admin/izin?status=' . $st) ?>">
        <?= $st ?>
        <?php if ($st !== 'Semua'): ?><span class="jml"><?= (int) ($jumlah[$st] ?? 0) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Keterangan</th>
            <th>Alur / Tahap</th><th>Status</th><th style="min-width:260px">Tindakan / Catatan</th></tr>
      </thead>
      <tbody>
        <?php foreach ($daftar as $r): $berjenjang = in_array($r['jenis'], ['Izin', 'Cuti'], true); ?>
        <tr>
          <td>
            <strong><?= esc($r['nama_lengkap']) ?></strong>
            <br><span class="teks-kecil teks-redup"><?= esc($r['unit_nama'] ?? '—') ?><?=
              $r['sub_nama'] ? ' — ' . esc($r['sub_nama']) : '' ?></span>
            <?php if ($berjenjang): ?>
              <br><span class="badge badge-ungu teks-kecil"><?= esc($r['posisi_pemohon']) ?></span>
            <?php endif; ?>
          </td>
          <td><strong><?= esc($r['jenis_cuti'] ?: $r['jenis']) ?></strong>
            <?php if ($r['lama_hari']): ?><br><span class="teks-kecil teks-redup"><?= (int) $r['lama_hari'] ?> hr kerja</span><?php endif; ?></td>
          <td class="angka"><?= tgl_id($r['tanggal_mulai'], false) ?><?=
              $r['tanggal_selesai'] !== $r['tanggal_mulai']
                ? '<br>s.d. ' . tgl_id($r['tanggal_selesai'], false) : '' ?></td>
          <td class="teks-kecil">
            <?= esc($r['keterangan']) ?>
            <?php if ($r['alamat_izin']): ?><br>Alamat: <?= esc($r['alamat_izin']) ?><?php endif; ?>
            <?php if ($r['lampiran']): ?>
              <br><a href="<?= site_url('lampiran-izin/' . (int) $r['id']) ?>" target="_blank"
                     rel="noopener">Lihat lampiran</a>
            <?php endif; ?>
          </td>
          <td class="teks-kecil">
            <?php if ($berjenjang && ! empty($tahapPer[$r['id']])): ?>
              <?php foreach ($tahapPer[$r['id']] as $t): ?>
                <?php if ($t['status'] === 'Dilewati' && ! $t['oleh_nama']): ?>
                  <span class="teks-redup"><?= label_tahap_izin((int) $t['tahap']) ?>: dilewati</span><br>
                <?php else: ?>
                  <?= label_tahap_izin((int) $t['tahap']) ?> <?= badge_tahap($t['status']) ?>
                  <?php if ($t['oleh_nama']): ?><span class="teks-redup">(<?= esc($t['oleh_nama']) ?>)</span><?php endif; ?><br>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php elseif (! $berjenjang): ?>
              <span class="teks-redup">Satu tahap (admin)</span>
            <?php endif; ?>
          </td>
          <td><?= badge_izin($r['status']) ?></td>
          <td>
            <?php if (! $berjenjang && $r['status'] === 'Menunggu'): ?>
              <form method="post" action="<?= site_url('admin/izin/proses') ?>" class="bilah-alat" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="catatan" placeholder="Catatan (opsional)…" style="min-width:120px">
                <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil">Setujui</button>
                <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                        onclick="return confirm('Tolak pengajuan ini?');">Tolak</button>
              </form>
            <?php elseif ($berjenjang && $r['status'] === 'Menunggu'): ?>
              <form method="post" action="<?= site_url('admin/izin/ambil-alih') ?>" class="bilah-alat" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="text" name="catatan" placeholder="Catatan (opsional)…" style="min-width:120px">
                <button type="submit" name="putusan" value="setuju" class="btn btn-garis btn-kecil">Ambil Alih: Setujui</button>
                <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                        onclick="return confirm('Tolak pengajuan ini pada tahap saat ini?');">Ambil Alih: Tolak</button>
              </form>
              <div class="petunjuk">Sedang menunggu <?= label_tahap_izin((int) $r['tahap_aktif']) ?>.
                Gunakan tombol ini hanya bila pejabat terkait belum terdaftar/berhalangan.</div>
            <?php else: ?>
              <span class="teks-kecil">
                <?= $r['catatan_admin'] ? esc($r['catatan_admin']) : ($r['nomor_surat'] ? 'No. ' . esc($r['nomor_surat']) : '—') ?>
                <?php if ($r['admin_nama']): ?>
                  <br><span class="teks-redup">oleh <?= esc($r['admin_nama']) ?> ·
                    <?= tgl_id($r['processed_at'], false) ?></span>
                <?php endif; ?>
              </span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $daftar): ?>
        <tr><td colspan="7" class="tengah teks-redup">Tidak ada pengajuan berstatus <?= esc($status) ?>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="petunjuk">Izin/Sakit/Cuti yang <strong>disetujui</strong> otomatis tidak dihitung sebagai
    Alpa dan tidak menurunkan persentase kehadiran; <strong>Dinas Luar</strong> dihitung sebagai hadir.
    <strong>Izin</strong> dan <strong>Cuti</strong> berjalan melalui alur berjenjang (Koordinator → Kepala
    Seksi/Sub Bagian → Kepala Bidang/Bagian → HRD) yang diputus pejabat terkait di menu Persetujuan mereka;
    kolom "Ambil Alih" di sini hanya untuk keadaan darurat.</p>
</section>

<?= $this->endSection() ?>
