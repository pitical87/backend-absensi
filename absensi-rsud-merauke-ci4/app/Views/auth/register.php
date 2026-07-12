<?php $lama = $lama ?? []; ?>
<?= $this->extend('layout/otentikasi') ?>
<?= $this->section('isi') ?>

<?php if (! empty($galat)): ?>
  <div class="flash flash-gagal"><?= esc($galat) ?></div>
<?php endif; ?>

<form method="post" action="<?= site_url('register') ?>" autocomplete="off">
  <?= csrf_field() ?>

  <div class="form-grup">
    <label class="wajib">Nama Lengkap</label>
    <input type="text" name="nama_lengkap" required value="<?= esc($lama['nama_lengkap'] ?? '') ?>">
  </div>

  <div class="form-baris">
    <div class="form-grup">
      <label>Tempat Lahir</label>
      <input type="text" name="tempat_lahir" value="<?= esc($lama['tempat_lahir'] ?? '') ?>">
    </div>
    <div class="form-grup">
      <label>Tanggal Lahir</label>
      <input type="date" name="tanggal_lahir" value="<?= esc($lama['tanggal_lahir'] ?? '') ?>">
    </div>
  </div>

  <div class="form-baris">
    <div class="form-grup">
      <label class="wajib">Jenis Kelamin</label>
      <select name="jenis_kelamin" required>
        <option value="">— Pilih —</option>
        <?php foreach (['Laki-Laki', 'Perempuan'] as $jk): ?>
          <option <?= ($lama['jenis_kelamin'] ?? '') === $jk ? 'selected' : '' ?>><?= $jk ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-grup">
      <label class="wajib">Agama</label>
      <select name="agama" required>
        <option value="">— Pilih —</option>
        <?php foreach (['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'] as $ag): ?>
          <option <?= ($lama['agama'] ?? '') === $ag ? 'selected' : '' ?>><?= $ag ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-baris">
    <div class="form-grup">
      <label class="wajib">Email</label>
      <input type="email" name="email" required value="<?= esc($lama['email'] ?? '') ?>">
    </div>
    <div class="form-grup">
      <label>No. HP</label>
      <input type="text" name="no_hp" value="<?= esc($lama['no_hp'] ?? '') ?>">
    </div>
  </div>

  <div class="form-grup">
    <label>NIP <span class="teks-redup" style="font-weight:400">(opsional)</span></label>
    <input type="text" name="nip" value="<?= esc($lama['nip'] ?? '') ?>"
           placeholder="Nomor Induk Pegawai">
  </div>

  <div class="form-baris">
    <div class="form-grup">
      <label class="wajib">Tempat Kerja</label>
      <select name="unit_kerja_id" id="unit_kerja_id" required>
        <option value="">— Pilih —</option>
        <?php foreach ($unitList as $uk): ?>
          <option value="<?= (int) $uk['id'] ?>" data-sub="<?= (int) $uk['punya_sub'] ?>"
            <?= (int) ($lama['unit_kerja_id'] ?? 0) === (int) $uk['id'] ? 'selected' : '' ?>>
            <?= esc($uk['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-grup" id="grup-sub">
      <label class="wajib">Sub Unit</label>
      <select name="sub_unit_id" id="sub_unit_id">
        <option value="">— Pilih —</option>
      </select>
    </div>
  </div>

  <div class="form-grup">
    <label class="wajib">Profesi</label>
    <select name="profesi_id" required>
      <option value="">— Pilih —</option>
      <?php foreach ($profList as $p): ?>
        <option value="<?= (int) $p['id'] ?>"
          <?= (int) ($lama['profesi_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
          <?= esc($p['nama']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-baris">
    <div class="form-grup">
      <label class="wajib">Jabatan</label>
      <select name="jabatan_kategori" id="jabatan_kategori" required>
        <?php foreach ($kategoriJab as $k): ?>
          <option <?= ($lama['jabatan_kategori'] ?? 'Staf/Pelaksana') === $k ? 'selected' : '' ?>><?= $k ?></option>
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
          <option <?= ($lama['posisi'] ?? 'Staf') === $ps ? 'selected' : '' ?>><?= esc($ps) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="petunjuk">Menentukan alur persetujuan izin/cuti Anda. Untuk Kepala Seksi/Sub Bagian,
        Kepala Bidang/Bagian, atau Direktur, pastikan field Jabatan di atas sudah sesuai.</div>
    </div>
    <div class="form-grup" id="grup-seksi-pembina">
      <label>Seksi/Sub Bagian Pembina</label>
      <select name="seksi_pembina_id">
        <option value="">— Belum ditetapkan (admin dapat melengkapi nanti) —</option>
        <?php foreach ($seksiPembinaPilihan as $sp): ?>
          <option value="<?= (int) $sp['id'] ?>"
            <?= (int) ($lama['seksi_pembina_id'] ?? 0) === (int) $sp['id'] ? 'selected' : '' ?>>
            <?= esc($sp['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="petunjuk">Seksi/Sub Bagian yang membina unit Anda — menentukan ke mana pengajuan
        izin/cuti diteruskan setelah Koordinator/Kepala Unit Anda.</div>
    </div>
  </div>

  <div class="form-grup">
    <label class="teks-kecil" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" name="status_pegawai" value="PNS" style="width:auto"
        <?= ($lama['status_pegawai'] ?? '') === 'PNS' ? 'checked' : '' ?>>
      Pegawai Negeri Sipil (PNS)
    </label>
    <div class="petunjuk">Hanya pegawai berstatus PNS yang dapat mengajukan Cuti (Tahunan, Sakit,
      Melahirkan, Alasan Penting, Besar, atau di Luar Tanggungan Negara).</div>
  </div>


  <div class="form-baris">
    <div class="form-grup">
      <label class="wajib">Password</label>
      <input type="password" name="password" required minlength="6">
      <div class="petunjuk">Minimal 6 karakter.</div>
    </div>
    <div class="form-grup">
      <label class="wajib">Konfirmasi Password</label>
      <input type="password" name="password2" required minlength="6">
    </div>
  </div>

  <button type="submit" class="btn btn-primer btn-blok"><?= ikon('centang', 17) ?> Daftar</button>
</form>

<div class="pembatas"><span>sudah punya akun?</span></div>
<a href="<?= site_url('login') ?>" class="btn btn-garis btn-blok">Masuk</a>

<script>
const SUB_UNIT = <?= json_encode($subPerUnit) ?>;
const SUB_TERPILIH = <?= (int) ($lama['sub_unit_id'] ?? 0) ?>;
(function () {
  const unit = document.getElementById('unit_kerja_id');
  const sub  = document.getElementById('sub_unit_id');
  const grup = document.getElementById('grup-sub');
  function segarkan(pilih) {
    const opt = unit.options[unit.selectedIndex];
    const punyaSub = opt && opt.dataset.sub === '1';
    grup.style.visibility = punyaSub ? 'visible' : 'hidden';
    sub.required = punyaSub;
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
const JAB_TERPILIH = <?= (int) ($lama['jabatan_id'] ?? 0) ?>;
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
