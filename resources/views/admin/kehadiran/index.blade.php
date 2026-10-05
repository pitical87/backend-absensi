@extends('layouts.admin')

@section('content')

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-2 mb-4" id="baris-tab-kehadiran" role="tablist">
  <button type="button" role="tab" aria-controls="panel-kehadiran"
          class="tab-kehadiran rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors cursor-pointer bg-white text-slate-900 shadow-sm"
          data-tab="kehadiran">Data Kehadiran</button>
  <button type="button" role="tab" aria-controls="panel-percobaan"
          class="tab-kehadiran rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="percobaan">Data Percobaan Absen Ditolak</button>
</div>

<section class="kartu" id="panel-kehadiran" role="tabpanel">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kehadiran — <span id="label-tanggal">{{ tgl_id($tanggal) }}</span></h2>
    <div class="flex items-center gap-3">
      <span class="badge badge-biru"><span id="total-catatan">{{ $total }}</span> catatan</span>
      <button type="button" id="tombol-peta" class="btn btn-navy btn-kecil">
        {!! ikon('peta', 14) !!} Peta Sebaran Absen
      </button>
      <button type="button" id="tombol-tambah" class="btn btn-primer btn-kecil">
        {!! ikon('tambah', 14) !!} Tambah Absen
      </button>
    </div>
  </div>

  <form method="get" action="{{ route('admin.kehadiran.index') }}" class="bilah-alat" id="form-filter-kehadiran">
    <input type="date" name="tanggal" id="input-tanggal" value="{{ $tanggal }}">
    <input type="text" name="q" id="input-q" value="{{ $q }}" autocomplete="off"
           placeholder="Cari nama pegawai / NIP..." class="min-w-[180px]">
    <select name="unit" id="input-unit">
      <option value="">Semua Unit</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" @selected($fUnit === (int) $uk->id)>{{ $uk->nama }}</option>
      @endforeach
    </select>
    <select name="status" id="input-status">
      <option value="">Semua Status</option>
      <option value="tepat" @selected($fStatus === 'tepat')>Tepat Waktu</option>
      <option value="terlambat" @selected($fStatus === 'terlambat')>Tidak Tepat Waktu (Terlambat)</option>
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
    <a href="{{ route('admin.kehadiran.index') }}" class="btn btn-garis btn-kecil">Reset</a>
  </form>

  <div id="pesan-kehadiran" class="hidden mb-3 px-4 py-3 rounded-xl text-sm"></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Pegawai</th><th>Foto</th><th>Shift</th>
          <th>Jam Absen</th><th>Status</th>
          <th>Jarak</th><th>Koordinat Masuk</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-kehadiran">
        @include('admin.kehadiran.rows', ['rows' => $rows])
      </tbody>
    </table>
  </div>
  <p class="teks-kecil teks-redup mt-2 mb-0" id="catatan-batas" @if($total <= 200) hidden @endif>
    Menampilkan {{ count($rows) }} dari {{ $total }} catatan. Persempit dengan filter tanggal, pencarian, unit, atau status.
  </p>
</section>

<section class="kartu hidden" id="panel-percobaan" role="tabpanel">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Percobaan Absen Ditolak (di luar radius)</h2>
    <span class="badge badge-merah"><span id="total-percobaan">{{ $ditolak->count() }}</span> percobaan</span>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Jarak dari Titik Absen</th><th>Koordinat</th></tr>
      </thead>
      <tbody id="tbody-percobaan">
        @include('admin.kehadiran.percobaan_rows', ['ditolak' => $ditolak])
      </tbody>
    </table>
  </div>
</section>

{{-- ===== MODAL TAMBAH / UBAH ABSENSI ===== --}}
<div id="modal-absen" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-lg max-h-[92vh] overflow-y-auto">
    <div class="kartu-kepala">
      <h2 id="modal-absen-judul">{!! ikon('kalender') !!} Tambah Absensi</h2>
      <button type="button" id="modal-absen-tutup" class="btn btn-garis btn-kecil" aria-label="Tutup">&times;</button>
    </div>
    <form method="post" action="{{ route('admin.kehadiran.simpan') }}" id="form-absen" class="px-3 pb-3 space-y-3">
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

      {{-- Mode Tambah (per tanggal) --}}
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
            {!! ikon('centang', 14) !!} Isi Semua
          </button>
        </div>
        <div id="daftar-hari"
             class="max-h-64 overflow-y-auto space-y-1.5 border border-slate-200 rounded-xl p-2 bg-slate-50/60"></div>
        <p class="teks-kecil teks-redup m-0">Atur jam masuk &amp; pulang per tanggal di atas, atau gunakan
          &ldquo;Isi Semua&rdquo;. Baris dengan jam masuk kosong akan dilewati. Minggu/hari libur otomatis
          dikosongkan — isi manual bila pegawai tetap masuk.</p>
      </div>

      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-garis" id="modal-absen-batal">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan</button>
      </div>
    </form>
  </section>
