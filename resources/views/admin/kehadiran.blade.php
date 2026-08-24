@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kehadiran — {{ tgl_id($tanggal) }}</h2>
    <div class="flex items-center gap-3">
      <span class="badge badge-biru">{{ count($rows) }} catatan</span>
      <button type="button" id="tombol-peta" class="btn btn-navy btn-kecil">
        {!! ikon('peta', 14) !!} Peta Sebaran Absen
      </button>
      <button type="button" id="tombol-tambah" class="btn btn-hijau btn-kecil">
        + Tambah Absen
      </button>
    </div>
  </div>

  <form method="get" action="{{ url('admin/kehadiran') }}" class="bilah-alat">
    <input type="date" name="tanggal" value="{{ $tanggal }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama pegawai / NIP..." class="min-w-[180px]">
    <select name="unit">
      <option value="">Semua Unit</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="">Semua Status</option>
      <option value="tepat" {{ $fStatus === 'tepat' ? 'selected' : '' }}>Tepat Waktu</option>
      <option value="terlambat" {{ $fStatus === 'terlambat' ? 'selected' : '' }}>Tidak Tepat Waktu (Terlambat)</option>
    </select>
    {{-- <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="anomali" value="1" class="w-auto" {{ $hanyaAnomali ? 'checked' : '' }}>
      Hanya anomali GPS
    </label> --}}
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Pegawai</th><th>Foto</th><th>Shift</th>
          <th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th>
          <th>Jarak</th><th>Koordinat Masuk</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $r)
        <tr {{ $r->flag_anomali ? 'class="anomali-baris"' : '' }}>
          <td>
            <strong>{{ $r->user?->nama_lengkap }}</strong>
            <br><span class="teks-kecil teks-redup">{{ $r->user?->unitKerja?->nama ?? '—' }}@if(
              $r->user?->subUnit?->nama) — {{ $r->user->subUnit->nama }}@endif</span>
            @if($r->flag_anomali)
              <div class="catatan-anomali">{!! ikon('peringatan', 12) !!}
                {{ $r->catatan_anomali ?? 'Terindikasi anomali GPS' }}</div>
            @endif
          </td>
          <td>
            <div class="foto-mini">
              @if($r->foto_masuk)
                <a href="{{ url('foto/' . (int) $r->id . '/datang') }}" target="_blank"
                   rel="noopener" title="Selfie datang">
                  <img src="{{ url('foto/' . (int) $r->id . '/datang') }}" alt="Datang" loading="lazy"></a>
              @endif
              @if($r->foto_pulang)
                <a href="{{ url('foto/' . (int) $r->id . '/pulang') }}" target="_blank"
                   rel="noopener" title="Selfie pulang">
                  <img src="{{ url('foto/' . (int) $r->id . '/pulang') }}" alt="Pulang" loading="lazy"></a>
              @endif
              @if(! $r->foto_masuk && ! $r->foto_pulang)
                <span class="teks-redup teks-kecil">—</span>
              @endif
            </div>
          </td>
          <td>{{ label_shift($r->shiftHariIni) }}</td>
          <td class="angka">{{ jam_id($r->waktu_masuk) }}</td>
          <td class="angka">{{ jam_id($r->waktu_pulang) }}</td>
          <td>{!! badge_status(! $r->waktu_pulang ? 'Belum Pulang'
                 : ($r->status_masuk === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu'),
                 (int) $r->menit_terlambat) !!}</td>
          <td class="angka">{{ $r->logLokasiDatang?->jarak_meter !== null
                ? number_format((float) $r->logLokasiDatang->jarak_meter, 0, ',', '.') . ' m' : '—' }}</td>
          <td class="angka teks-kecil">
            @if($r->lat_masuk !== null)
              <a href="https://www.google.com/maps?q={{ $r->lat_masuk }},{{ $r->lng_masuk }}"
                 target="_blank" rel="noopener">
                {{ number_format((float) $r->lat_masuk, 5) }}, {{ number_format((float) $r->lng_masuk, 5) }}
              </a>
            @else —@endif
          </td>
          <td class="tengah whitespace-nowrap">
            <button type="button"
                    class="btn btn-garis btn-kecil btn-ubah-absen"
                    data-id="{{ (int) $r->id }}"
                    data-user="{{ (int) $r->user_id }}"
                    data-tanggal="{{ $r->tanggal->format('Y-m-d') }}"
                    data-masuk="{{ substr($r->waktu_masuk, 11, 5) }}"
                    data-pulang="{{ $r->waktu_pulang ? substr($r->waktu_pulang, 11, 5) : '' }}">Ubah</button>
            <form method="post" action="{{ url('admin/kehadiran/hapus') }}" class="inline-block"
                  onsubmit="return confirm('Hapus absensi {{ $r->user?->nama_lengkap }} pada tanggal ini?');">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $r->id }}">
              <button type="submit" class="btn btn-merah btn-kecil">Hapus</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $rows)
        <tr><td colspan="9" class="tengah teks-redup">Tidak ada catatan kehadiran pada tanggal ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@if($ditolak)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Percobaan Absen Ditolak (di luar radius)</h2>
    <span class="badge badge-merah">{{ count($ditolak) }}</span>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Jarak dari RSUD</th><th>Koordinat</th></tr></thead>
      <tbody>
        @foreach($ditolak as $d)
        <tr>
          <td class="angka">{{ jam_id($d->waktu) }}</td>
          <td>{{ $d->user?->nama_lengkap }}</td>
          <td>Absen {{ $d->tipe }}</td>
          <td class="angka">{{ number_format((float) $d->jarak_meter, 0, ',', '.') }} m</td>
          <td class="angka teks-kecil">
            <a href="https://www.google.com/maps?q={{ $d->latitude }},{{ $d->longitude }}"
               target="_blank" rel="noopener">
              {{ number_format((float) $d->latitude, 5) }}, {{ number_format((float) $d->longitude, 5) }}</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

