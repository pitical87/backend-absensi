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
    <h2>{!! ikon('log') !!} Mapping ID Finger</h2>
    <div class="flex items-center gap-2">
      <span class="badge badge-biru" id="jumlah-mapping">{{ count($mapping) }} mapping</span>
      <button type="button" id="tombol-tambah-mapping" class="btn btn-navy btn-kecil">{!! ikon('tambah', 14) !!} Tambah</button>
    </div>
  </div>

  <div class="p-3">
    <input type="text" id="cari-mapping" placeholder="Cari pegawai / ID finger…" class="flex-1 min-w-[200px]">
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Pegawai</th><th>ID Finger</th><th class="min-w-[110px]">Aksi</th></tr></thead>
      <tbody id="mapping-tubuh">
        @foreach($mapping as $m)
        <tr data-nama="{{ mb_strtolower($m->user?->nama_lengkap ?? '') }}" data-finger="{{ mb_strtolower($m->finger_id) }}">
          <td>
            <strong>{{ $m->user?->nama_lengkap ?? '—' }}</strong>
            @if($m->user?->nip)<br><span class="teks-redup teks-kecil">NIP {{ $m->user->nip }}</span>@endif
          </td>
          <td><code>{{ $m->finger_id }}</code></td>
          <td class="flex flex-wrap gap-1">
              <button type="button" class="btn btn-garis btn-kecil ubah-mapping"
                data-id="{{ $m->id }}" data-user="{{ $m->user_id }}"
                data-finger="{{ $m->finger_id }}" data-nama="{{ $m->user?->nama_lengkap }}">Ubah</button>
              <form method="post" action="{{ route('admin.finger.mapping.hapus') }}" onsubmit="return confirm('Hapus mapping ini?')">
                @csrf
                <input type="hidden" name="id" value="{{ $m->id }}">
                <button type="submit" class="btn btn-merah btn-kecil">Hapus</button>
              </form>
          </td>
        </tr>
        @endforeach
        @if($mapping->isEmpty())
        <tr><td colspan="3" class="tengah teks-redup">Belum ada mapping ID finger.</td></tr>
        @endif
        <tr id="mapping-kosong" class="hidden"><td colspan="3" class="tengah teks-redup">Tidak ada mapping yang cocok.</td></tr>
      </tbody>
    </table>
  </div>
</section>

{{-- Modal Tambah Mapping --}}
<div id="modal-tambah-mapping" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-lg">
    <div class="kartu-kepala">
      <h2>{!! ikon('tambah') !!} Tambah Mapping ID Finger</h2>
      <button type="button" class="btn btn-garis btn-kecil tutup-modal-tambah">&times; Tutup</button>
    </div>
    <form method="post" action="{{ route('admin.finger.mapping.simpan') }}" class="p-4 space-y-2">
      @csrf
      <input type="hidden" name="id" value="">
      <label class="blok">
        <span class="teks-kecil">Pegawai</span>
        <select name="user_id" required>
          <option value="">— Pilih Pegawai —</option>
          @foreach($pegawaiTanpaMapping as $p)
            <option value="{{ $p->id }}">{{ $p->nama_lengkap }}@if($p->nip) (NIP {{ $p->nip }})@endif</option>
          @endforeach
        </select>
      </label>
      <label class="blok">
        <span class="teks-kecil">ID Finger di Mesin</span>
        <input type="text" name="finger_id" placeholder="cth. 101" required>
      </label>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-garis btn-kecil tutup-modal-tambah">Batal</button>
        <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit Mapping --}}
<div id="modal-edit-mapping" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-lg">
    <div class="kartu-kepala">
      <h2>{!! ikon('atur') !!} Ubah Mapping ID Finger</h2>
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
        <button type="submit" class="btn btn-navy btn-kecil">Perbarui</button>
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
  const modalTambah = document.getElementById('modal-tambah-mapping');
  const modalEdit   = document.getElementById('modal-edit-mapping');

  function bukaModal(el) { el.classList.remove('hidden'); el.classList.add('flex'); }
  function tutupModal(el) { el.classList.add('hidden'); el.classList.remove('flex'); }

  const tombolTambah = document.getElementById('tombol-tambah-mapping');
  if (tombolTambah) {
    tombolTambah.addEventListener('click', function () {
      modalTambah.querySelector('form').reset();
      modalTambah.querySelector('input[name="id"]').value = '';
      bukaModal(modalTambah);
    });
  }

  document.querySelectorAll('.tutup-modal-tambah').forEach(function (b) {
    b.addEventListener('click', function () { tutupModal(modalTambah); });
  });
  document.querySelectorAll('.tutup-modal-edit').forEach(function (b) {
    b.addEventListener('click', function () { tutupModal(modalEdit); });
  });

  if (modalTambah) modalTambah.addEventListener('click', function (e) { if (e.target === modalTambah) tutupModal(modalTambah); });
  if (modalEdit) modalEdit.addEventListener('click', function (e) { if (e.target === modalEdit) tutupModal(modalEdit); });

  document.querySelectorAll('.ubah-mapping').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('edit-mapping-id').value = btn.dataset.id;
      document.getElementById('edit-mapping-user').value = btn.dataset.user;
      document.getElementById('edit-mapping-finger').value = btn.dataset.finger;
      document.getElementById('edit-mapping-nama').value = btn.dataset.nama || '';
      bukaModal(modalEdit);
    });
  });

  // ── Cari mapping ──
  var cari = document.getElementById('cari-mapping');
  var tubuh = document.getElementById('mapping-tubuh');
  var kosong = document.getElementById('mapping-kosong');
  var jumlahLabel = document.getElementById('jumlah-mapping');
  if (cari && tubuh) {
    var jumlahAwal = tubuh.querySelectorAll('tr[data-nama]').length;
    cari.addEventListener('input', function () {
      var q = cari.value.trim().toLowerCase();
      var tampil = 0;
      tubuh.querySelectorAll('tr[data-nama]').forEach(function (tr) {
        var cocok = tr.dataset.nama.includes(q) || tr.dataset.finger.includes(q);
        tr.classList.toggle('hidden', !cocok);
        if (cocok) tampil++;
      });
      kosong.classList.toggle('hidden', q === '' || tampil > 0);
      if (jumlahLabel) {
        jumlahLabel.textContent = (q === '' ? jumlahAwal : tampil) + ' mapping';
      }
    });
  }
})();
</script>
@endsection
