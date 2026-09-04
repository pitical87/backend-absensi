@extends('layouts.admin')

@section('content')

@php
  $hasil = session('finger_hasil');
  $tabAwal = (string) request('tab', 'mapping');
  if (! in_array($tabAwal, ['mapping', 'csv', 'mesin', 'pengaturan'], true)) {
      $tabAwal = 'mapping';
  }
@endphp

@if($hasil)
<section class="kartu mb-5">
  <div class="kartu-kepala">
    <h2>{!! ikon('centang') !!} Hasil Impor Absen FingerSpot</h2>
  </div>
  <div class="p-4 text-sm">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
      <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
        <div class="text-2xl font-bold text-emerald-700">{{ number_format($hasil['ditambah'], 0, ',', '.') }}</div>
        <div class="teks-kecil text-emerald-700">Absen Baru</div>
      </div>
      <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
        <div class="text-2xl font-bold text-blue-700">{{ number_format($hasil['diperbarui'], 0, ',', '.') }}</div>
        <div class="teks-kecil text-blue-700">Diperbarui (melengkapi GPS)</div>
      </div>
      <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
        <div class="text-2xl font-bold text-slate-700">{{ number_format($hasil['dilewati'], 0, ',', '.') }}</div>
        <div class="teks-kecil text-slate-700">Dilewati (sudah lengkap)</div>
      </div>
      <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
        <div class="text-2xl font-bold text-amber-700">{{ number_format($hasil['tanpa_mapping'], 0, ',', '.') }}</div>
        <div class="teks-kecil text-amber-700">Scan tanpa mapping</div>
      </div>
    </div>
    <p class="teks-redup teks-kecil mt-3">Data FingerSpot <strong>tidak menimpa</strong> absensi GPS/manual yang sudah lengkap. Ia hanya mengisi bagian masuk/pulang yang masih kosong.</p>
  </div>
</section>
@endif

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="baris-tab-finger">
  <button type="button" class="finger-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="mapping">Mapping ID Finger</button>
  <button type="button" class="finger-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="csv">Upload CSV</button>
  <button type="button" class="finger-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="mesin">Ambil dari Mesin</button>
  <button type="button" class="finger-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="pengaturan">Pengaturan</button>
</div>

{{-- Tab: Mapping ID Finger --}}
<section class="kartu finger-panel" id="panel-mapping">
  <div class="kartu-kepala">
    <h2>{!! ikon('log') !!} Mapping ID Finger per Pegawai</h2>
    <span class="badge badge-biru" id="jumlah-mapping">{{ count($pegawaiMapping) }} pegawai</span>
  </div>

  <div class="p-3">
    <input type="text" id="cari-mapping" placeholder="Cari pegawai / NIP / ID finger…" class="flex-1 min-w-[200px]">
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Pegawai</th><th>Unit / Sub Unit</th><th>ID Finger</th><th class="min-w-[150px]">Aksi</th></tr></thead>
      <tbody id="mapping-tubuh">
        @php
          $jumlahTerpetakan = 0;
        @endphp
        @foreach($pegawaiMapping as $pm)
        @php
          $u = $pm['user'];
          $sudah = $pm['finger_id'] !== null;
          if ($sudah) $jumlahTerpetakan++;
        @endphp
        <tr data-nama="{{ mb_strtolower($u->nama_lengkap ?? '') }}" data-nip="{{ mb_strtolower((string) $u->nip) }}" data-finger="{{ mb_strtolower((string) $pm['finger_id']) }}">
          <td>
            <strong>{{ $u->nama_lengkap }}</strong>
            @if($u->nip)<br><span class="teks-redup teks-kecil">NIP {{ $u->nip }}</span>@endif
          </td>
          <td>
            {{ $u->unitKerja?->nama ?? '—' }}
            @if($u->subUnit?->nama)<br><span class="teks-redup teks-kecil">{{ $u->subUnit->nama }}</span>@endif
          </td>
          <td>
            @if($sudah)
              <code>{{ $pm['finger_id'] }}</code>
            @else
              <span class="badge badge-merah">Belum</span>
            @endif
          </td>
          <td class="flex flex-wrap gap-1">
            <button type="button" class="btn btn-garis btn-kecil set-mapping"
              data-id="{{ $pm['mapping_id'] ?? '' }}" data-user="{{ $u->id }}"
              data-finger="{{ $pm['finger_id'] ?? '' }}" data-nama="{{ $u->nama_lengkap }}">
              {{ $sudah ? 'Ubah' : 'Set ID' }}
            </button>
            @if($sudah)
              <form method="post" action="{{ route('admin.finger.mapping.hapus') }}" onsubmit="return confirm('Hapus mapping ID finger ini?')">
                @csrf
                <input type="hidden" name="id" value="{{ $pm['mapping_id'] }}">
                <button type="submit" class="btn btn-merah btn-kecil">Hapus</button>
              </form>
            @endif
          </td>
        </tr>
        @endforeach
        @if(empty($pegawaiMapping))
        <tr><td colspan="4" class="tengah teks-redup">Tidak ada pegawai aktif.</td></tr>
        @endif
        <tr id="mapping-kosong" class="hidden"><td colspan="4" class="tengah teks-redup">Tidak ada pegawai yang cocok.</td></tr>
      </tbody>
    </table>
  </div>
  <nav class="paginasi px-2" id="paginasi-mapping">
    <span class="info" id="info-halaman-mapping"></span>
  </nav>
  @php
    $jumlahBelum = count($pegawaiMapping) - $jumlahTerpetakan;
  @endphp
  <p class="teks-redup teks-kecil p-2">Terpetakan <strong>{{ $jumlahTerpetakan }}</strong> dari {{ count($pegawaiMapping) }} pegawai. @if($jumlahBelum > 0) Sisanya <strong>{{ $jumlahBelum }}</strong> pegawai berstatus <strong>Belum</strong>.@endif</p>
