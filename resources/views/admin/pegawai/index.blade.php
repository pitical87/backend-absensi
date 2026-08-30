@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Daftar Pegawai <span class="badge badge-biru" id="total-pegawai">{{ number_format($pegawai->total(), 0, ',', '.') }}</span></h2>
    <div class="flex flex-wrap items-center gap-2">
      <a class="btn btn-garis btn-kecil" href="{{ route('admin.pegawai.template') }}">{!! ikon('unduh', 15) !!} Template Excel</a>
      <a class="btn btn-garis btn-kecil" href="#" id="btn-excel">{!! ikon('unduh', 15) !!} Export Excel</a>
      <a class="btn btn-garis btn-kecil" href="#" id="btn-pdf">{!! ikon('unduh', 15) !!} Export PDF</a>
      <a class="btn btn-garis btn-kecil" href="#" id="btn-cetak">{!! ikon('print', 15) !!} Print</a>
      <a class="btn btn-primer btn-kecil" href="{{ url('admin/pegawai/form') }}">+ Tambah Pegawai</a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.pegawai.import') }}" enctype="multipart/form-data"
        class="bilah-alat" style="border-top:1px dashed var(--warna-garis);padding-top:12px;margin-top:4px">
    @csrf
    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    <button type="submit" class="btn btn-navy btn-kecil">{!! ikon('unduh', 15) !!} Import Excel</button>
    <span class="teks-kecil teks-redup">
      Kolom mengikuti template. Baris dengan email yang sudah terdaftar akan dilewati.
    </span>
  </form>

  <div class="grid gap-2" id="form-cari" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    <input type="text" id="input-q" class="w-full" placeholder="Cari nama / email…" value="{{ $q }}">
    <select id="select-unit" class="w-full">
      <option value="">Semua Bidang</option>
      @foreach($unitList as $uk)
          <option value="{{ (int) $uk->id }}"
              {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
              {{ $uk->nama }}
          </option>
      @endforeach
    </select>
    <select id="select-sub" class="w-full">
      <option value="">Semua Sub Bidang</option>
      @if($fSub)
        <option value="{{ $fSub }}" selected>{{ (app(\App\Models\SubUnit::class)->find($fSub))?->nama ?? 'Semua Sub Bidang' }}</option>
      @endif
    </select>
    <select id="select-jabatan" class="w-full">
      <option value="">Semua Jabatan</option>
      @foreach($jabatanList as $j)
        <option value="{{ (int) $j->id }}" {{ $fJab === (int) $j->id ? 'selected' : '' }}>
          {{ $j->kategori }} — {{ $j->nama }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="mt-2 teks-kecil teks-redup" id="status-cari"></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Unit / Sub Unit</th>
            <th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody id="tbody-pegawai">
        @include('admin.pegawai.rows', ['pegawai' => $pegawai])
      </tbody>
    </table>
  </div>

  <div id="paginasi-pegawai">
    @include('admin.pegawai.paginasi', ['pegawai' => $pegawai])
  </div>
</section>

@endsection

@section('script')
<script>
(function () {
  const inputQ   = document.getElementById('input-q');
  const selectU  = document.getElementById('select-unit');
  const selectS  = document.getElementById('select-sub');
  const selectJ  = document.getElementById('select-jabatan');
  const tbody    = document.getElementById('tbody-pegawai');
  const paginasi = document.getElementById('paginasi-pegawai');
  const badge    = document.getElementById('total-pegawai');
  const status   = document.getElementById('status-cari');
  const urlData  = '{{ route('admin.pegawai.data') }}';
  const subPerUnit = {!! json_encode((object) $subPerUnit) !!};
  let halaman    = 1;
  let jam        = null;

  function subUnitTerpilih() {
    const val = selectS.value;
    const unit = selectU.value;
    if (typeof subPerUnit[unit] === 'undefined') return '';
    for (let i = 0; i < subPerUnit[unit].length; i++) {
      if (String(subPerUnit[unit][i].id) === String(val)) return val;
    }
    return '';
  }

  function isiSubBidang() {
    const unit = selectU.value;
    const lama = selectS.value;
    const daftar = (typeof subPerUnit[unit] !== 'undefined') ? subPerUnit[unit] : [];
    selectS.innerHTML = '<option value="">Semua Sub Bidang</option>';
    daftar.forEach(function (s) {
      const o = document.createElement('option');
      o.value = s.id; o.textContent = s.nama;
      if (String(s.id) === String(lama)) o.selected = true;
      selectS.appendChild(o);
    });
    if (! daftar.length) { selectS.value = ''; }
  }

  function paramsMu() {
    return new URLSearchParams({
      page: halaman,
      q: inputQ.value,
      unit: selectU.value,
      sub: (selectU.value && selectS.value) ? selectS.value : '',
      jabatan: selectJ.value
    });
  }

  function muat(resetHalaman) {
    if (resetHalaman) halaman = 1;
    status.textContent = 'Memuat…';
    fetch(urlData + '?' + paramsMu().toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { status.textContent = 'Gagal memuat data.'; return; }
        tbody.innerHTML    = h.tbody;
        paginasi.innerHTML = h.paginasi;
        badge.textContent  = h.total.toLocaleString('id-ID');
        status.textContent = h.total === 0 ? 'Tidak ada hasil.' : '';
        halaman = h.halaman;
        pasangPaginasi();
      })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  function pasangPaginasi() {
    paginasi.querySelectorAll('a[data-page]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        halaman = parseInt(a.getAttribute('data-page'), 10) || 1;
        muat(false);
      });
    });
  }

  function arahkan(kind) {
    const p = paramsMu();
    p.delete('page');
    window.location = '{{ url('admin/pegawai') }}/' + kind + '?' + p.toString();
  }

  document.getElementById('btn-excel').addEventListener('click', function (e) { e.preventDefault(); arahkan('excel'); });
  document.getElementById('btn-pdf').addEventListener('click', function (e) { e.preventDefault(); arahkan('pdf'); });
  document.getElementById('btn-cetak').addEventListener('click', function (e) { e.preventDefault(); arahkan('cetak'); });

  inputQ.addEventListener('input', function () {
    clearTimeout(jam);
    jam = setTimeout(function () { muat(true); }, 350);
  });

  inputQ.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(jam);
      muat(true);
    }
  });

  selectU.addEventListener('change', function () { isiSubBidang(); muat(true); });
  selectS.addEventListener('change', function () { muat(true); });
  selectJ.addEventListener('change', function () { muat(true); });

  isiSubBidang();
  pasangPaginasi();

})();
</script>
@endsection
