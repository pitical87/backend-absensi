<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('pegawai') ?> Daftar Pegawai <span class="badge badge-biru"><?= count($pegawai) ?></span></h2>
    <a class="btn btn-primer btn-kecil" href="<?= site_url('admin/pegawai/form') ?>">+ Tambah Pegawai</a>
  </div>

  <form method="get" action="<?= site_url('admin/pegawai') ?>" class="bilah-alat">
    <input type="text" name="q" placeholder="Cari nama / email…" value="<?= esc($q) ?>">
    <select name="unit">
      <option value="">Semua Unit</option>
      <?php foreach ($unitList as $uk): ?>
        <option value="<?= (int) $uk['id'] ?>" <?= $fUnit === (int) $uk['id'] ? 'selected' : '' ?>>
          <?= esc($uk['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Terapkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Unit / Sub Unit</th><th>Profesi</th>
            <th>Shift</th><th>Peran</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pegawai as $p): ?>
        <tr>
          <td><strong><?= esc($p['nama_lengkap']) ?></strong>
            <br><span class="teks-kecil teks-redup"><?= esc($p['jabatan_nama']
                  ?? ($p['jabatan_kategori'] ?? 'Staf/Pelaksana')) ?><?=
                  ! empty($p['nip']) ? ' · NIP ' . esc($p['nip']) : '' ?></span>
            <?php if (! empty($p['posisi']) && $p['posisi'] !== 'Staf'): ?>
              <br><span class="badge badge-ungu teks-kecil"><?= esc($p['posisi']) ?></span>
            <?php endif; ?>
            <?php if (($p['status_pegawai'] ?? '') === 'PNS'): ?>
              <span class="badge badge-teal teks-kecil">PNS</span>
            <?php endif; ?></td>
          <td><?= esc($p['email']) ?></td>
          <td><?= esc($p['unit_nama'] ?? '—') ?><?= $p['sub_nama'] ? ' — ' . esc($p['sub_nama']) : '' ?></td>
          <td><?= esc($p['profesi_nama'] ?? '—') ?></td>
          <td><?= esc(label_shift($p['shift_id'] ? ['kategori' => $p['shift_kategori'],
                'jam_masuk' => $p['shift_masuk'], 'jam_pulang' => $p['shift_pulang']] : null)) ?></td>
          <td><?= $p['role'] === 'admin' ? '<span class="badge badge-biru">Admin</span>' : 'Pegawai' ?></td>
          <td><?= $p['status'] === 'aktif'
                ? '<span class="badge badge-hijau">Aktif</span>'
                : '<span class="badge badge-abu">Nonaktif</span>' ?></td>
          <td>
            <div class="aksi-baris">
              <a class="btn btn-garis btn-kecil" href="<?= site_url('admin/pegawai/form/' . (int) $p['id']) ?>">Ubah</a>
              <?php if ((int) $p['id'] !== (int) session('uid')): ?>
                <form method="post" action="<?= site_url('admin/pegawai/status') ?>" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-garis btn-kecil">
                    <?= $p['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                </form>
                <form method="post" action="<?= site_url('admin/pegawai/hapus') ?>" style="display:inline"
                      onsubmit="return confirm('Hapus permanen <?= esc($p['nama_lengkap'], 'js') ?>?\nSeluruh data absensinya ikut terhapus dan tidak dapat dikembalikan.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (! $pegawai): ?>
        <tr><td colspan="8" class="tengah teks-redup">Tidak ada data pegawai.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?= $this->endSection() ?>
