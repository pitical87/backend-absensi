<table border="1">
  <tr><th colspan="15" style="background:#0B3B66;color:#fff;font-size:14px">
    REKAPITULASI KEHADIRAN PEGAWAI RSUD MERAUKE</th></tr>
  <tr><th colspan="15">Periode: <?= BULAN_ID[$bulan] . ' ' . $tahun ?></th></tr>
  <tr><td colspan="15"></td></tr>
  <tr style="background:#E3F0FB;font-weight:bold">
    <th>No</th><th>Nama Pegawai</th><th>Unit / Sub Unit</th><th>Jabatan</th><th>Profesi</th>
    <th>Hari Efektif</th><th>Hadir</th><th>Tepat Waktu</th><th>Terlambat</th>
    <th>Alpa</th><th>Izin</th><th>Sakit</th><th>Cuti</th><th>Dinas Luar</th><th>Kehadiran (%)</th>
  </tr>
  <?php $no = 1; foreach ($pegawai as $p): $r = $rekapPer[(int) $p['id']]; ?>
  <tr>
    <td><?= $no++ ?></td>
    <td><?= esc($p['nama_lengkap']) ?></td>
    <td><?= esc($p['unit_nama'] ?? '-') ?><?= $p['sub_nama'] ? ' - ' . esc($p['sub_nama']) : '' ?></td>
    <td><?= esc($p['jabatan_nama'] ?? ($p['jabatan_kategori'] ?? '-')) ?></td>
    <td><?= esc($p['profesi_nama'] ?? '-') ?></td>
    <td><?= $r['hari_efektif'] ?></td>
    <td><?= $r['hadir'] ?></td>
    <td><?= $r['tepat'] ?></td>
    <td><?= $r['terlambat'] ?></td>
    <td><?= $r['alpa'] ?></td>
    <td><?= $r['izin'] ?></td>
    <td><?= $r['sakit'] ?></td>
    <td><?= $r['cuti'] ?></td>
    <td><?= $r['dinas_luar'] ?></td>
    <td><?= $r['persen'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