{{-- Modal Tambah / Ubah Absensi --}}
<div id="modal-absen" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-lg max-h-[92vh] overflow-y-auto">
    <div class="kartu-kepala">
      <h2 id="modal-absen-judul">{!! ikon('kalender') !!} Tambah Absensi</h2>
      <button type="button" id="modal-absen-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <form method="post" action="{{ url('admin/kehadiran/simpan') }}" id="form-absen" class="px-3 pb-3 space-y-3">
      @csrf
      <input type="hidden" name="id" value="" id="absen-id">
      <label class="blok">
        <span class="teks-kecil">Pegawai</span>
        <select name="user_id" id="absen-user" required>
          <option value="">— Pilih Pegawai —</option>
          @foreach($pegawaiList as $p)
            <option value="{{ (int) $p->id }}">{{ $p->nama_lengkap }}</option>
          @endforeach
        </select>
      </label>

      {{-- Mode Ubah (satu hari) --}}
      <div id="bagian-tunggal" class="hidden space-y-3">
        <label class="blok">
          <span class="teks-kecil">Tanggal</span>
          <input type="date" name="tanggal" id="absen-tanggal">
        </label>
        <div class="grid grid-cols-2 gap-2">
          <label class="blok">
            <span class="teks-kecil">Jam Masuk</span>
            <input type="time" name="jam_masuk" id="absen-masuk">
          </label>
          <label class="blok">
            <span class="teks-kecil">Jam Pulang <em class="teks-redup">(kosong = belum pulang)</em></span>
            <input type="time" name="jam_pulang" id="absen-pulang">
          </label>
        </div>
      </div>

      {{-- Mode Tambah (per hari) --}}
      <div id="bagian-banyak" class="space-y-3">
        <div class="grid grid-cols-2 gap-2">
          <label class="blok">
            <span class="teks-kecil">Tanggal Mulai</span>
            <input type="date" id="rentang-mulai" value="{{ $tanggal }}">
          </label>
          <label class="blok">
            <span class="teks-kecil">Tanggal Selesai</span>
            <input type="date" id="rentang-selesai" value="{{ $tanggal }}">
          </label>
        </div>
        <div class="flex items-end gap-2">
          <label class="blok grow">
            <span class="teks-kecil">Jam Masuk</span>
            <input type="time" id="templat-masuk" value="08:00">
          </label>
          <label class="blok grow">
            <span class="teks-kecil">Jam Pulang</span>
            <input type="time" id="templat-pulang" value="16:00">
          </label>
          <button type="button" id="btn-terapkan-semua" class="btn btn-navy btn-kecil whitespace-nowrap">
            {!! ikon('centang', 14) !!} Isi Semua</button>
        </div>
        <div id="daftar-hari"
             class="max-h-64 overflow-y-auto space-y-1.5 border border-slate-200 rounded-xl p-2 bg-slate-50/60"></div>
        <p class="teks-kecil teks-redup m-0">Atur jam masuk &amp; pulang per tanggal di atas, atau gunakan &ldquo;Isi Semua&rdquo;.
          Baris dengan jam masuk kosong akan dilewati. Minggu/hari libur otomatis dikosongkan — isi manual bila pegawai tetap masuk.</p>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-garis" id="modal-absen-batal">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan</button>
      </div>
    </form>
  </section>
