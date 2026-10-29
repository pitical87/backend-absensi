@extends('layouts.admin')

@section('content')

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-2 mb-4" id="baris-tab-kehadiran" role="tablist">
  <button type="button" role="tab" aria-controls="panel-kehadiran"
          class="tab-kehadiran rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors cursor-pointer bg-white text-slate-900 shadow-sm"
          data-tab="kehadiran">Data Kehadiran</button>
  <button type="button" role="tab" aria-controls="panel-percobaan"
          class="tab-kehadiran rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="percobaan">Data Percobaan Absen Ditolak</button>
  <button type="button" role="tab" aria-controls="panel-mencurigakan"
          class="tab-kehadiran rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="mencurigakan">Absen Mencurigakan</button>
</div>

<section class="kartu" id="panel-kehadiran" role="tabpanel">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kehadiran — <span id="label-tanggal">{{ tgl_id($tanggal) }}</span></h2>
    <div class="flex items-center gap-3">
      <span class="badge badge-biru"><span id="total-catatan">…</span> catatan</span>
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
      <tbody id="tbody-kehadiran" aria-busy="true" aria-live="polite">
        @for($i = 0; $i < 6; $i++)
          <tr>
            <td><div class="skel h-3.5 w-44"></div><div class="skel h-2.5 w-24 mt-1.5"></div></td>
            <td><div class="skel h-8 w-12"></div></td>
            <td><div class="skel h-3.5 w-20"></div></td>
            <td><div class="skel h-3.5 w-16"></div></td>
            <td><div class="skel h-4 w-20 rounded-full"></div></td>
            <td><div class="skel h-3.5 w-10"></div></td>
            <td><div class="skel h-3.5 w-28"></div></td>
            <td><div class="skel h-6 w-20"></div></td>
          </tr>
        @endfor
      </tbody>
    </table>
  </div>
  <div id="paginasi-kehadiran"></div>
</section>

<section class="kartu hidden" id="panel-percobaan" role="tabpanel">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Percobaan Absen Ditolak (di luar radius)</h2>
    <span class="badge badge-merah"><span id="total-percobaan">…</span> percobaan</span>
  </div>



  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Jarak dari Titik Absen</th><th>Koordinat</th></tr>
      </thead>
      <tbody id="tbody-percobaan" aria-busy="true">
        @for($i = 0; $i < 4; $i++)
          <tr>
            <td><div class="skel h-3.5 w-24"></div></td>
            <td><div class="skel h-3.5 w-40"></div></td>
            <td><div class="skel h-3.5 w-24"></div></td>
            <td><div class="skel h-3.5 w-12"></div></td>
            <td><div class="skel h-3.5 w-28"></div></td>
          </tr>
        @endfor
      </tbody>
    </table>
  </div>
</section>


<section class="kartu hidden" id="panel-mencurigakan" role="tabpanel">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Absen Mencurigakan</h2>
    <span class="badge badge-amber"><span id="total-mencurigakan">0</span> pegawai</span>
  </div>
  <form class="bilah-alat" id="form-mencurigakan">
    <select id="input-bulan-mencurigakan">
      @foreach(BULAN_ID as $no => $nama)
        <option value="{{ $no }}" @selected($no === (int) date('n'))>{{ $nama }}</option>
      @endforeach
    </select>
    <select id="input-tahun-mencurigakan">
      @foreach($tahunList as $th)
        <option value="{{ $th }}" @selected($th === (int) date('Y'))>{{ $th }}</option>
      @endforeach
    </select>
    <button type="button" id="tombol-scan-mencurigakan" class="btn btn-navy btn-kecil">{!! ikon('filter',14) !!} Scan</button>
  </form>
  <div id="aksi-mencurigakan" class="hidden flex items-center gap-2 mb-3">
    <span class="teks-kecil teks-redup"><span id="jumlah-terpilih">0</span> terpilih</span>
    <button type="button" id="btn-kirim-notifikasi" class="btn btn-navy btn-kecil">{!! ikon('info',14) !!} Kirim Notifikasi</button>
    <button type="button" id="btn-kirim-email" class="btn btn-garis btn-kecil">{!! ikon('surat',14) !!} Kirim Email</button>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr>
        <th style="width:40px" class="tengah"><input type="checkbox" id="checkbox-semua-mencurigakan"></th>
        <th>Nama Pegawai</th>
        <th>Absen Masuk Sama</th>
        <th>Absen Keluar Sama</th>
        <th>Koordinat Sama</th>
      </tr></thead>
      <tbody id="tbody-mencurigakan">
        <tr><td colspan="5" class="tengah teks-redup">Pilih bulan & tahun lalu klik Scan.</td></tr>
      </tbody>
    </table>
  </div>
