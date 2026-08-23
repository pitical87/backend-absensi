@extends('layouts.admin')

@section('content')

{{-- ===== HEADER & TOMBOL TAMBAH ===== --}}
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('gedung') !!} Tambah Unit Kerja</h2>
  </div>
  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="tambah_unit">
    <input type="text" name="nama" placeholder="Nama unit kerja baru…" required>
    <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="punya_sub" value="1" class="w-auto"> Memiliki sub unit
    </label>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Unit</button>
  </form>
</section>

{{-- ===== TAB NAVIGASI ===== --}}
@php
$tabs = [
  'semua'         => ['label' => 'Semua Unit Kerja', 'kata' => []],
  'rawat_inap'    => ['label' => 'Rawat Inap',       'kata' => ['rawat inap', 'inap', 'ranap']],
  'rawat_jalan'   => ['label' => 'Rawat Jalan',      'kata' => ['rawat jalan', 'jalan', 'rajal', 'poli', 'poliklinik', 'klinik']],
  'farmasi'       => ['label' => 'Farmasi',           'kata' => ['farmasi', 'apotek', 'obat', 'depo']],
  'administrasi'  => ['label' => 'Administrasi',      'kata' => ['administrasi', 'admin', 'tata usaha', 'keuangan', 'sdm', 'kepegawaian', 'humas', 'rekam medik', 'it', 'umum']],
];

// Kelompokkan unitList per tab
$unitPerTab = [];
foreach ($tabs as $kunci => $tab) {
  $unitPerTab[$kunci] = [];
}
foreach ($unitList as $uk) {
  $namaBawah = strtolower($uk->nama);
  $cocok = false;
  foreach ($tabs as $kunci => $tab) {
    if ($kunci === 'semua') continue;
    foreach ($tab['kata'] as $kata) {
      if (str_contains($namaBawah, $kata)) {
        $unitPerTab[$kunci][] = $uk;
        $cocok = true;
        break;
      }
    }
    if ($cocok) break;
  }
  // Jika tidak cocok ke tab manapun, masukkan ke "semua" saja
  if (!$cocok) {
    $unitPerTab['semua'][] = $uk;
  }
  // Selalu masukkan ke tab "semua"
}
// Tab "semua" berisi semua unit
$unitPerTab['semua'] = $unitList;

$activeTab = request()->query('tab', 'semua');
if (!array_key_exists($activeTab, $tabs)) $activeTab = 'semua';
@endphp

