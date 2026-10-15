@extends('layouts.admin')

@section('content')

<section class="kartu" data-ajax-tabel data-url-data="{{ route('admin.rekap.data') }}">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Rekap Kehadiran Bulan {{ BULAN_ID[$bulan] . ' ' . $tahun }}</h2>
    <span class="badge badge-biru">{{ number_format($pegawai->total(), 0, ',', '.') }} pegawai</span>
  </div>

  <form method="get" action="{{ url('admin/rekap') }}" id="form-filter-rekap" class="bilah-alat">
    <select name="bulan" data-kategori="bulan">
      @foreach(BULAN_ID as $i => $nb)
        <option value="{{ $i }}" {{ $i === $bulan ? 'selected' : '' }}>{{ $nb }}</option>
      @endforeach
    </select>
    <select name="tahun" data-kategori="tahun">
      @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
        <option value="{{ $t }}" {{ $t === $tahun ? 'selected' : '' }}>{{ $t }}</option>
      @endfor
    </select>
    <select name="unit" data-kategori="unit">
      <option value="">Semua Unit Kerja</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}</option>
      @endforeach
    </select>
    <select name="jab" data-kategori="jab" title="Kategori jabatan">
      <option value="">Semua Jabatan</option>
      @foreach($kategoriJab as $k)
        <option value="{{ $k }}" {{ $f['jab'] === $k ? 'selected' : '' }}>{{ $k }}</option>
      @endforeach
    </select>
    <select name="njab" data-kategori="njab" title="Nama jabatan tertentu">
      <option value="">Semua Nama Jabatan</option>
      @foreach($jabPilihan as $kat => $daftar)
        <optgroup label="{{ $kat }}">
          @foreach($daftar as $j)
            <option value="{{ (int) $j['id'] }}" {{ $f['njab'] === (int) $j['id'] ? 'selected' : '' }}>
              {{ $j['nama'] }}</option>
          @endforeach
        </optgroup>
      @endforeach
    </select>
    <select name="org" data-kategori="org" title="Bidang / Bagian pada struktur organisasi">
      <option value="">Semua Bidang/Bagian</option>
      @foreach($orgList as $o)
        <option value="{{ (int) $o['id'] }}" {{ $f['org'] === (int) $o['id'] ? 'selected' : '' }}>
          {{ $o['unit_label'] }}</option>
      @endforeach
    </select>
    <select name="prof" data-kategori="prof">
      <option value="">Semua Profesi</option>
      @foreach($profList as $pr)
        <option value="{{ (int) $pr->id }}" {{ $f['prof'] === (int) $pr->id ? 'selected' : '' }}>
          {{ $pr->nama }}</option>
      @endforeach
    </select>
  </form>
  

  <div class="aksi-baris mb-3.5 gap-2 justify-center">
    <form method="post" action="{{ url('admin/rekap/generate') }}" id="form-generate-rekap">
      @csrf
      <input type="hidden" name="bulan" value="{{ $bulan }}">
      <input type="hidden" name="tahun" value="{{ $tahun }}">
      <input type="hidden" name="unit" value="{{ $fUnit }}">
      <input type="hidden" name="jab" value="{{ $f['jab'] }}">
      <input type="hidden" name="njab" value="{{ $f['njab'] }}">
      <input type="hidden" name="org" value="{{ $f['org'] }}">
      <input type="hidden" name="prof" value="{{ $f['prof'] }}">
      <input type="hidden" name="q" value="{{ $q }}">
      <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Generate &amp; Simpan Rekap</button>
    </form>
    <a class="btn btn-navy btn-kecil" target="_blank" data-ekspor="{{ url('admin/rekap/cetak') }}"
       href="{{ url('admin/rekap/cetak?' . $qs) }}">{!! ikon('cetak', 15) !!} Cetak Laporan (PDF)</a>
    <a class="btn btn-garis btn-kecil" data-ekspor="{{ url('admin/rekap/excel') }}" data-mode="rekap"
       href="{{ url('admin/rekap/excel?' . $qs . '&mode=rekap') }}">{!! ikon('unduh', 15) !!} Export Excel (Rekap)</a>
    <a class="btn btn-garis btn-kecil" data-ekspor="{{ url('admin/rekap/excel') }}" data-mode="detail"
       href="{{ url('admin/rekap/excel?' . $qs . '&mode=detail') }}">{!! ikon('unduh', 15) !!} Export Excel (Detail Harian)</a>
  </div>
<div class="d-flex mb-3 gap-2">
    <input type="text" name="q" class="form-control form-control-kecil" value="{{ $q }}" placeholder="Cari nama / NIP…" data-cari autocomplete="off">
  </div>
  <div data-tabel>
    @include('admin.rekap.tabel', ['pegawai' => $pegawai, 'rekapPer' => $rekapPer, 'bulan' => $bulan, 'tahun' => $tahun, 'q' => $q])
  </div>
</section>

<script>
(function () {
  var filter = document.getElementById('form-filter-rekap');
  var generate = document.getElementById('form-generate-rekap');
  if (! filter) return;

  function filterSaatIni() {
    var params = new URLSearchParams();
    filter.querySelectorAll('select[name], input[name]').forEach(function (el) {
      if (el.value !== '') params.set(el.name, el.value);
    });
    return params;
  }

  function sinkronkan() {
    var params = filterSaatIni();

    if (generate) {
      generate.querySelectorAll('input[name]').forEach(function (inp) {
        inp.value = params.has(inp.name) ? params.get(inp.name) : '';
      });
    }

    document.querySelectorAll('[data-ekspor]').forEach(function (a) {
      var p = new URLSearchParams(params);
      if (a.dataset.mode) p.set('mode', a.dataset.mode);
      var qs = p.toString();
      a.href = a.dataset.ekspor + (qs ? '?' + qs : '');
    });
  }

  filter.addEventListener('change', sinkronkan);
  filter.addEventListener('input', sinkronkan);
  sinkronkan();
})();
</script>

@endsection
