<?php $v = static fn ($k, $def = '') => esc($edit[$k] ?? $def); ?>
<?= $this->extend('layout/admin') ?>
<?= $this->section('isi') ?>

<section class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('pegawai') ?>
      <?= $edit ? 'Ubah Data: ' . esc($edit['nama_lengkap']) : 'Formulir Pegawai Baru' ?></h2>
    <a class="btn btn-garis btn-kecil" href="<?= site_url('admin/pegawai') ?>">&larr; Kembali</a>
  </div>

  <form method="post" action="<?= site_url('admin/pegawai/simpan') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

    <div class="form-grup">
      <label class="wajib">Nama Lengkap</label>
      <input type="text" name="nama_lengkap" required value="<?= $v('nama_lengkap') ?>">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" value="<?= $v('tempat_lahir') ?>">
      </div>
      <div class="form-grup">
        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" value="<?= $v('tanggal_lahir') ?>">
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin">
          <option value="">— Pilih —</option>
          <?php foreach (['Laki-Laki', 'Perempuan'] as $jk): ?>
            <option <?= ($edit['jenis_kelamin'] ?? '') === $jk ? 'selected' : '' ?>><?= $jk ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grup">
        <label>Agama</label>
        <select name="agama">
          <option value="">— Pilih —</option>
          <?php foreach ($agamaList as $ag): ?>
            <option <?= ($edit['agama'] ?? '') === $ag ? 'selected' : '' ?>><?= $ag ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Email</label>
        <input type="email" name="email" required value="<?= $v('email') ?>">
      </div>
      <div class="form-grup">
        <label>No. HP</label>
        <input type="text" name="no_hp" value="<?= $v('no_hp') ?>">
      </div>
    </div>

    <div class="form-grup">
      <label>NIP</label>
      <input type="text" name="nip" value="<?= $v('nip') ?>" placeholder="Nomor Induk Pegawai">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Kerja</label>
        <select name="unit_kerja_id" id="unit_kerja_id">
          <option value="">— Pilih —</option>
          <?php foreach ($unitList as $uk): ?>
            <option value="<?= (int) $uk['id'] ?>" data-sub="<?= (int) $uk['punya_sub'] ?>"
              <?= (int) ($edit['unit_kerja_id'] ?? 0) === (int) $uk['id'] ? 'selected' : '' ?>>
              <?= esc($uk['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grup" id="grup-sub">
        <label>Sub Unit</label>
        <select name="sub_unit_id" id="sub_unit_id">
          <option value="">— Pilih —</option>
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Jabatan</label>
        <select name="jabatan_kategori" id="jabatan_kategori" required>
          <?php foreach ($kategoriJab as $k): ?>
            <option <?= ($edit['jabatan_kategori'] ?? 'Staf/Pelaksana') === $k ? 'selected' : '' ?>><?= $k ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grup" id="grup-jab" hidden>
        <label class="wajib">Nama Jabatan</label>
        <select name="jabatan_id" id="jabatan_id">
          <option value="">— Pilih —</option>
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Posisi</label>
        <select name="posisi" id="posisi" required>
          <?php foreach ($posisiList as $ps): ?>
            <option <?= ($edit['posisi'] ?? 'Staf') === $ps ? 'selected' : '' ?>><?= esc($ps) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="petunjuk">Menentukan alur persetujuan izin/cuti. Untuk Kepala Seksi/Sub Bagian,
          Kepala Bidang/Bagian, atau Direktur, samakan dengan field Jabatan di atas.</div>
      </div>
      <div class="form-grup" id="grup-seksi-pembina">
        <label>Seksi/Sub Bagian Pembina</label>
        <select name="seksi_pembina_id">
          <option value="">— Belum ditetapkan —</option>
          <?php foreach ($seksiPembinaPilihan as $sp): ?>
            <option value="<?= (int) $sp['id'] ?>"
              <?= (int) ($edit['seksi_pembina_id'] ?? 0) === (int) $sp['id'] ? 'selected' : '' ?>>
              <?= esc($sp['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="petunjuk">Menentukan tujuan tahap ke-2 &amp; ke-3 alur persetujuan izin/cuti pegawai ini.</div>
      </div>
    </div>

    <div class="form-grup">
      <label class="teks-kecil" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="status_pegawai" value="PNS" style="width:auto"
          <?= ($edit['status_pegawai'] ?? '') === 'PNS' ? 'checked' : '' ?>>
        Pegawai Negeri Sipil (PNS)
      </label>
      <div class="petunjuk">Hanya pegawai berstatus PNS yang dapat mengajukan Cuti.</div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Profesi</label>
        <select name="profesi_id">
          <option value="">— Pilih —</option>
          <?php foreach ($profList as $p): ?>
            <option value="<?= (int) $p['id'] ?>"
              <?= (int) ($edit['profesi_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
              <?= esc($p['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-grup">
        <label>Shift Kerja</label>
        <select name="shift_id">
          <option value="">— Belum diatur —</option>
          <?php foreach ($shiftGrup as $kategori => $daftar): ?>
            <optgroup label="Shift <?= esc($kategori) ?>">
              <?php foreach ($daftar as $s): ?>
                <option value="<?= (int) $s['id'] ?>"
                  <?= (int) ($edit['shift_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                  <?= jam_singkat($s['jam_masuk']) ?> - <?= jam_singkat($s['jam_pulang']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Peran</label>
        <select name="role">
          <option value="pegawai" <?= ($edit['role'] ?? 'pegawai') === 'pegawai' ? 'selected' : '' ?>>Pegawai</option>
          <option value="admin"   <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div class="form-grup">
        <label>Status Akun</label>
        <select name="status">
          <option value="aktif"    <?= ($edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
          <option value="nonaktif" <?= ($edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
      </div>
    </div>

    <div class="form-grup">
      <label <?= $edit ? '' : 'class="wajib"' ?>>Password <?= $edit ? '(kosongkan bila tidak diubah)' : '' ?></label>
      <input type="password" name="password" minlength="6" <?= $edit ? '' : 'required' ?>
             autocomplete="new-password">
    </div>

    <div class="aksi-baris">
      <button type="submit" class="btn btn-primer"><?= ikon('centang', 17) ?> Simpan</button>
      <a href="<?= site_url('admin/pegawai') ?>" class="btn btn-garis">Batal</a>
    </div>
  </form>
</section>

<?= $this->endSection() ?>

<?= $this->section('skrip') ?>
<script>
const SUB_UNIT = <?= json_encode($subPerUnit) ?>;
const SUB_TERPILIH = <?= (int) ($edit['sub_unit_id'] ?? 0) ?>;
(function () {
  const unit = document.getElementById('unit_kerja_id');
  const sub  = document.getElementById('sub_unit_id');
  const grup = document.getElementById('grup-sub');
  function segarkan(pilih) {
    const opt = unit.options[unit.selectedIndex];
    const punyaSub = opt && opt.dataset.sub === '1';
    grup.style.visibility = punyaSub ? 'visible' : 'hidden';
    sub.innerHTML = '<option value="">— Pilih —</option>';
    if (punyaSub && SUB_UNIT[unit.value]) {
      SUB_UNIT[unit.value].forEach(function (s) {
        const o = document.createElement('option');
        o.value = s.id; o.textContent = s.nama;
        if (pilih && s.id === pilih) o.selected = true;
        sub.appendChild(o);
      });
    }
  }
  unit.addEventListener('change', function () { segarkan(0); });
  segarkan(SUB_TERPILIH);
})();

const JAB = <?= json_encode($jabPilihan, JSON_UNESCAPED_UNICODE) ?>;
const JAB_TERPILIH = <?= (int) ($edit['jabatan_id'] ?? 0) ?>;
const TANPA_NAMA = ['Direktur', 'Staf/Pelaksana'];
(function () {
  var kat  = document.getElementById('jabatan_kategori');
  var grup = document.getElementById('grup-jab');
  var sel  = document.getElementById('jabatan_id');
  function segarkanJab(pilih) {
    var tampil = TANPA_NAMA.indexOf(kat.value) === -1;
    grup.hidden  = ! tampil;
    sel.required = tampil;
    sel.innerHTML = '<option value="">— Pilih —</option>';
    if (tampil && JAB[kat.value]) {
      JAB[kat.value].forEach(function (j) {
        var o = document.createElement('option');
        o.value = j.id; o.textContent = j.nama;
        if (j.id === pilih) o.selected = true;
        sel.appendChild(o);
      });
    }
  }
  kat.addEventListener('change', function () { segarkanJab(0); });
  segarkanJab(JAB_TERPILIH);
})();

(function () {
  var pos  = document.getElementById('posisi');
  var grup = document.getElementById('grup-seksi-pembina');
  var TANPA_SEKSI = ['Kepala Seksi/Sub Bagian', 'Kepala Bidang/Bagian', 'Direktur'];
  function segarkanPosisi() {
    grup.hidden = TANPA_SEKSI.indexOf(pos.value) !== -1;
  }
  pos.addEventListener('change', segarkanPosisi);
  segarkanPosisi();
})();
</script>
<?= $this->endSection() ?>
