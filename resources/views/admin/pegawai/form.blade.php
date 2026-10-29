@php $v = fn ($k, $def = '') => old($k, $edit->$k ?? $def); @endphp
@extends('layouts.admin')

@section('content')

@if ($errors->any())
<section class="kartu border-red-300 mb-4">
  <div class="flex items-start gap-3 text-red-700">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
      <p class="font-semibold">Periksa kembali isian formulir:</p>
      <ul class="list-disc list-inside mt-1">
        @foreach ($errors->all() as $galat)
          <li>{{ $galat }}</li>
        @endforeach
      </ul>
    </div>
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!}
      {{ $edit ? 'Ubah Data: ' . $edit->nama_lengkap : 'Formulir Pegawai Baru' }}</h2>
    <a class="btn btn-garis btn-kecil" href="{{ url('admin/pegawai') }}">&larr; Kembali</a>
  </div>

  <form method="post" action="{{ url('admin/pegawai/simpan') }}">
    @csrf
    <input type="hidden" name="id" value="{{ (int) ($edit->id ?? 0) }}">

    <div class="form-grup">
      <label class="wajib">Nama Lengkap</label>
      <input type="text" name="nama_lengkap" required value="{{ $v('nama_lengkap') }}">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" value="{{ $v('tempat_lahir') }}">
      </div>
      <div class="form-grup">
        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" value="{{ $v('tanggal_lahir') }}">
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin">
          <option value="">— Pilih —</option>
          @foreach(['Laki-Laki', 'Perempuan'] as $jk)
            <option {{ old('jenis_kelamin', $edit->jenis_kelamin ?? '') === $jk ? 'selected' : '' }}>{{ $jk }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-grup">
        <label>Agama</label>
        <select name="agama">
          <option value="">— Pilih —</option>
          @foreach($agamaList as $ag)
            <option {{ old('agama', $edit->agama ?? '') === $ag ? 'selected' : '' }}>{{ $ag }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Email</label>
        <input type="email" name="email" required value="{{ $v('email') }}">
      </div>
      <div class="form-grup">
        <label>No. HP</label>
        <input type="text" name="no_hp" value="{{ $v('no_hp') }}">
      </div>
    </div>

    <div class="form-grup">
      <label>NIP</label>
      <input type="text" name="nip" value="{{ $v('nip') }}" placeholder="Nomor Induk Pegawai">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Kerja</label>
        <select name="unit_kerja_id" id="unit_kerja_id">
          <option value="">— Pilih —</option>
          @foreach($unitList as $uk)
            <option value="{{ (int) $uk->id }}" data-sub="{{ (int) $uk->punya_sub }}"
              {{ (int) old('unit_kerja_id', $edit->unit_kerja_id ?? 0) === (int) $uk->id ? 'selected' : '' }}>
              {{ $uk->nama }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-grup" id="grup-sub">
        <label>Sub Unit</label>
        <select name="sub_unit_id" id="sub_unit_id">
          <option value="">— Pilih —</option>
        </select>
      </div>
    </div>

    @php $atTerpilih = array_map('intval', (array) old('atasan', $atasanTerpilih)); @endphp
    <div class="form-grup">
      <label>Atasan Langsung</label>
      <input type="text" id="cari-atasan-form" placeholder="Cari nama pegawai…" class="mb-2"
             oninput="saringAtasanForm(this.value)">
      <div id="daftar-atasan-form" class="max-h-44 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100">
        @foreach($atasanPilihan as $opt)
          <label class="baris-at flex items-center gap-2.5 py-2 px-3 cursor-pointer hover:bg-slate-50"
                 data-nama="{{ strtolower($opt->nama_lengkap) }}">
            <input type="checkbox" name="atasan[]" value="{{ (int) $opt->id }}" class="chk-at w-auto"
              {{ in_array((int) $opt->id, $atTerpilih, true) ? 'checked' : '' }}>
            <span class="text-sm">{{ $opt->nama_lengkap }}</span>
          </label>
        @endforeach
        @if(count($atasanPilihan) === 0)
          <div class="py-4 tengah teks-redup text-sm">Belum ada kandidat atasan.</div>
        @endif
      </div>
      <div class="petunjuk">Otomatis terpilih mengikuti sub unit / unit kerja saat pilihan di atas berubah.
        Boleh memilih lebih dari satu atasan.</div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Jabatan</label>
        <select name="jabatan_kategori" id="jabatan_kategori" required>
          @foreach($kategoriJab as $k)
            <option {{ old('jabatan_kategori', $edit->jabatan_kategori ?? 'Staf/Pelaksana') === $k ? 'selected' : '' }}>{{ $k }}</option>
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
            <option {{ old('posisi', $edit->posisi ?? 'Staf') === $ps ? 'selected' : '' }}>{{ $ps }}</option>
          @endforeach
        </select>
        <div class="petunjuk">Menentukan alur persetujuan izin/cuti. Untuk Kepala Seksi/Sub Bagian,
          Kepala Bidang/Bagian, atau Direktur, samakan dengan field Jabatan di atas.</div>
      </div>
      <div class="form-grup" id="grup-seksi-pembina">
        <label>Seksi/Sub Bagian Pembina</label>
        <select name="seksi_pembina_id">
          <option value="">— Belum ditetapkan —</option>
          @foreach($seksiPembinaPilihan as $sp)
            <option value="{{ (int) $sp['id'] }}"
              {{ (int) old('seksi_pembina_id', $edit->seksi_pembina_id ?? 0) === (int) $sp['id'] ? 'selected' : '' }}>
              {{ $sp['nama'] }}</option>
          @endforeach
        </select>
        <div class="petunjuk">Menentukan tujuan tahap ke-2 &amp; ke-3 alur persetujuan izin/cuti pegawai ini.</div>
      </div>
    </div>

    <div class="form-grup">
      <label class="teks-kecil flex items-center gap-2">
        <input type="checkbox" name="status_pegawai" value="PNS" class="w-auto"
          {{ old('status_pegawai', $edit->status_pegawai ?? '') === 'PNS' ? 'checked' : '' }}>
        Pegawai Negeri Sipil (PNS)
      </label>
      <div class="petunjuk">Hanya pegawai berstatus PNS yang dapat mengajukan Cuti.</div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Profesi</label>
        <select name="profesi_id">
          <option value="">— Pilih —</option>
          @foreach($profList as $p)
            <option value="{{ (int) $p->id }}"
              {{ (int) old('profesi_id', $edit->profesi_id ?? 0) === (int) $p->id ? 'selected' : '' }}>
              {{ $p->nama }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-baris">
      
      <div class="form-grup">
        <label>Status Akun</label>
        <select name="status">
          <option value="aktif"    {{ old('status', $edit->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
          <option value="nonaktif" {{ old('status', $edit->status ?? '') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
        </select>
      </div>
    </div>

    <div class="form-grup">
      <label class="teks-kecil flex items-center gap-2">
        <input type="checkbox" name="password_default" id="password_default" value="1" class="w-auto"
          {{ ! $edit ? 'checked' : '' }}>
        Gunakan password default (<em>{{ $passwordDefault }}</em>)
      </label>
      <div class="petunjuk">Bila dicentang, pegawai memakai password default instansi yang diatur di
        Pengaturan. Hilangkan centang untuk mengisi password khusus.</div>
    </div>

    <div class="form-grup" id="grup-input-password">
      <label {!! $edit ? '' : 'class="wajib"' !!}>Password {!! $edit ? '(kosongkan bila tidak diubah)' : '' !!}</label>
      <div class="relative">
        <input type="password" name="password" id="input-password" minlength="6" {{ $edit ? '' : 'required' }}
               autocomplete="new-password">
         <button
                  type="button"
                  id="toggle-password"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer"
                  title="Toggle Password Visibility"
                >
                 <svg id="eye-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg id="eye-off-icon" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                  </svg>
        </button>
      </div>
    </div>

    <div class="aksi-baris">
      <button type="submit" class="btn btn-primer">{!! ikon('centang', 17) !!} Simpan</button>
      <a href="{{ url('admin/pegawai') }}" class="btn btn-garis">Batal</a>
    </div>
  </form>
</section>

@endsection

@section('script')
<script>
const SUB_UNIT = @json($subPerUnit);
const SUB_TERPILIH = {{ (int) old('sub_unit_id', $edit->sub_unit_id ?? 0) }};
const ATASAN_UNIT = @json($atasanUnitMap);
const ATASAN_SUB = @json($atasanSubMap);

function terapkanWarisanAtasan() {
  const unit = document.getElementById('unit_kerja_id');
  const sub = document.getElementById('sub_unit_id');
  let target = null;
  if (sub && sub.value && ATASAN_SUB[sub.value]) target = Number(ATASAN_SUB[sub.value]);
  if (target === null && unit && unit.value && ATASAN_UNIT[unit.value]) target = Number(ATASAN_UNIT[unit.value]);
  document.querySelectorAll('.chk-at').forEach(function (c) {
    c.checked = target !== null && Number(c.value) === target;
  });
}

function saringAtasanForm(kata) {
  kata = kata.toLowerCase();
  document.querySelectorAll('#daftar-atasan-form .baris-at').forEach(function (b) {
    b.style.display = b.dataset.nama.includes(kata) ? '' : 'none';
  });
}

(function () {
  const masuk  = document.getElementById('input-password');
  const tombol = document.getElementById('toggle-password');
  const mata   = document.getElementById('eye-icon');
  const matatutup = document.getElementById('eye-off-icon');
  if (tombol && masuk) {
    tombol.addEventListener('click', function () {
      const isPassword = masuk.type === 'password';
      masuk.type = isPassword ? 'text' : 'password';
      mata.classList.toggle('hidden', isPassword);
      matatutup.classList.toggle('hidden', !isPassword);
    });
  }
})();

(function () {
  const chk  = document.getElementById('password_default');
  const grup = document.getElementById('grup-input-password');
  const masuk = document.getElementById('input-password');
  if (chk && grup) {
    const wajibAwal = masuk && masuk.hasAttribute('required');
    function segarkan() {
      const pakaiDefault = chk.checked;
      grup.hidden = pakaiDefault;
      if (masuk) {
        if (pakaiDefault) masuk.removeAttribute('required');
        else if (wajibAwal) masuk.setAttribute('required', '');
      }
    }
    chk.addEventListener('change', segarkan);
    segarkan();
  }
})();

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
  unit.addEventListener('change', function () { segarkan(0); terapkanWarisanAtasan(); });
  sub.addEventListener('change', terapkanWarisanAtasan);
  segarkan(SUB_TERPILIH);
})();

const JAB = @json($jabPilihan);
const JAB_TERPILIH = {{ (int) old('jabatan_id', $edit->jabatan_id ?? 0) }};
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
@endsection