</div>

{{-- ===== MODAL PETA SEBARAN ABSEN ===== --}}
<div id="modal-peta-absen" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-4xl">
    <div class="kartu-kepala">
      <h2>{!! ikon('peta') !!} Peta Posisi Absensi</h2>
      <div class="flex items-center gap-3">
        <span class="teks-redup teks-kecil hidden md:inline">lingkaran = radius
          {{ number_format($radius, 0, ',', '.') }} m · hijau = datang · navy = pulang · merah = anomali</span>
        <button type="button" id="modal-peta-tutup" class="btn btn-garis btn-kecil" aria-label="Tutup">&times;</button>
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

{{-- ===== MODAL DETAIL ANOMALI ===== --}}
<div id="modal-anomali" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-lg">
    <div class="kartu-kepala">
      <h2>{!! ikon('peringatan') !!} Detail Anomali</h2>
      <button type="button" id="modal-anomali-tutup" class="btn btn-garis btn-kecil" aria-label="Tutup">&times;</button>
    </div>
    <div class="px-3 pb-3 space-y-3">
      <div>
        <span class="teks-kecil teks-redup">Pegawai</span>
        <div class="font-semibold" id="anomali-nama">—</div>
      </div>
      <div>
        <span class="teks-kecil teks-redup">Catatan Anomali</span>
        <div class="catatan-anomali mt-1" id="anomali-keterangan">—</div>
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
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  var urlData      = @json(route('admin.kehadiran.data'));
  var urlPercobaan = @json(route('admin.kehadiran.percobaan'));
  var rsPusat      = [@json($rsLat), @json($rsLng)];
  var radiusPusat  = @json($radius);
  var titikAwal    = @json($titik);

  var namaHari  = @json(HARI_ID);
  var namaBulan = @json(array_values(BULAN_ID));
  var liburSet  = @json($hariLiburSet);

  var inputTanggal = document.getElementById('input-tanggal');
  var inputQ       = document.getElementById('input-q');
  var inputUnit    = document.getElementById('input-unit');
  var inputStatus  = document.getElementById('input-status');
  var formFilter   = document.getElementById('form-filter-kehadiran');
  var pesan        = document.getElementById('pesan-kehadiran');
  var tbody        = document.getElementById('tbody-kehadiran');
  var totalCatatan = document.getElementById('total-catatan');
  var labelTanggalEl = document.getElementById('label-tanggal');
  var catatanBatas = document.getElementById('catatan-batas');
  var tombolPeta   = document.getElementById('tombol-peta');
  var tabButtons   = document.querySelectorAll('.tab-kehadiran');

  var KUNCI_TAB = 'tabKehadiran';
  var tabAktif  = localStorage.getItem(KUNCI_TAB) === 'percobaan' ? 'percobaan' : 'kehadiran';
  var titikPeta = titikAwal;
  var jedaCari  = null;

  /* ---------- Tab ---------- */

  function pasangTab() {
    Array.prototype.forEach.call(tabButtons, function (tombol) {
      var aktif = tombol.dataset.tab === tabAktif;
      tombol.classList.toggle('bg-white', aktif);
      tombol.classList.toggle('text-slate-900', aktif);
      tombol.classList.toggle('shadow-sm', aktif);
      tombol.classList.toggle('text-slate-500', !aktif);
      tombol.setAttribute('aria-selected', aktif ? 'true' : 'false');
    });

    document.getElementById('panel-kehadiran').classList.toggle('hidden', tabAktif !== 'kehadiran');
    document.getElementById('panel-percobaan').classList.toggle('hidden', tabAktif !== 'percobaan');
  }

  Array.prototype.forEach.call(tabButtons, function (tombol) {
    tombol.addEventListener('click', function () {
      tabAktif = tombol.dataset.tab;
      localStorage.setItem(KUNCI_TAB, tabAktif);
      pasangTab();
      if (tabAktif === 'percobaan') muatPercobaan();
    });
  });

  /* ---------- Pesan ---------- */

  function tampilPesan(teks, berhasil) {
    if (! teks) {
      pesan.classList.add('hidden');
      pesan.textContent = '';
      return;
    }
    pesan.className = 'mb-3 px-4 py-3 rounded-xl text-sm ' + (berhasil
      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
      : 'bg-rose-50 text-rose-700 border border-rose-200');
    pesan.textContent = teks;
  }

  /* ---------- Muat data ---------- */

  function parameterFilter() {
    var params = new URLSearchParams();
    params.set('tanggal', inputTanggal.value);
    if (inputQ.value.trim()) params.set('q', inputQ.value.trim());
    if (inputUnit.value) params.set('unit', inputUnit.value);
    if (inputStatus.value) params.set('status', inputStatus.value);
    params.set('_', Date.now());
    return params;
  }

  function muatKehadiran() {
    tampilPesan('Memuat data…', true);

    fetch(urlData + '?' + parameterFilter().toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { tampilPesan('Gagal memuat data kehadiran.', false); return; }
        tbody.innerHTML = h.html;
        totalCatatan.textContent = h.total;
        labelTanggalEl.textContent = h.labelTanggal || labelTanggalEl.textContent;
        catatanBatas.hidden = h.total <= h.jumlah;
        titikPeta = h.titik || [];
        if (peta) gambarTitik();
        tampilPesan('', true);
      })
      .catch(function () { tampilPesan('Terjadi kesalahan jaringan saat memuat kehadiran.', false); });
  }

  function muatPercobaan() {
    var params = new URLSearchParams({ tanggal: inputTanggal.value, _: String(Date.now()) });
    var kolom = document.getElementById('tbody-percobaan');

    fetch(urlPercobaan + '?' + params.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) return;
        kolom.innerHTML = h.html;
        document.getElementById('total-percobaan').textContent = h.total;
      })
      .catch(function () { /* panel sekunder: biarkan tabel terakhir tetap tampil */ });
  }

  function muatSemua() {
    muatKehadiran();
    if (tabAktif === 'percobaan') muatPercobaan();
  }

  formFilter.addEventListener('submit', function (e) {
    e.preventDefault();
    muatSemua();
  });

  inputQ.addEventListener('input', function () {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(muatSemua, 350);
  });

  inputQ.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); window.clearTimeout(jedaCari); muatSemua(); }
  });

  [inputTanggal, inputUnit, inputStatus].forEach(function (el) {
    el.addEventListener('change', muatSemua);
  });

  /* ---------- Delegasi baris: Ubah, Hapus, detail anomali ---------- */

  document.addEventListener('click', function (e) {
    var ubah = e.target.closest('.btn-ubah-absen');
    if (ubah) { modeUbah(ubah); return; }

    var anomali = e.target.closest('[data-anomali-nama]');
    if (anomali) { bukaAnomali(anomali); }
  });

  // Form Hapus tetap POST biasa supaya tetap jalan tanpa JavaScript; di sini
  // hanya diantar lewat fetch lalu tabel dimuat ulang.
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-hapus]');
    if (! form) return;

    var tombol = e.submitter;
    var konfirmasi = tombol && tombol.dataset ? tombol.dataset.konfirmasi : null;
    if (konfirmasi && ! window.confirm(konfirmasi)) { e.preventDefault(); return; }

    e.preventDefault();
    tampilPesan('Menghapus…', true);

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json().then(function (h) { return { ok: r.ok, h: h }; }); })
      .then(function (b) {
        tampilPesan(b.h.pesan || (b.ok ? 'Data absensi dihapus.' : 'Gagal menghapus absensi.'), b.ok);
        muatSemua();
      })
      .catch(function () { tampilPesan('Gagal menghapus absensi.', false); });
  });

  /* ---------- Modal Tambah / Ubah Absensi ---------- */

  var modalAbsen      = document.getElementById('modal-absen');
  var tombolTambah    = document.getElementById('tombol-tambah');
  var tutupAbsen      = document.getElementById('modal-absen-tutup');
  var batalAbsen      = document.getElementById('modal-absen-batal');
  var judulAbsen      = document.getElementById('modal-absen-judul');
  var formAbsen       = document.getElementById('form-absen');
  var bagianTunggal   = document.getElementById('bagian-tunggal');
  var bagianBanyak    = document.getElementById('bagian-banyak');
  var rentangMulai    = document.getElementById('rentang-mulai');
  var rentangSelesai  = document.getElementById('rentang-selesai');
  var daftarHari      = document.getElementById('daftar-hari');
  var templatMasuk    = document.getElementById('templat-masuk');
  var templatPulang   = document.getElementById('templat-pulang');
  var btnSemua        = document.getElementById('btn-terapkan-semua');
  var kelasJam        = 'w-full rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-xs';

  function tampilkan(el, buka) {
    el.classList.toggle('hidden', !buka);
    el.classList.toggle('flex', buka);
  }

  function toggleNonaktif(wadah, matikan) {
    Array.prototype.forEach.call(wadah.querySelectorAll('input, select'), function (el) {
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
    return namaHari[d.getDay()] + ', ' + (+b[2]) + ' ' + namaBulan[+b[1] - 1] + ' ' + b[0];
  }

  function barisHari(i, iso) {
    var tgl = new Date(+iso.slice(0, 4), +iso.slice(5, 7) - 1, +iso.slice(8, 10));
    var minggu = tgl.getDay() === 0;
    var libur = !!liburSet[iso];
    var kosongkan = minggu || libur;
    var badge = kosongkan
      ? ' <span class="badge badge-merah" style="font-size:.58rem;padding:1px 6px">'
        + (libur ? 'Libur' : 'Minggu') + '</span>'
      : '';

    var wrap = document.createElement('div');
    wrap.className = 'grid grid-cols-[minmax(0,1fr)_82px_82px] gap-1.5 items-center';
    wrap.innerHTML =
      '<div class="text-xs text-slate-700 truncate leading-tight">' + labelTanggal(iso) + badge + '</div>'
      + '<input type="hidden" name="hari[' + i + '][tanggal]" value="' + iso + '">'
      + '<input type="time" name="hari[' + i + '][masuk]" value="' + (kosongkan ? '' : '08:00')
      + '" class="' + kelasJam + '" aria-label="Jam masuk ' + iso + '">'
      + '<input type="time" name="hari[' + i + '][pulang]" value="' + (kosongkan ? '' : '16:00')
      + '" class="' + kelasJam + '" aria-label="Jam pulang ' + iso + '">';
    return wrap;
  }

  function regenDaftarHari() {
    daftarHari.innerHTML = '';
    var mulai = rentangMulai.value;
    var selesai = rentangSelesai.value;

    if (! mulai || ! selesai || selesai < mulai) {
      daftarHari.innerHTML = '<p class="teks-kecil teks-redup m-0 tengah">'
        + (! mulai || ! selesai ? 'Pilih tanggal mulai &amp; selesai.' : 'Tanggal selesai lebih awal dari tanggal mulai.')
        + '</p>';
      return;
    }

    var kursor = new Date(mulai + 'T00:00:00');
    var akhir = new Date(selesai + 'T00:00:00');
    var i = 0;
    while (kursor <= akhir && i < 31) {
      daftarHari.appendChild(barisHari(i, isoLokal(kursor)));
      i++;
      kursor.setDate(kursor.getDate() + 1);
    }
    if (kursor <= akhir) {
      daftarHari.insertAdjacentHTML('beforeend',
        '<p class="teks-kecil teks-amber m-0">Maksimal 31 hari per pengisian.</p>');
    }
  }

  function modeTambah() {
    judulAbsen.innerHTML = @json(ikon('kalender')) + ' Tambah Absensi';
    formAbsen.reset();
    document.getElementById('absen-id').value = '';
    bagianTunggal.classList.add('hidden');
    toggleNonaktif(bagianTunggal, true);
    bagianBanyak.classList.remove('hidden');
    rentangMulai.value = inputTanggal.value;
    rentangSelesai.value = inputTanggal.value;
    regenDaftarHari();
    tampilkan(modalAbsen, true);
  }

  function modeUbah(tombol) {
    judulAbsen.innerHTML = @json(ikon('kalender')) + ' Ubah Absensi';
    formAbsen.reset();
    document.getElementById('absen-id').value = tombol.dataset.id;
    document.getElementById('absen-user').value = tombol.dataset.user;
    document.getElementById('absen-tanggal').value = tombol.dataset.tanggal;
    document.getElementById('absen-masuk').value = tombol.dataset.masuk || '';
    document.getElementById('absen-pulang').value = tombol.dataset.pulang || '';
    bagianBanyak.classList.add('hidden');
    daftarHari.innerHTML = '';
    bagianTunggal.classList.remove('hidden');
    toggleNonaktif(bagianTunggal, false);
    tampilkan(modalAbsen, true);
  }

  formAbsen.addEventListener('submit', function () {
    tampilPesan('Menyimpan…', true);
  });

  tombolTambah.addEventListener('click', modeTambah);
  tutupAbsen.addEventListener('click', function () { tampilkan(modalAbsen, false); });
  batalAbsen.addEventListener('click', function () { tampilkan(modalAbsen, false); });
  rentangMulai.addEventListener('change', regenDaftarHari);
  rentangSelesai.addEventListener('change', regenDaftarHari);
  btnSemua.addEventListener('click', function () {
    Array.prototype.forEach.call(daftarHari.children, function (baris) {
      var jam = baris.querySelectorAll('input[type="time"]');
      if (jam.length !== 2) return;
      jam[0].value = templatMasuk.value;
      jam[1].value = templatPulang.value;
    });
  });
  modalAbsen.addEventListener('click', function (e) {
    if (e.target === modalAbsen) tampilkan(modalAbsen, false);
  });

  /* ---------- Modal Peta ---------- */

  var modalPeta = document.getElementById('modal-peta-absen');
  var elemenPeta = document.getElementById('peta');
  var petaKosong = document.getElementById('peta-kosong');
  var peta = null;
  var layerTitik = null;

  function gambarTitik() {
    if (! peta) return;
    if (layerTitik) peta.removeLayer(layerTitik);
    layerTitik = L.layerGroup().addTo(peta);

    L.circle(rsPusat, {
      radius: radiusPusat, color: '#1568B8', fillColor: '#1568B8', fillOpacity: .08, weight: 1.5
    }).addTo(layerTitik);
    L.marker(rsPusat).addTo(layerTitik).bindPopup('<strong>Titik RSUD Merauke</strong>');

    var batas = [rsPusat];
    titikPeta.forEach(function (t) {
      if (t.lat === null || t.lng === null) return;
      var warna = t.anomali ? '#B3312D' : (t.tipe === 'Datang' ? '#178A50' : '#0B3B66');
      var teks = '<strong>' + escapeHtml(t.nama) + '</strong><br>Absen ' + escapeHtml(t.tipe)
        + ' · pukul ' + escapeHtml(t.jam)
        + (t.anomali ? '<br><em>⚠ terindikasi anomali GPS</em>' : '');
      L.circleMarker([t.lat, t.lng], {
        radius: 7, color: warna, fillColor: warna, fillOpacity: .85, weight: 1.5
      }).addTo(layerTitik).bindPopup(teks);
      batas.push([t.lat, t.lng]);
    });

    if (batas.length > 1) peta.fitBounds(batas, { padding: [30, 30], maxZoom: 17 });
  }

  function escapeHtml(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function bukaPeta() {
    if (typeof window.L === 'undefined') {
      elemenPeta.hidden = true;
      petaKosong.hidden = false;
      return;
    }
    if (! peta) {
      peta = L.map(elemenPeta).setView(rsPusat, 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
      }).addTo(peta);
    }
    gambarTitik();
    tampilkan(modalPeta, true);
    window.setTimeout(function () { if (peta) peta.invalidateSize(); }, 60);
  }

  tombolPeta.addEventListener('click', bukaPeta);
  document.getElementById('modal-peta-tutup').addEventListener('click', function () {
    tampilkan(modalPeta, false);
  });
  modalPeta.addEventListener('click', function (e) {
    if (e.target === modalPeta) tampilkan(modalPeta, false);
  });

  /* ---------- Modal Anomali ---------- */

  var modalAnomali = document.getElementById('modal-anomali');

  function bukaAnomali(tombol) {
    document.getElementById('anomali-nama').textContent = tombol.dataset.anomaliNama || '—';
    document.getElementById('anomali-keterangan').textContent = tombol.dataset.anomaliKeterangan || '—';
    tampilkan(modalAnomali, true);
  }

  document.getElementById('modal-anomali-tutup').addEventListener('click', function () {
    tampilkan(modalAnomali, false);
  });
  modalAnomali.addEventListener('click', function (e) {
    if (e.target === modalAnomali) tampilkan(modalAnomali, false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    [modalAbsen, modalPeta, modalAnomali].forEach(function (modal) {
      if (! modal.classList.contains('hidden')) tampilkan(modal, false);
    });
  });

  /* ---------- Mulai ---------- */

  // Kedua panel sudah dirender server, jadi tidak perlu muat data lagi.
  pasangTab();
})();
</script>
@endsection