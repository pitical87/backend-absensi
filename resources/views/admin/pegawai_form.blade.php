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
      <div class="form-grup">
        <label>Shift Kerja</label>
        <select name="shift_id">
          <option value="">— Belum diatur —</option>
          @foreach($shiftGrup as $kategori => $daftar)
            <optgroup label="Shift {{ $kategori }}">
              @foreach($daftar as $s)
                <option value="{{ (int) $s->id }}"
                  {{ (int) old('shift_id', $edit->shift_id ?? 0) === (int) $s->id ? 'selected' : '' }}>
                  {{ jam_singkat($s->jam_masuk) }} - {{ jam_singkat($s->jam_pulang) }}
                </option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Peran</label>
        <select name="role">
          <option value="pegawai" {{ old('role', $edit->role ?? 'pegawai') === 'pegawai' ? 'selected' : '' }}>Pegawai</option>
          <option value="admin"   {{ old('role', $edit->role ?? '') === 'admin' ? 'selected' : '' }}>Admin</option>
        </select>
      </div>
      <div class="form-grup">
        <label>Status Akun</label>
        <select name="status">
          <option value="aktif"    {{ old('status', $edit->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
          <option value="nonaktif" {{ old('status', $edit->status ?? '') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
        </select>
      </div>
    </div>

    <div class="form-grup">
      <label {!! $edit ? '' : 'class="wajib"' !!}>Password {!! $edit ? '(kosongkan bila tidak diubah)' : '' !!}</label>
      <input type="password" name="password" minlength="6" {{ $edit ? '' : 'required' }}
             autocomplete="new-password">
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