</div>

{{-- Modal Peta Sebaran Absen --}}
<div id="modal-peta-absen" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-4xl">
    <div class="kartu-kepala">
      <h2>{!! ikon('peta') !!} Peta Posisi Absensi</h2>
      <div class="flex items-center gap-3">
        <span class="teks-redup teks-kecil hidden md:inline">lingkaran = radius {{ number_format($radius, 0, ',', '.') }} m ·
          hijau = datang · navy = pulang · merah = anomali</span>
        <button type="button" id="modal-peta-tutup" class="btn btn-garis btn-kecil">&times;</button>
      </div>
    </div>
    <div class="px-3 pb-3">
      <div id="peta"></div>
      <div class="peta-kosong" id="peta-kosong" hidden>
        Peta tidak dapat dimuat (memerlukan koneksi internet untuk pustaka peta &amp; ubin peta).
        Gunakan tautan koordinat pada tabel di atas untuk membuka lokasi di Google Maps.
      </div>
    </div>
  </section>
</div>

@endsection

@section('script')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  var el     = document.getElementById('peta');
  var kosong = document.getElementById('peta-kosong');
  var tombol = document.getElementById('tombol-peta');
  var modal  = document.getElementById('modal-peta-absen');
  var tutup  = document.getElementById('modal-peta-tutup');
  var peta   = null;
  var sudahInit = false;

  // ---- Modal Tambah / Ubah Absensi ----
  var modalAbsen   = document.getElementById('modal-absen');
  var tombolTambah = document.getElementById('tombol-tambah');
  var tutupAbsen   = document.getElementById('modal-absen-tutup');
  var batalAbsen   = document.getElementById('modal-absen-batal');
  var judulAbsen   = document.getElementById('modal-absen-judul');
  var formAbsen    = document.getElementById('form-absen');

  var bagianTunggal  = document.getElementById('bagian-tunggal');
  var bagianBanyak   = document.getElementById('bagian-banyak');
  var rentangMulai   = document.getElementById('rentang-mulai');
  var rentangSelesai = document.getElementById('rentang-selesai');
  var daftarHari     = document.getElementById('daftar-hari');
  var templatMasuk   = document.getElementById('templat-masuk');
  var templatPulang  = document.getElementById('templat-pulang');
  var btnSemua       = document.getElementById('btn-terapkan-semua');

  var LIBUR_SET   = @json($hariLiburSet);
  var NAMA_HARI   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  var NAMA_BULAN  = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                     'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var KELAS_JAM   = 'w-full rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-xs';

  function bukaModalAbsen() {
    modalAbsen.classList.remove('hidden');
    modalAbsen.classList.add('flex');
  }
  function tutupModalAbsen() {
    modalAbsen.classList.add('hidden');
    modalAbsen.classList.remove('flex');
  }
  function toggleNonaktif(container, matikan) {
    Array.prototype.forEach.call(container.querySelectorAll('input, select'), function (el) {
      el.disabled = matikan;
    });
  }
  function isoLokal(d) {
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
  }
  function labelTanggal(iso) {
    var b = iso.split('-');
    var d = new Date(+b[0], +b[1] - 1, +b[2]);
    return NAMA_HARI[d.getDay()] + ', ' + (+b[2]) + ' ' + NAMA_BULAN[+b[1] - 1] + ' ' + b[0];
  }
  function buatBarisHari(i, iso) {
    var tgl = new Date(+iso.slice(0, 4), +iso.slice(5, 7) - 1, +iso.slice(8, 10));
    var minggu = tgl.getDay() === 0;
    var libur = !!LIBUR_SET[iso];
    var badge = (minggu || libur)
      ? ' <span class="badge badge-merah" style="font-size:.58rem;padding:1px 6px">' + (libur ? 'Libur' : 'Minggu') + '</span>'
      : '';
    var kosongkan = minggu || libur;

    var wrap = document.createElement('div');
    wrap.className = 'grid grid-cols-[minmax(0,1fr)_82px_82px] gap-1.5 items-center';
    wrap.innerHTML =
      '<div class="text-xs text-slate-700 truncate leading-tight">' + labelTanggal(iso) + badge + '</div>'
      + '<input type="hidden" name="hari[' + i + '][tanggal]" value="' + iso + '">'
      + '<input type="time" name="hari[' + i + '][masuk]" value="' + (kosongkan ? '' : '08:00') + '"'
      + ' class="' + KELAS_JAM + '" aria-label="Jam masuk ' + iso + '">'
      + '<input type="time" name="hari[' + i + '][pulang]" value="' + (kosongkan ? '' : '16:00') + '"'
      + ' class="' + KELAS_JAM + '" aria-label="Jam pulang ' + iso + '">';
    return wrap;
  }
  function regenDaftarHari() {
    daftarHari.innerHTML = '';
    var m = rentangMulai.value;
    var s = rentangSelesai.value;

    if (!m || !s || s < m) {
      daftarHari.innerHTML = '<p class="teks-kecil teks-redup m-0 tengah">'
        + (!m || !s ? 'Pilih tanggal mulai &amp; selesai.' : 'Tanggal selesai lebih awal dari tanggal mulai.')
        + '</p>';
      return;
    }

    var kursor = new Date(m + 'T00:00:00');
    var akhir  = new Date(s + 'T00:00:00');
    var i = 0;
    while (kursor <= akhir && i < 31) {
      var iso = isoLokal(kursor);
      daftarHari.appendChild(buatBarisHari(i, iso));
      i++;
      kursor.setDate(kursor.getDate() + 1);
    }
    if (kursor <= akhir) {
      daftarHari.insertAdjacentHTML('beforeend',
        '<p class="teks-kecil teks-amber m-0">Maksimal 31 hari per pengisian.</p>');
    }
  }

  function modeTambah() {
    judulAbsen.innerHTML = '{!! ikon('kalender') !!} Tambah Absensi';
    formAbsen.reset();
    document.getElementById('absen-id').value = '';
    bagianTunggal.classList.add('hidden');
    toggleNonaktif(bagianTunggal, true);
    bagianBanyak.classList.remove('hidden');
    rentangMulai.value = @json($tanggal);
    rentangSelesai.value = @json($tanggal);
    regenDaftarHari();
    bukaModalAbsen();
  }
  function modeUbah(btn) {
    judulAbsen.innerHTML = '{!! ikon('kalender') !!} Ubah Absensi';
    formAbsen.reset();
    document.getElementById('absen-id').value = btn.dataset.id;
    document.getElementById('absen-user').value = btn.dataset.user;
    document.getElementById('absen-tanggal').value = btn.dataset.tanggal;
    document.getElementById('absen-masuk').value = btn.dataset.masuk;
    document.getElementById('absen-pulang').value = btn.dataset.pulang;
    bagianBanyak.classList.add('hidden');
    daftarHari.innerHTML = '';
    bagianTunggal.classList.remove('hidden');
    toggleNonaktif(bagianTunggal, false);
    bukaModalAbsen();
  }

  if (tombolTambah) tombolTambah.addEventListener('click', modeTambah);
  if (tutupAbsen) tutupAbsen.addEventListener('click', tutupModalAbsen);
  if (batalAbsen) batalAbsen.addEventListener('click', tutupModalAbsen);
  if (rentangMulai) rentangMulai.addEventListener('change', regenDaftarHari);
  if (rentangSelesai) rentangSelesai.addEventListener('change', regenDaftarHari);
  if (btnSemua) {
    btnSemua.addEventListener('click', function () {
      Array.prototype.forEach.call(daftarHari.children, function (baris) {
        var jam = baris.querySelectorAll('input[type="time"]');
        if (jam.length === 2) {
          jam[0].value = templatMasuk.value;
          jam[1].value = templatPulang.value;
        }
      });
    });
  }
  if (modalAbsen) {
    modalAbsen.addEventListener('click', function (e) {
      if (e.target === modalAbsen) tutupModalAbsen();
    });
  }
  Array.prototype.forEach.call(document.querySelectorAll('.btn-ubah-absen'), function (btn) {
    btn.addEventListener('click', function () { modeUbah(btn); });
  });

  // ---- Peta Sebaran Absen ----
  function initPeta() {
    if (typeof window.L === 'undefined') {
      el.hidden = true;
      kosong.hidden = false;
      return false;
    }
    var rs     = [@json($rsLat), @json($rsLng)];
    var radius = @json($radius);
    var titik  = @json($titik);

    peta = L.map(el).setView(rs, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(peta);

    L.circle(rs, { radius: radius, color: '#1568B8', fillColor: '#1568B8', fillOpacity: .08, weight: 1.5 }).addTo(peta);
    L.marker(rs).addTo(peta).bindPopup('<strong>Titik RSUD Merauke</strong>');

    function esk(s) {
      var d = document.createElement('div');
      d.textContent = String(s == null ? '' : s);
      return d.innerHTML;
    }

    var batas = [rs];
    titik.forEach(function (t) {
      var warna = t.anomali ? '#B3312D' : (t.tipe === 'Datang' ? '#178A50' : '#0B3B66');
      L.circleMarker([t.lat, t.lng], {
        radius: 7, color: warna, fillColor: warna, fillOpacity: .85, weight: 1.5
      }).addTo(peta).bindPopup(
        '<strong>' + esk(t.nama) + '</strong><br>Absen ' + esk(t.tipe) + ' · pukul ' + esk(t.jam)
        + (t.anomali ? '<br><em>⚠ terindikasi anomali GPS</em>' : '')
      );
      batas.push([t.lat, t.lng]);
    });
    if (batas.length > 1) {
      peta.fitBounds(batas, { padding: [30, 30], maxZoom: 17 });
    }
    return true;
  }

  function bukaPeta() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (! sudahInit) {
      sudahInit = initPeta();
      if (! sudahInit) return;
    }
    setTimeout(function () { if (peta) peta.invalidateSize(); }, 60);
  }

  function tutupPeta() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  tombol.addEventListener('click', bukaPeta);
  tutup.addEventListener('click', tutupPeta);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) tutupPeta();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (! modal.classList.contains('hidden')) tutupPeta();
      if (modalAbsen && ! modalAbsen.classList.contains('hidden')) tutupModalAbsen();
    }
  });
})();
</script>
@endsection
