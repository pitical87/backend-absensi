<?php
/**
 * Partial rekursif bagan organisasi.
 * Var: $cabang (array node), $kelola (bool — tampilkan tombol admin)
 */
$kelasKategori = [
    'Direktur' => 'direktur', 'Kepala Bidang' => 'kabid', 'Kepala Bagian' => 'kabag',
    'Kepala Seksi' => 'kasi', 'Kepala Sub Bagian' => 'kasubag',
];
?>
<ul class="<?= empty($akar) ? '' : 'pohon' ?>">
  <?php foreach ($cabang as $j): ?>
  <li>
    <div class="simpul <?= $kelasKategori[$j['kategori']] ?? '' ?>">
      <span class="tanda"></span>
      <span class="isi-simpul">
        <span class="nama-jab"><?= esc($j['nama']) ?></span><br>
        <?php if ($j['pejabat']): ?>
          <?php foreach ($j['pejabat'] as $p): ?>
            <span class="pejabat"><?= esc($p['nama_lengkap']) ?>
              <?php if ($p['nip']): ?><small>· NIP <?= esc($p['nip']) ?></small><?php endif; ?>
            </span><br>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="kosong">— belum terisi —</span>
        <?php endif; ?>
      </span>
      <?php if (! empty($kelola)): ?>
        <span class="aksi-simpul">
          <button type="button" class="btn btn-garis btn-kecil"
                  onclick="ubahJabatan(<?= (int) $j['id'] ?>, '<?= esc($j['nama'], 'js') ?>', '<?= esc($j['unit_label'] ?? '', 'js') ?>')">Ubah</button>
          <form method="post" action="<?= site_url('admin/struktur/aksi') ?>"
                onsubmit="return confirm('Hapus jabatan <?= esc($j['nama'], 'js') ?> dari struktur?');">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
            <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
          </form>
        </span>
      <?php endif; ?>
    </div>
    <?php if ($j['anak']): ?>
      <?= view('partial/pohon', ['cabang' => $j['anak'], 'kelola' => $kelola ?? false, 'akar' => false]) ?>
    <?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