</section>

<div id="modal-email-mencurigakan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-2xl max-h-[92vh] overflow-y-auto">
    <div class="kartu-kepala">
      <h2>{!! ikon('surat') !!} Tinjau Email</h2>
      <button type="button" id="modal-email-mencurigakan-tutup" class="btn btn-garis btn-kecil" aria-label="Tutup">&times;</button>
    </div>
    <form id="form-email-mencurigakan" class="px-3 pb-3 space-y-3">
      <div>
        <div class="teks-kecil font-semibold mb-1">Penerima (<span id="jumlah-email-mencurigakan">0</span>)</div>
        <div id="penerima-email-mencurigakan" class="border border-garis rounded-lg p-2 max-h-28 overflow-y-auto teks-kecil"></div>
      </div>
      <label class="blok">
        <span class="teks-kecil">Subjek</span>
        <input type="text" id="subjek-email-mencurigakan" maxlength="180" required>
      </label>
      <label class="blok">
        <span class="teks-kecil">Pesan</span>
        <textarea id="isi-email-mencurigakan" rows="8" maxlength="10000" required></textarea>
      </label>
      <div class="flex justify-end gap-2">
        <button type="button" id="modal-email-mencurigakan-batal" class="btn btn-garis">Batal</button>
        <button type="submit" id="btn-kirim-email-konfirmasi" class="btn btn-primer">{!! ikon('surat',14) !!} Kirim Email</button>
      </div>
    </form>
  </section>
