<table border="1">
  <tr><th colspan="10" style="background:#0B3B66;color:#fff;font-size:14px">
    DETAIL ABSENSI HARIAN PEGAWAI RSUD MERAUKE</th></tr>
  <tr><th colspan="10">Periode: <?= BULAN_ID[$bulan] . ' ' . $tahun ?></th></tr>
  <tr><td colspan="10"></td></tr>
  <tr style="background:#E3F0FB;font-weight:bold">
    <th>Tanggal</th><th>Nama Pegawai</th><th>Unit / Sub Unit</th><th>Shift</th>
    <th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th>
    <th>Menit Terlambat</th><th>Total Menit Kerja</th><th>Anomali GPS</th>
  </tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= esc($r['tanggal']) ?></td>
    <td><?= esc($r['nama_lengkap']) ?></td>
    <td><?= esc($r['unit_nama'] ?? '-') ?><?= $r['sub_nama'] ? ' - ' . esc($r['sub_nama']) : '' ?></td>
    <td><?= esc(label_shift((object)['kategori' => $r['shift_kategori'], 'jam_masuk' => $r['shift_masuk'], 'jam_pulang' => $r['shift_pulang']])) ?></td>
    <td><?= $r['waktu_masuk'] ? esc(date('H:i', strtotime($r['waktu_masuk']))) : '-' ?></td>
    <td><?= $r['waktu_pulang'] ? esc(date('H:i', strtotime($r['waktu_pulang']))) : '-' ?></td>
    <td><?= esc($r['status_masuk'] ?? '-') ?></td>
    <td><?= (int) $r['menit_terlambat'] ?></td>
    <td><?= $r['total_menit_kerja'] !== null ? (int) $r['total_menit_kerja'] : '-' ?></td>
    <td><?= $r['flag_anomali'] ? esc($r['catatan_anomali'] ?? 'Ya') : '-' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
