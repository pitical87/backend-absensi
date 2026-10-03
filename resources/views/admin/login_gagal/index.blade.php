@extends('layouts.admin')

@section('content')

{{-- BANNER PERINGATAN --}}
@if(($ringkasan['jumlahAncaman'] ?? 0) > 0)
  <div class="flash flash-error mb-4 items-start">
    <svg class="w-5 h-5 shrink-0 text-red-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <div>
      <strong class="block">Ada {{ $ringkasan['jumlahAncaman'] }} ancaman login aktif.</strong>
      <span class="text-[0.8rem]">
        {{ $ringkasan['jumlah_medium'] }} medium, {{ $ringkasan['jumlah_danger'] }} danger,
        {{ $ringkasan['jumlah_danger2'] }} danger 2. Tinjau daftar di bawah dan blokir akun bila perlu.
      </span>
    </div>
  </div>
@endif

<div class="stat-grid mb-4">
  <div class="stat merah">
    <span>Ancaman (Medium+)</span>
    <strong id="st-ancaman">{{ number_format($ringkasan['jumlahAncaman'], 0, ',', '.') }}</strong>
  </div>
  <div class="stat amber">
    <span>Mencurigakan</span>
    <strong id="st-mencurigakan">{{ number_format($ringkasan['jumlah_mencurigakan'], 0, ',', '.') }}</strong>
  </div>
  <div class="stat">
    <span>Total Kelompok</span>
    <strong id="st-grup">{{ number_format($ringkasan['totalGrup'], 0, ',', '.') }}</strong>
  </div>
  <div class="stat">
    <span>Total Percobaan Gagal</span>
    <strong id="st-gagal">{{ number_format($ringkasan['totalGagal'], 0, ',', '.') }}</strong>
  </div>
</div>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Tracker Login Gagal</h2>
    <span class="badge badge-biru" id="badge-jendela">{{ $opsiJendela[$filter['jam']] }}</span>
  </div>

  <div class="bilah-alat flex-wrap gap-2">
    <input type="text" id="input-q" placeholder="Cari email / IP…" value="{{ $filter['q'] }}" class="grow min-w-[180px]">

    <select id="filter-level" class="py-2 px-2.5 rounded-xl border border-slate-200 bg-white text-xs">
      <option value="">Semua Level</option>
      @foreach(['low' => 'Low', 'mencurigakan' => 'Mencurigakan', 'medium' => 'Medium', 'danger' => 'Danger', 'danger2' => 'Danger 2'] as $v => $l)
        <option value="{{ $v }}" @selected($filter['level'] === $v)>{{ $l }}</option>
      @endforeach
    </select>

    <select id="filter-sumber" class="py-2 px-2.5 rounded-xl border border-slate-200 bg-white text-xs">
      <option value="">Semua Sumber</option>
      @foreach($opsiSumber as $v => $l)
        <option value="{{ $v }}" @selected($filter['sumber'] === $v)>{{ $l }}</option>
      @endforeach
    </select>

    <select id="filter-jam" class="py-2 px-2.5 rounded-xl border border-slate-200 bg-white text-xs">
      @foreach($opsiJendela as $v => $l)
        <option value="{{ $v }}" @selected($filter['jam'] == $v)>{{ $l }}</option>
      @endforeach
    </select>

    <button type="button" id="btn-reset" class="btn btn-garis btn-kecil">Reset</button>
  </div>

  <div class="mt-2 teks-kecil teks-redup" id="status-cari"></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Level</th>
          <th>Akun / Target</th>
          <th class="tengah">Gagal</th>
          <th class="tengah" title="Jumlah IP atau email target berbeda">Sasaran</th>
          <th class="tengah" title="Jumlah perangkat berbeda">Perangkat</th>
          <th>IP Terakhir &amp; Perangkat</th>
          <th>Waktu Terakhir</th>
          <th class="tengah">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-login-gagal">
        @include('admin.login_gagal.rows', ['rows' => $rows])
      </tbody>
    </table>
  </div>

  <div id="paginasi-login-gagal">
    @include('admin.login_gagal.paginasi', ['rows' => $rows])
  </div>

  <p class="mt-3 teks-kecil teks-redup">
    Riwayat disimpan 30 hari. Level dihitung dari jumlah kegagalan dalam rentang yang dipilih:
    1× <strong>Low</strong>, 2–5× <strong>Mencurigakan</strong>, &gt;5× <strong>Medium</strong>,
    &gt;5× dengan sasaran/ip berbeda <strong>Danger</strong>, dan dengan perangkat berbeda <strong>Danger 2</strong>.
  </p>
