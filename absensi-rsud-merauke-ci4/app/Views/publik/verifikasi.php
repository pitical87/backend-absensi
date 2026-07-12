<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Dokumen — RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="<?= base_url('assets/img/logo.svg') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body class="latar-pola">
<header class="topbar">
  <div class="topbar-isi">
    <a class="topbar-judul" href="<?= site_url('/') ?>">
      <img class="logo" src="<?= base_url('assets/img/logo.svg') ?>" alt="">
      <span class="topbar-merek">
        <span class="baris-kecil">Sistem Absensi</span>
        <strong>RSUD Merauke</strong>
      </span>
    </a>
  </div>
</header>
<main class="wadah" style="max-width:640px">
  <section class="kartu">
    <div class="kartu-kepala"><h2><?= ikon('perisai') ?> Verifikasi Keabsahan Surat Izin/Cuti</h2></div>
    <form method="get" action="<?= site_url('verifikasi') ?>" class="bilah-alat">
      <input type="text" name="kode" placeholder="Masukkan kode verifikasi…" value="<?= esc($kode) ?>"
             style="text-transform:uppercase" required>
      <button type="submit" class="btn btn-navy btn-kecil">Periksa</button>
    </form>

    <?php if ($kode !== ''): ?>
      <?php if ($hasil): ?>
        <div class="flash flash-sukses" style="margin-top:16px">Dokumen dengan kode ini sah dan tercatat dalam sistem.</div>
        <table class="tabel" style="margin-top:10px">
          <tr><th style="width:180px">Nomor Surat</th><td><?= esc($hasil['nomor_surat'] ?? '—') ?></td></tr>
          <tr><th>Nama Pegawai</th><td><?= esc($hasil['nama_lengkap']) ?></td></tr>
          <tr><th>NIP</th><td><?= esc($hasil['nip'] ?: '—') ?></td></tr>
          <tr><th>Jenis</th><td><?= esc($hasil['jenis_cuti'] ?: $hasil['jenis']) ?></td></tr>
          <tr><th>Periode</th><td><?= tgl_id($hasil['tanggal_mulai'], false) ?> s.d.
              <?= tgl_id($hasil['tanggal_selesai'], false) ?> (<?= (int) $hasil['lama_hari'] ?> hari kerja)</td></tr>
          <tr><th>Status</th><td><?= badge_izin($hasil['status']) ?></td></tr>
          <tr><th>Tanda Tangan</th>
            <td><?= $hasil['ttd_digital']
                  ? 'Elektronik oleh ' . esc($hasil['ttd_nama']) . ' pada ' . tgl_id($hasil['ttd_waktu'], false)
                  : 'Manual (ditandatangani basah oleh Direktur)' ?></td></tr>
        </table>
      <?php else: ?>
        <div class="flash flash-gagal" style="margin-top:16px">
          Kode verifikasi tidak ditemukan. Periksa kembali penulisan kode pada dokumen.</div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
<footer class="kaki">Sistem Absensi Pegawai RSUD Merauke · <?= date('Y') ?></footer>
</body>
</html>
