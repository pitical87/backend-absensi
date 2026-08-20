@php $lama = $lama ?? []; @endphp
@extends('layouts.auth')

@section('content')

@if(! empty($galat))
  <div class="flash flash-error">{{ $galat }}</div>
@endif

<div class="stepper" data-stepper>
  <div class="stepper-item stepper-active" data-stepper-item="1">
    <span class="stepper-angka">1</span>
    <span class="stepper-label">Identitas Diri</span>
  </div>
  <div class="stepper-item" data-stepper-item="2">
    <span class="stepper-angka">2</span>
    <span class="stepper-label">Akun</span>
  </div>
  <div class="stepper-item" data-stepper-item="3">
    <span class="stepper-angka">3</span>
    <span class="stepper-label">Kepegawaian</span>
  </div>
</div>

<form method="post" action="{{ route('register') }}" autocomplete="off">

  @csrf

  <div class="langkah" data-langkah="1">
    <h3 class="langkah-judul">Identitas Diri</h3>

    <div class="form-grup">
      <label class="wajib">Nama Lengkap</label>
      <input type="text" name="nama_lengkap" required value="{{ $lama['nama_lengkap'] ?? '' }}">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" value="{{ $lama['tempat_lahir'] ?? '' }}">
      </div>
      <div class="form-grup">
        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" value="{{ $lama['tanggal_lahir'] ?? '' }}">
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Jenis Kelamin</label>
        <select name="jenis_kelamin" required>
          <option value="">— Pilih —</option>
          @foreach(['Laki-Laki', 'Perempuan'] as $jk)
            <option {{ ($lama['jenis_kelamin'] ?? '') === $jk ? 'selected' : '' }}>{{ $jk }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-grup">
        <label class="wajib">Agama</label>
        <select name="agama" required>
          <option value="">— Pilih —</option>
          @foreach(['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'] as $ag)
            <option {{ ($lama['agama'] ?? '') === $ag ? 'selected' : '' }}>{{ $ag }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="langkah" data-langkah="2" hidden>
    <h3 class="langkah-judul">Akun</h3>

    <div class="form-grup">
      <label class="wajib">Email</label>
      <input type="email" name="email" required value="{{ $lama['email'] ?? '' }}">
    </div>

    <div class="form-grup">
      <label>No. HP</label>
      <input type="text" name="no_hp" value="{{ $lama['no_hp'] ?? '' }}">
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
  </div>

  <div class="langkah" data-langkah="3" hidden>
    <h3 class="langkah-judul">Kepegawaian</h3>

    <div class="form-grup">
      <label>NIP <span class="teks-redup font-normal">(opsional)</span></label>
      <input type="text" name="nip" value="{{ $lama['nip'] ?? '' }}"
             placeholder="Nomor Induk Pegawai">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Tempat Kerja</label>
        <select name="unit_kerja_id" id="unit_kerja_id" required>
          <option value="">— Pilih —</option>
          @foreach($unitList as $uk)
            <option value="{{ (int) $uk->id }}" data-sub="{{ (int) $uk->punya_sub }}"
              {{ (int) ($lama['unit_kerja_id'] ?? 0) === (int) $uk->id ? 'selected' : '' }}>
              {{ $uk->nama }}
            </option>
          @endforeach
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
        @foreach($profList as $p)
          <option value="{{ (int) $p->id }}"
            {{ (int) ($lama['profesi_id'] ?? 0) === (int) $p->id ? 'selected' : '' }}>
            {{ $p->nama }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Jabatan</label>
        <select name="jabatan_kategori" id="jabatan_kategori" required>
          @foreach($kategoriJab as $k)
            <option {{ ($lama['jabatan_kategori'] ?? 'Staf/Pelaksana') === $k ? 'selected' : '' }}>{{ $k }}</option>
          @endforeach
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
          @foreach($posisiList as $ps)
            <option {{ ($lama['posisi'] ?? 'Staf') === $ps ? 'selected' : '' }}>{{ $ps }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-grup" id="grup-seksi-pembina">
        <label>Seksi/Sub Bagian Pembina</label>
        <select name="seksi_pembina_id">
          <option value="">— Belum ditetapkan (admin dapat melengkapi nanti) —</option>
          @foreach($seksiPembinaPilihan as $sp)
            <option value="{{ (int) $sp['id'] }}"
              {{ (int) ($lama['seksi_pembina_id'] ?? 0) === (int) $sp['id'] ? 'selected' : '' }}>
              {{ $sp['nama'] }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-grup">
      <label class="teks-kecil" style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="status_pegawai" value="PNS" style="width:auto"
          {{ ($lama['status_pegawai'] ?? '') === 'PNS' ? 'checked' : '' }}>
        Pegawai Negeri Sipil (PNS)
      </label>
    </div>
  </div>

  <div class="form-baris langkah-nav">
    <div class="form-grup">
      <button type="button" class="btn btn-garis btn-blok" data-langkah-kembali hidden>&larr; Kembali</button>
    </div>
    <div class="form-grup">
      <button type="button" class="btn btn-primer btn-blok" data-langkah-lanjut>{!! ikon('masuk', 17) !!} Lanjut</button>
      <button type="submit" class="btn btn-primer btn-blok" data-langkah-kirim hidden>{!! ikon('centang', 17) !!} Daftar</button>
    </div>
  </div>

</form>

<div class="pembatas"><span>sudah punya akun?</span></div>
<a href="{{ route('login') }}" class="btn btn-garis btn-blok">Masuk</a>

<script>
const SUB_UNIT = @json($subPerUnit);
const SUB_TERPILIH = {{ (int) ($lama['sub_unit_id'] ?? 0) }};
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

const JAB = @json($jabPilihan);
const JAB_TERPILIH = {{ (int) ($lama['jabatan_id'] ?? 0) }};
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

(function () {
  var langkah = 1;
  var total   = 3;
  var tombolKembali = document.querySelector('[data-langkah-kembali]');
  var tombolLanjut  = document.querySelector('[data-langkah-lanjut]');
  var tombolKirim   = document.querySelector('[data-langkah-kirim]');
  var itemStepper   = document.querySelectorAll('[data-stepper-item]');

  function tampilLangkah(n) {
    langkah = Math.min(Math.max(n, 1), total);
    document.querySelectorAll('[data-langkah]').forEach(function (el) {
      el.hidden = parseInt(el.dataset.langkah, 10) !== langkah;
    });
    tombolKembali.hidden = langkah === 1;
    tombolLanjut.hidden  = langkah === total;
    tombolKirim.hidden   = langkah !== total;
    itemStepper.forEach(function (el) {
      el.classList.toggle('stepper-active', parseInt(el.dataset.stepperItem, 10) <= langkah);
    });
  }

  tombolLanjut.addEventListener('click', function () {
    var bagian = document.querySelector('[data-langkah="' + langkah + '"]');
    var wajib  = bagian.querySelectorAll('[required]');
    for (var i = 0; i < wajib.length; i++) {
      if (! wajib[i].reportValidity()) return;
    }
    tampilLangkah(langkah + 1);
  });

  tombolKembali.addEventListener('click', function () { tampilLangkah(langkah - 1); });
  tampilLangkah(1);
})();
</script>

@endsection