{{-- TAB NAVIGATION --}}
<div class="mb-5">
  <div class="flex flex-wrap gap-2 p-1 bg-slate-100/80 rounded-2xl border border-slate-200/70 w-fit">
    @foreach($tabs as $kunci => $tab)
      @php
        $jml = $kunci === 'semua' ? count($unitList) : count($unitPerTab[$kunci]);
        $isActive = $activeTab === $kunci;
      @endphp
      <a href="{{ url('admin/unit') }}?tab={{ $kunci }}"
         class="inline-flex items-center gap-2 py-2 px-4 rounded-xl text-sm font-medium transition-all duration-150 no-underline
                {{ $isActive
                  ? 'bg-white text-[#007afc] shadow-md shadow-blue-500/10 border border-slate-200/80 font-semibold'
                  : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}">
        {{ $tab['label'] }}
        <span class="px-2 py-0.5 rounded-full text-[0.68rem] font-bold
                     {{ $isActive ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500' }}">
          {{ $jml }}
        </span>
      </a>
    @endforeach
  </div>
</div>

{{-- ===== KONTEN TAB AKTIF ===== --}}
@php $tampilUnit = $unitPerTab[$activeTab]; @endphp

@if(count($tampilUnit) === 0)
<section class="kartu">
  <div class="text-center py-12 text-slate-400">
    <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
    </svg>
    <p class="text-sm font-medium text-slate-500">Belum ada unit kerja pada kategori <strong>{{ $tabs[$activeTab]['label'] }}</strong>.</p>
    <p class="text-xs text-slate-400 mt-1">Tambahkan unit baru atau sesuaikan nama unit agar cocok dengan kategori ini.</p>
  </div>
</section>
@endif

@foreach($tampilUnit as $uk)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>
      <span class="w-7 h-7 rounded-lg bg-blue-100 text-[#007afc] flex items-center justify-center shrink-0">
        {!! ikon('gedung', 14) !!}
      </span>
      {{ $uk->nama }}
      <span class="badge badge-biru ml-1">{{ (int) $uk->jml_pegawai }} pegawai</span>
    </h2>
    <form method="post" action="{{ url('admin/unit/aksi') }}"
          onsubmit="return confirm('Hapus unit {{ $uk->nama }} beserta seluruh sub unitnya?');">
      @csrf
      <input type="hidden" name="aksi" value="hapus_unit">
      <input type="hidden" name="id" value="{{ (int) $uk->id }}">
      <button type="submit" class="btn btn-bahaya btn-kecil">Hapus Unit</button>
    </form>
  </div>

  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="ubah_unit">
    <input type="hidden" name="id" value="{{ (int) $uk->id }}">
    <input type="text" name="nama" value="{{ $uk->nama }}" required>
    <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="punya_sub" value="1" class="w-auto" {{ $uk->punya_sub ? 'checked' : '' }}>
      Memiliki sub unit
    </label>
    <select name="atasan_id" class="w-auto min-w-[170px] text-sm" title="Atasan default pegawai unit ini">
      <option value="">— Atasan unit —</option>
      @foreach($pegawaiPilihan as $opt)
        <option value="{{ (int) $opt->id }}" {{ (int) $uk->atasan_id === (int) $opt->id ? 'selected' : '' }}>{{ $opt->nama_lengkap }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
  </form>

  @if($uk->punya_sub)
    <h3 class="mt-4 mb-2 text-sm font-bold text-navy">Sub Unit</h3>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead>
          <tr>
            <th>Nama Sub Unit</th>
            <th>Atasan Sub Unit</th>
            <th>Jumlah Pegawai</th>
            <th class="w-[110px]">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($subPerUnit[(int) $uk->id] ?? [] as $su)
          <tr>
            <td>{{ $su->nama }}</td>
            <td>
              <form method="post" action="{{ url('admin/unit/aksi') }}" class="flex items-center gap-1.5">
                @csrf
                <input type="hidden" name="aksi" value="ubah_sub">
                <input type="hidden" name="id" value="{{ (int) $su->id }}">
                <select name="atasan_id" class="w-auto min-w-[160px] text-sm" title="Atasan default pegawai sub unit ini">
                  <option value="">— Atasan sub unit —</option>
                  @foreach($pegawaiPilihan as $opt)
                    <option value="{{ (int) $opt->id }}" {{ (int) $su->atasan_id === (int) $opt->id ? 'selected' : '' }}>{{ $opt->nama_lengkap }}</option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
              </form>
            </td>
            <td class="angka">{{ (int) $su->jml_pegawai }}</td>
            <td>
              <form method="post" action="{{ url('admin/unit/aksi') }}"
                    onsubmit="return confirm('Hapus sub unit {{ $su->nama }}?');">
                @csrf
                <input type="hidden" name="aksi" value="hapus_sub">
                <input type="hidden" name="id" value="{{ (int) $su->id }}">
                <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            </td>
          </tr>
          @endforeach
          @if(empty($subPerUnit[(int) $uk->id]))
          <tr><td colspan="4" class="tengah teks-redup py-4">Belum ada sub unit.</td></tr>
          @endif
        </tbody>
      </table>
    </div>

    <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat mt-3">
      @csrf
      <input type="hidden" name="aksi" value="tambah_sub">
      <input type="hidden" name="unit_kerja_id" value="{{ (int) $uk->id }}">
      <input type="text" name="nama" placeholder="Nama sub unit baru untuk {{ $uk->nama }}…" required>
      <select name="atasan_id" class="w-auto min-w-[170px] text-sm" title="Atasan default pegawai sub unit ini">
        <option value="">— Atasan sub unit —</option>
        @foreach($pegawaiPilihan as $opt)
          <option value="{{ (int) $opt->id }}">{{ $opt->nama_lengkap }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Sub Unit</button>
    </form>
  @endif
</section>
@endforeach

@endsection
