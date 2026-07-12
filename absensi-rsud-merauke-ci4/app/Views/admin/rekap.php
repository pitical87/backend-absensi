<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('grafik') ?> Rekap Kehadiran — <?= BULAN_ID[$bulan] . ' ' . $tahun ?></h2>
    <span class="badge badge-biru"><?= count($pegawai) ?> pegawai</span>
  </div>

  <form method="get" action="<?= site_url('admin/rekap') ?>" class="bilah-alat">
    <select name="bulan">
      <?php foreach (BULAN_ID as $i => $nb): ?>
        <option value="<?= $i ?>" <?= $i === $bulan ? 'selected' : '' ?>><?= $nb ?></option>
      <?php endforeach; ?>
    </select>
    <select name="tahun">
      <?php for ($t = (int) date('Y') + 1; $t >= 2024; $t--): ?>
        <option <?= $t === $tahun ? 'selected' : '' ?>><?= $t ?></option>
      <?php endfor; ?>
    </select>
    <select name="unit">
      <option value="">Semua Unit Kerja</option>
      <?php foreach ($unitList as $uk): ?>
        <option value="<?= (int) $uk['id'] ?>" <?= $fUnit === (int) $uk['id'] ? 'selected' : '' ?>>
          <?= esc($uk['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="jab" title="Kategori jabatan">
      <option value="">Semua Jabatan</option>
      <?php foreach ($kategoriJab as $k): ?>
        <option <?= $f['jab'] === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="njab" title="Nama jabatan tertentu">
      <option value="">Semua Nama Jabatan</option>
      <?php foreach ($jabPilihan as $kat => $daftar): ?>
        <optgroup label="<?= esc($kat) ?>">
          <?php foreach ($daftar as $j): ?>
            <option value="<?= (int) $j['id'] ?>" <?= $f['njab'] === (int) $j['id'] ? 'selected' : '' ?>>
              <?= esc($j['nama']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
    <select name="org" title="Bidang / Bagian pada struktur organisasi">
      <option value="">Semua Bidang/Bagian</option>
      <?php foreach ($orgList as $o): ?>
        <option value="<?= (int) $o['id'] ?>" <?= $f['org'] === (int) $o['id'] ? 'selected' : '' ?>>
          <?= esc($o['unit_label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="prof">
      <option value="">Semua Profesi</option>
      <?php foreach ($profList as $pr): ?>
        <option value="<?= (int) $pr['id'] ?>" <?= $f['prof'] === (int) $pr['id'] ? 'selected' : '' ?>>
          <?= esc($pr['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>
  <p class="petunjuk">Filter Bidang/Bagian mencakup pejabat struktural di dalamnya (Kabid/Kabag
    beserta Kasi/Kasubag bawahannya). Filter dapat digabung dan ikut terbawa ke Cetak PDF
    maupun Export Excel.</p>

  <div class="aksi-baris" style="margin-bottom:14px">
    <form method="post" action="<?= site_url('admin/rekap/generate') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="bulan" value="<?= $bulan ?>">
      <input type="hidden" name="tahun" value="<?= $tahun ?>">
      <input type="hidden" name="unit" value="<?= $fUnit ?>">
      <input type="hidden" name="jab" value="<?= esc($f['jab']) ?>">
      <input type="hidden" name="njab" value="<?= $f['njab'] ?>">
      <input type="hidden" name="org" value="<?= $f['org'] ?>">
      <input type="hidden" name="prof" value="<?= $f['prof'] ?>">
      <button type="submit" class="btn btn-primer btn-kecil"><?= ikon('centang', 15) ?> Generate &amp; Simpan Rekap</button>
    </form>
    <a class="btn btn-navy btn-kecil" target="_blank"
       href="<?= site_url('admin/rekap/cetak?' . $qs) ?>"><?= ikon('cetak', 15) ?> Cetak Laporan (PDF)</a>
    <a class="btn btn-garis btn-kecil"
       href="<?= site_url('admin/rekap/excel?' . $qs . '&mode=rekap') ?>"><?= ikon('unduh', 15) ?> Export Excel (Rekap)</a>
    <a class="btn btn-garis btn-kecil"
       href="<?= site_url('admin/rekap/excel?' . $qs . '&mode=detail') ?>"><?= ikon('unduh', 15) ?> Export Excel (Detail Harian)</a>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Pegawai</th><th>Unit</th><th>Hari Efektif</th><th>Hadir</th>
          <th>Tepat</th><th>Telat</th><th>Alpa</th><th>Izin</th><th>Sakit</th>
          <th>Cuti</th><th>Dinas</th><th>Total Jam</th><th>%</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pegawai as $p): $r = $rekapPer[(int) $p['id']]; ?>
        <tr>
          <td><strong><?= esc($p['nama_lengkap']) ?></strong><br>
              <span class="teks-kecil teks-redup"><?= esc($p['jabatan_nama']
                    ?? ($p['jabatan_kategori'] ?? '')) ?><?=
                    $p['profesi_nama'] ? ' · ' . esc($p['profesi_nama']) : '' ?></span></td>
          <td><?= esc($p['unit_nama'] ?? '—') ?><?= $p['sub_nama'] ? ' — ' . esc($p['sub_nama']) : '' ?></td>
          <td class="angka"><?= $r['hari_efektif'] ?></td>
          <td class="angka"><?= $r['hadir'] ?></td>
          <td class="angka"><span class="badge badge-hijau"><?= $r['tepat'] ?></span></td>
          <td class="angka"><span class="badge badge-amber"><?= $r['terlambat'] ?></span></td>
          <td class="angka"><span class="badge badge-merah"><?= $r['alpa'] ?></span></td>
          <td class="angka"><?= $r['izin'] ?></td>
          <td class="angka"><?= $r['sakit'] ?></td>
          <td class="angka"><?= $r['cuti'] ?></td>
          <td class="angka"><?= $r['dinas_luar'] ?></td>
          <td class="angka"><?= menit_ke_teks($r['total_menit']) ?></td>
          <td class="angka"><strong><?= $r['persen'] ?>%</strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $pegawai): ?>
        <tr><td colspan="13" class="tengah teks-redup">Tidak ada pegawai pada filter ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="petunjuk">Angka dihitung langsung dari data absensi, izin yang disetujui, dan kalender
    hari libur. <em>Generate &amp; Simpan Rekap</em> menyimpan salinan ke tabel arsip
    <code>rekap_bulanan</code> — juga tersedia bagi SIMRS melalui API.</p>
</section>

<?= $this->endSection() ?>