</div>

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
  var titikAwal    = [];

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
  var paginasiKehadiran = document.getElementById('paginasi-kehadiran');
  var tombolPeta   = document.getElementById('tombol-peta');
  var tabButtons   = document.querySelectorAll('.tab-kehadiran');

  var KUNCI_TAB = 'tabKehadiran';
  var tabMsk = localStorage.getItem(KUNCI_TAB);
  var tabAktif  = (tabMsk === 'percobaan' || tabMsk === 'mencurigakan') ? tabMsk : 'kehadiran';
  var titikPeta = titikAwal;
  var jedaCari  = null;
  var halKehadiran = 1;

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
    var panelMenc = document.getElementById('panel-mencurigakan');
    if (panelMenc) panelMenc.classList.toggle('hidden', tabAktif !== 'mencurigakan');
  }

  Array.prototype.forEach.call(tabButtons, function (tombol) {
    tombol.addEventListener('click', function () {
      tabAktif = tombol.dataset.tab;
      localStorage.setItem(KUNCI_TAB, tabAktif);
      pasangTab();
      if (tabAktif === 'percobaan') muatPercobaan();
  if (tabAktif === 'mencurigakan') muatMencurigakan();
      if (tabAktif === 'mencurigakan') muatMencurigakan();
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
    params.set('hal', halKehadiran);
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
        tbody.removeAttribute('aria-busy');
        paginasiKehadiran.innerHTML = h.paginasi || '';
        halKehadiran = h.hal || 1;
        totalCatatan.textContent = h.total;
        labelTanggalEl.textContent = h.labelTanggal || labelTanggalEl.textContent;
        titikPeta = h.titik || [];
        if (peta) gambarTitik();
        tampilPesan('', true);
      })
      .catch(function () {
        tbody.removeAttribute('aria-busy');
        tampilPesan('Terjadi kesalahan jaringan saat memuat kehadiran.', false);
      });
  }

  function keHalamanKehadiran(halBaru) {
    var nomor = parseInt(halBaru, 10);
    if (! nomor || nomor === halKehadiran) return;
    halKehadiran = nomor;
    muatKehadiran();
    if (tbody.scrollIntoView) tbody.scrollIntoView({ block: 'nearest' });
  }

  // Delegasi karena isi pagination diganti ulang tiap halaman.
  paginasiKehadiran.addEventListener('click', function (e) {
    var tautan = e.target.closest('[data-hal]');
    if (! tautan) return;
    e.preventDefault();
    keHalamanKehadiran(tautan.getAttribute('data-hal'));
  });

  function muatPercobaan() {
    var params = new URLSearchParams({ tanggal: inputTanggal.value, _: String(Date.now()) });
    var kolom = document.getElementById('tbody-percobaan');
    kolom.setAttribute('aria-busy', 'true');

    fetch(urlPercobaan + '?' + params.toString(), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) return;
        kolom.innerHTML = h.html;
        kolom.removeAttribute('aria-busy');
        document.getElementById('total-percobaan').textContent = h.total;
      })
      .catch(function () { /* panel sekunder: biarkan tabel terakhir tetap tampil */ });
  }
  var urlMencurigakan = @json(route('admin.kehadiran.mencurigakan'));
  var btnScanMenc = document.getElementById('tombol-scan-mencurigakan');
  var selBulanMenc = document.getElementById('input-bulan-mencurigakan');
  var selTahunMenc = document.getElementById('input-tahun-mencurigakan');
  var tbodyMenc = document.getElementById('tbody-mencurigakan');
  var totalMenc = document.getElementById('total-mencurigakan');
  var urlNotifikasiMenc = @json(route('admin.kehadiran.mencurigakan.notifikasi'));
  var urlEmailMenc = @json(route('admin.kehadiran.mencurigakan.email'));

  function muatMencurigakan() {
    if (! urlMencurigakan) return;
    if (! tbodyMenc) return;
    tbodyMenc.setAttribute('aria-busy', 'true');
    tbodyMenc.innerHTML = '<tr><td colspan="4" class="tengah teks-redup">Memindai...</td></tr>';
    var params = new URLSearchParams({ bulan: selBulanMenc ? selBulanMenc.value : (new Date()).getMonth()+1, tahun: selTahunMenc ? selTahunMenc.value : (new Date()).getFullYear() });
    fetch(urlMencurigakan + '?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { tbodyMenc.innerHTML = '<tr><td colspan="4" class="tengah teks-redup">Gagal memindai.</td></tr>'; tbodyMenc.removeAttribute('aria-busy'); return; }
        tbodyMenc.innerHTML = h.html || '<tr><td colspan="4" class="tengah teks-redup">Tidak ada data.</td></tr>';
        tbodyMenc.removeAttribute('aria-busy');
        if (totalMenc) totalMenc.textContent = h.total;
      })
      .catch(function () {
        tbodyMenc.removeAttribute('aria-busy');
        tbodyMenc.innerHTML = '<tr><td colspan="4" class="tengah teks-redup">Terjadi kesalahan jaringan.</td></tr>';
      });
  }

  if (btnScanMenc) {
    btnScanMenc.addEventListener('click', muatMencurigakan);
  }


  function muatSemua() {
    halKehadiran = 1;
    muatKehadiran();
    if (tabAktif === 'percobaan') muatPercobaan();
  if (tabAktif === 'mencurigakan') muatMencurigakan();
      if (tabAktif === 'mencurigakan') muatMencurigakan();
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

  // Tabel kehadiran dimuat lazy lewat endpoint (halaman pertama); panel
  // percobaan hanya dimuat bila tabnya sedang aktif.
  pasangTab();
  muatKehadiran();
  if (tabAktif === 'percobaan') muatPercobaan();
  if (tabAktif === 'mencurigakan') muatMencurigakan();
      if (tabAktif === 'mencurigakan') muatMencurigakan();

  function updatePilihanMencurigakan() {
    var cb = tbodyMenc.querySelectorAll('.checkbox-pilih:checked');
    var jml = cb.length;
    var aksi = document.getElementById('aksi-mencurigakan');
    if (aksi) aksi.classList.toggle('hidden', jml === 0);
    var hitung = document.getElementById('jumlah-terpilih');
    if (hitung) hitung.textContent = jml;
  }

  if (tbodyMenc) {
    tbodyMenc.addEventListener('change', function (e) {
      if (e.target && e.target.classList.contains('checkbox-pilih')) {
        updatePilihanMencurigakan();
      }
    });
  }
  var cbSemua = document.getElementById('checkbox-semua-mencurigakan');
  if (cbSemua) {
    cbSemua.addEventListener('change', function () {
      if (! tbodyMenc) return;
      tbodyMenc.querySelectorAll('.checkbox-pilih').forEach(function (c) { c.checked = cbSemua.checked; });
      updatePilihanMencurigakan();
    });
  }

  function dataTerpilih() {
    var list = [];
    if (! tbodyMenc) return list;
    tbodyMenc.querySelectorAll('.checkbox-pilih:checked').forEach(function (c) {
      list.push({ email: c.getAttribute('data-email'), nama: c.getAttribute('data-nama'), user_id: c.getAttribute('data-user') });
    });
    return list;
  }

  var btnNotif = document.getElementById('btn-kirim-notifikasi');
  var btnEmail = document.getElementById('btn-kirim-email');
  var modalEmail = document.getElementById('modal-email-mencurigakan');
  var formEmail = document.getElementById('form-email-mencurigakan');
  var btnKirimEmail = document.getElementById('btn-kirim-email-konfirmasi');

  function tampilkanModalEmail(tampil) {
    if (! modalEmail) return;
    modalEmail.classList.toggle('hidden', !tampil);
    modalEmail.classList.toggle('flex', tampil);
  }

  function isiEmailTerpilih() {
    var penerima = dataTerpilih();
    var wadah = document.getElementById('penerima-email-mencurigakan');
    var isi = 'Kami mendeteksi pola absensi mencurigakan pada akun anda di sistem absensi RSUD Merauke untuk bulan '
      + (selBulanMenc ? selBulanMenc.options[selBulanMenc.selectedIndex].text : '')
      + ' ' + (selTahunMenc ? selTahunMenc.value : '') + '.\n\n'
      + 'Silakan meninjau kembali catatan absensi Anda dan memberikan klarifikasi '
      + 'apabila terdapat ketidaksesuaian. Abaikan pemberitahuan ini jika Anda merasa pemberitahuan ini tidak sesuai. '
        document.getElementById('jumlah-email-mencurigakan').textContent = String(penerima.length);
    wadah.textContent = '';
    penerima.forEach(function (pegawai) {
      var baris = document.createElement('div');
      baris.className = 'py-1 border-b border-slate-100';
      baris.textContent = pegawai.nama + ' — ' + (pegawai.email || 'Email tidak tersedia');
      wadah.appendChild(baris);
    });
    document.getElementById('subjek-email-mencurigakan').value = 'Pola Absensi Mencurigakan di SIMARO - '
      + (selBulanMenc ? selBulanMenc.options[selBulanMenc.selectedIndex].text : '')
      + ' ' + (selTahunMenc ? selTahunMenc.value : '');
    document.getElementById('isi-email-mencurigakan').value = isi;
    tampilkanModalEmail(true);
  }

  if (btnNotif) {
    btnNotif.addEventListener('click', function () {
      var d = dataTerpilih();
      if (d.length === 0) return alert('Tidak ada data terpilih.');

      var tombol = btnNotif;
      var teksAwal = tombol.innerHTML;
      var data = new FormData();
      d.forEach(function (pegawai) { data.append('user_ids[]', pegawai.user_id); });
      data.append('bulan', selBulanMenc ? selBulanMenc.value : '');
      data.append('tahun', selTahunMenc ? selTahunMenc.value : '');

      tombol.disabled = true;
      tombol.textContent = 'Mengirim…';
      tampilPesan('Mengirim notifikasi ke ' + d.length + ' pegawai…', true);
      fetch(urlNotifikasiMenc, {
        method: 'POST',
        body: data,
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf"]') || {}).content || ''
        },
        credentials: 'same-origin'
      })
        .then(function (r) {
          return r.json().then(function (h) {
            if (! r.ok || ! h.sukses) throw new Error(h.pesan || 'Notifikasi gagal dikirim.');
            return h;
          });
        })
        .then(function (h) { tampilPesan(h.pesan, true); })
        .catch(function (error) { tampilPesan(error.message || 'Terjadi kesalahan jaringan.', false); })
        .finally(function () {
          tombol.disabled = false;
          tombol.innerHTML = teksAwal;
        });
    });
  }
  if (btnEmail) {
    btnEmail.addEventListener('click', function () {
      var d = dataTerpilih();
      if (d.length === 0) return alert('Tidak ada data terpilih.');
      if (d.some(function (pegawai) { return !pegawai.email; })) {
        tampilPesan('Ada pegawai terpilih yang belum memiliki email. Periksa alamat email sebelum melanjutkan.', false);
        return;
      }
      isiEmailTerpilih();
    });
  }

  document.getElementById('modal-email-mencurigakan-tutup').addEventListener('click', function () {
    tampilkanModalEmail(false);
  });
  document.getElementById('modal-email-mencurigakan-batal').addEventListener('click', function () {
    tampilkanModalEmail(false);
  });
  modalEmail.addEventListener('click', function (e) {
    if (e.target === modalEmail) tampilkanModalEmail(false);
  });
  formEmail.addEventListener('submit', function (e) {
    e.preventDefault();
    var penerima = dataTerpilih();
    if (penerima.length === 0) {
      tampilkanModalEmail(false);
      tampilPesan('Tidak ada pegawai terpilih. Scan ulang lalu pilih penerima.', false);
      return;
    }

    var data = new FormData();
    penerima.forEach(function (pegawai) { data.append('user_ids[]', pegawai.user_id); });
    data.append('bulan', selBulanMenc ? selBulanMenc.value : '');
    data.append('tahun', selTahunMenc ? selTahunMenc.value : '');
    data.append('subjek', document.getElementById('subjek-email-mencurigakan').value);
    data.append('pesan', document.getElementById('isi-email-mencurigakan').value);

    btnKirimEmail.disabled = true;
    btnKirimEmail.textContent = 'Mengirim…';
    fetch(urlEmailMenc, {
      method: 'POST',
      body: data,
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf"]') || {}).content || ''
      },
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.json().then(function (h) {
          if (! r.ok || ! h.sukses) throw new Error(h.pesan || 'Email gagal dikirim.');
          return h;
        });
      })
      .then(function (h) {
        tampilkanModalEmail(false);
        tampilPesan(h.pesan, true);
      })
      .catch(function (error) {
        tampilPesan(error.message || 'Terjadi kesalahan jaringan saat mengirim email.', false);
      })
      .finally(function () {
        btnKirimEmail.disabled = false;
        btnKirimEmail.innerHTML = @json(ikon('surat',14)) + ' Kirim Email';
      });
  });

})();
</script>
@endsection