</section>

{{-- Modal Set / Edit Mapping --}}
<div id="modal-edit-mapping" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-lg">
    <div class="kartu-kepala">
      <h2>{!! ikon('atur') !!} Atur Mapping ID Finger</h2>
      <button type="button" class="btn btn-garis btn-kecil tutup-modal-edit">&times; Tutup</button>
    </div>
    <form method="post" action="{{ route('admin.finger.mapping.simpan') }}" class="p-4 space-y-2">
      @csrf
      <input type="hidden" name="id" id="edit-mapping-id" value="">
      <input type="hidden" name="user_id" id="edit-mapping-user" value="">
      <label class="blok">
        <span class="teks-kecil">Pegawai</span>
        <input type="text" id="edit-mapping-nama" disabled>
      </label>
      <label class="blok">
        <span class="teks-kecil">ID Finger di Mesin</span>
        <input type="text" name="finger_id" id="edit-mapping-finger" placeholder="cth. 101" required>
      </label>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-garis btn-kecil tutup-modal-edit">Batal</button>
        <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Tab: Upload CSV --}}
<section class="kartu finger-panel hidden" id="panel-csv">
  <div class="kartu-kepala">
    <h2>{!! ikon('unggah') !!} Upload File CSV</h2>
  </div>
  <form method="post" action="{{ route('admin.finger.import') }}" enctype="multipart/form-data" class="bilah-alat m-0 flex-wrap">
    @csrf
    <input type="file" name="file" accept=".csv,.txt,text/csv" required class="flex-1 min-w-[200px]">
    <button type="submit" class="btn btn-navy btn-kecil">Impor</button>
  </form>
  <p class="px-3 pb-3 text-xs teks-redup">File CSV export dari mesin/software FingerSpot. Kolom ID finger, tanggal, dan jam dideteksi otomatis dari header. Scan pertama = masuk, scan terakhir = pulang.</p>

  <div class="px-3 pb-3">
    <form method="get" action="{{ route('admin.finger') }}" class="bilah-alat m-0 flex-wrap">
      <input type="hidden" name="tab" value="csv">
      <label class="teks-redup teks-kecil">Bulan
        <select name="bulan">
          @foreach(range(1, 12) as $b)
            <option value="{{ $b }}" @if($b === $bulan) selected @endif>{{ $b }}</option>
          @endforeach
        </select>
      </label>
      <label class="teks-redup teks-kecil">Tahun
        <select name="tahun">
          @foreach(range(now()->year - 2, now()->year + 1) as $t)
            <option value="{{ $t }}" @if($t === $tahun) selected @endif>{{ $t }}</option>
          @endforeach
        </select>
      </label>
      <button type="submit" class="btn btn-navy btn-kecil">{!! ikon('filter', 14) !!} Tampilkan</button>
    </form>

    <div class="tabel-bungkus mt-3">
      <table class="tabel">
        <thead><tr><th>Tanggal</th><th>Pegawai</th><th>Masuk</th><th>Pulang</th><th>Status Masuk</th><th>Status Pulang</th></tr></thead>
        <tbody>
          @foreach($preview as $r)
          <tr>
            <td>{{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d/m/Y') }}</td>
            <td><strong>{{ $r->user?->nama_lengkap ?? '—' }}</strong></td>
            <td>{{ $r->waktu_masuk?->format('H:i') ?? '—' }}</td>
            <td>{{ $r->waktu_pulang?->format('H:i') ?? '—' }}</td>
            <td>{{ $r->status_masuk ?? '—' }}</td>
            <td>{{ $r->status_pulang ?? '—' }}</td>
          </tr>
          @endforeach
          @if($preview->isEmpty())
          <tr><td colspan="6" class="tengah teks-redup">Belum ada data hasil impor FingerSpot pada bulan {{ $bulan }}/{{ $tahun }}.</td></tr>
          @endif
        </tbody>
      </table>
      @if(! $preview->isEmpty())
      <p class="teks-redup teks-kecil p-2">Menampilkan hingga 300 catatan terbaru hasil impor FingerSpot (sumber "Finger").</p>
      @endif
    </div>
  </div>
</section>

{{-- Tab: Ambil dari Mesin --}}
<section class="kartu finger-panel hidden" id="panel-mesin">
  <div class="kartu-kepala">
    <h2>{!! ikon('koneksi') !!} Ambil Langsung dari Mesin</h2>
  </div>
  <form method="post" action="{{ route('admin.finger.import.url') }}" class="bilah-alat m-0 flex-col items-stretch gap-2">
    @csrf
    <div class="grid grid-cols-2 gap-2">
      <label class="blok m-0">
        <span class="teks-kecil">Tanggal Mulai</span>
        <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', now()->format('Y-m-d')) }}" required>
      </label>
      <label class="blok m-0">
        <span class="teks-kecil">Tanggal Selesai</span>
        <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', now()->format('Y-m-d')) }}" required>
      </label>
    </div>
    <button type="submit" class="btn btn-navy btn-kecil self-end">Ambil &amp; Impor</button>
  </form>
  
</section>

{{-- Tab: Pengaturan --}}
<section class="kartu finger-panel hidden" id="panel-pengaturan">
  <div class="kartu-kepala">
    <h2>{!! ikon('atur') !!} Pengaturan Mesin FingerSpot</h2>
  </div>
  <form method="post" action="{{ route('admin.finger.setting') }}" class="p-2 flex flex-col gap-2">
    @csrf
      <label class="blok m-0">
        <span class="teks-kecil">IP Mesin</span>
        <input type="text" name="ip" placeholder="cth. 192.168.1.100" value="{{ old('ip', $ipMesin) }}" required>
      </label>
      <label class="blok m-0">
        <span class="teks-kecil">Port</span>
        <input type="number" name="port" placeholder="cth. 80" value="{{ old('port', $portMesin) }}" min="1" max="65535" required>
      </label>
    <button type="submit" class="btn btn-navy btn-besar self-end">Simpan Pengaturan</button>
  </form></section>

@endsection

@section('script')
<script>
(function () {
  // ── Tab switcher ──
  const tombolTab = document.querySelectorAll('.finger-tab');
  const panelTab  = document.querySelectorAll('.finger-panel');

  function bukaTab(id) {
    tombolTab.forEach(function (b) {
      const aktif = b.dataset.tab === id;
      b.classList.toggle('bg-white', aktif);
      b.classList.toggle('text-slate-900', aktif);
      b.classList.toggle('shadow-sm', aktif);
      b.classList.toggle('text-slate-500', !aktif);
    });
    panelTab.forEach(function (p) { p.classList.toggle('hidden', p.id !== 'panel-' + id); });
    if (window.history.replaceState) history.replaceState(null, '', '#tab=' + id);
  }

  tombolTab.forEach(function (b) {
    b.addEventListener('click', function () { bukaTab(b.dataset.tab); });
  });

  let tabAwal = '';
  const hash = (location.hash || '').replace('#', '');
  if (hash.startsWith('tab=')) tabAwal = hash.slice(4);
  const dariQuery = new URLSearchParams(location.search).get('tab');
  if (!tabAwal && dariQuery) tabAwal = dariQuery;
  if (!['mapping', 'csv', 'mesin', 'pengaturan'].includes(tabAwal)) tabAwal = 'mapping';
  bukaTab(tabAwal);

  // ── Modal Mapping ──
  const modalEdit   = document.getElementById('modal-edit-mapping');

  function bukaModal(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function tutupModal(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  document.querySelectorAll('.tutup-modal-edit').forEach(function (b) {
    b.addEventListener('click', function () { tutupModal(modalEdit); });
  });

  if (modalEdit) modalEdit.addEventListener('click', function (e) { if (e.target === modalEdit) tutupModal(modalEdit); });

  document.querySelectorAll('.set-mapping').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('edit-mapping-id').value = btn.dataset.id || '';
      document.getElementById('edit-mapping-user').value = btn.dataset.user;
      document.getElementById('edit-mapping-finger').value = btn.dataset.finger || '';
      document.getElementById('edit-mapping-nama').value = btn.dataset.nama || '';
      bukaModal(modalEdit);
    });
  });

  // ── Cari mapping ──
  var cari = document.getElementById('cari-mapping');
  var tubuh = document.getElementById('mapping-tubuh');
  var kosong = document.getElementById('mapping-kosong');
  var jumlahLabel = document.getElementById('jumlah-mapping');
  var paginasiWrap = document.getElementById('paginasi-mapping');
  var infoHalaman = document.getElementById('info-halaman-mapping');
  var PER = 25;

  function barisCocok(tr, q) {
    return tr.dataset.nama.includes(q) || tr.dataset.nip.includes(q) || tr.dataset.finger.includes(q);
  }

  function semuaBaris() {
    return Array.prototype.slice.call(tubuh.querySelectorAll('tr[data-nama]'));
  }

  function renderPaginasi(q) {
    var daftar = semuaBaris();
    var hasil = daftar.filter(function (tr) { return barisCocok(tr, q); });
    var total = hasil.length;
    var maxHal = Math.max(1, Math.ceil(total / PER));

    // simpan hal aktif saat ini (reset ke halaman dengan data saat q berubah)
    if (typeof renderPaginasi._hal !== 'number') renderPaginasi._hal = 1;
    var hal = renderPaginasi._hal;
    if (hal > maxHal) hal = maxHal;
    renderPaginasi._hal = hal;

    daftar.forEach(function (tr) { tr.classList.add('hidden'); });
    kosong.classList.toggle('hidden', total > 0);
    if (jumlahLabel) jumlahLabel.textContent = (q === '' ? daftar.length : total) + ' pegawai';

    var mulai = (hal - 1) * PER;
    hasil.slice(mulai, mulai + PER).forEach(function (tr) { tr.classList.remove('hidden'); });

    renderPaginasi._total = total;
    renderPaginasi._maxHal = maxHal;
    renderPaginasi._hal = hal;
    buildNavigasi(total, maxHal, hal);
  }

  function buildNavigasi(total, maxHal, hal) {
    paginasiWrap.innerHTML = '';
    if (total === 0) { infoHalaman.textContent = ''; return; }
    infoHalaman.textContent = 'Menampilkan ' + Math.min(PER, total) + ' dari ' + total + ' pegawai';

    var buatTombol = function (label, h, aktif, nonaktif) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.className = (aktif ? 'aktif' : '');
      if (nonaktif) b.disabled = true;
      b.addEventListener('click', function () {
        renderPaginasi._hal = h;
        renderPaginasi(cari.value.trim().toLowerCase());
      });
      return b;
    };

    paginasiWrap.appendChild(buatTombol('« Prev', hal - 1, false, hal <= 1));

    var awal = Math.max(1, hal - 2);
    var akhir = Math.min(maxHal, hal + 2);
    for (var h = awal; h <= akhir; h++) {
      paginasiWrap.appendChild(buatTombol(String(h), h, h === hal, false));
    }

    paginasiWrap.appendChild(buatTombol('Next »', hal + 1, false, hal >= maxHal));
  }

  if (cari) {
    cari.addEventListener('input', function () {
      renderPaginasi._hal = 1;
      renderPaginasi(cari.value.trim().toLowerCase());
    });
  }

  renderPaginasi(cari ? cari.value.trim().toLowerCase() : '');
})();
</script>
@endsection