</section>

@endsection

@section('script')
<script>
(function () {
  var inputQ   = document.getElementById('input-q');
  var filterLevel  = document.getElementById('filter-level');
  var filterSumber = document.getElementById('filter-sumber');
  var filterJam    = document.getElementById('filter-jam');
  var btnReset  = document.getElementById('btn-reset');
  var tbody     = document.getElementById('tbody-login-gagal');
  var paginasi  = document.getElementById('paginasi-login-gagal');
  var statusEl  = document.getElementById('status-cari');
  var csrf      = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var urlData   = '{{ route('admin.login_gagal.data') }}';
  var urlStatus = '{{ route('admin.login_gagal.status') }}';

  var halaman = 1;
  var jeda = null;

  function angka(n) {
    return (n === null || n === undefined) ? '0' : String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function muat(pesan) {
    if (pesan) statusEl.textContent = pesan;

    var params = new URLSearchParams({
      q: inputQ.value,
      level: filterLevel.value,
      sumber: filterSumber.value,
      jam: filterJam.value,
      hal: halaman
    });

    statusEl.textContent = 'Memuat…';

    fetch(urlData + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (! d['sukses']) throw new Error('Gagal memuat data');
        tbody.innerHTML = d.tbody;
        paginasi.innerHTML = d.paginasi;
        statusEl.textContent = '';

        if (d.ringkasan) {
          document.getElementById('st-ancaman').textContent     = angka(d.ringkasan.jumlahAncaman);
          document.getElementById('st-mencurigakan').textContent = angka(d.ringkasan.jumlah_mencurigakan);
          document.getElementById('st-grup').textContent        = angka(d.ringkasan.totalGrup);
          document.getElementById('st-gagal').textContent       = angka(d.ringkasan.totalGagal);
        }
      })
      .catch(function (e) {
        statusEl.textContent = 'Gagal memuat data: ' + e.message;
      });
  }

  function jadwalkan() {
    clearTimeout(jeda);
    halaman = 1;
    jeda = setTimeout(function () { muat(); }, 350);
  }

  inputQ.addEventListener('input', jadwalkan);
  filterLevel.addEventListener('change', jadwalkan);
  filterSumber.addEventListener('change', jadwalkan);
  filterJam.addEventListener('change', function () {
    jadwalkan();
  });

  btnReset.addEventListener('click', function () {
    inputQ.value = '';
    filterLevel.value = '';
    filterSumber.value = '';
    filterJam.value = filterJam.options[0].value;
    jadwalkan();
  });

  paginasi.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-page]');
    if (! a) return;
    e.preventDefault();
    halaman = parseInt(a.getAttribute('data-page'), 10) || 1;
    muat('Memuat…');
  });

  // Blokir / buka blokir akun
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-blokir], [data-buka]');
    if (! btn) return;

    var buka = btn.hasAttribute('data-buka');
    var nama = btn.getAttribute('data-nama') || 'akun ini';
    var id   = buka ? btn.getAttribute('data-buka') : btn.getAttribute('data-blokir');

    if (! buka && ! confirm('Blokir akun "' + nama + '"?\n\nSemua sesi aktifnya akan dicabut dan akun tidak bisa login sampai dibuka kembali.')) {
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Memproses…';

    var body = new URLSearchParams();
    body.append('id', id);
    body.append('_token', csrf);

    fetch(urlStatus, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf
      },
      body: body.toString()
    })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (res) {
        statusEl.textContent = res.d.pesan || '';
        statusEl.classList.toggle('text-red-600', ! res.ok);
        statusEl.classList.toggle('teks-redup', res.ok);
        muat();
      })
      .catch(function (e) {
        statusEl.textContent = 'Gagal: ' + e.message;
        btn.disabled = false;
        btn.textContent = buka ? 'Buka Blokir' : 'Blokir';
      });
  });
})();
</script>
@endsection
