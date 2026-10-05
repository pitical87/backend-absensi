@extends('layouts.admin')

@section('content')



{{-- ===== RINGKASAN ===== --}}
<div class="stat-admin mb-4">
  <div class="stat"><span>Total Pegawai</span><strong id="stat-total">{{ number_format($statistik['total'], 0, ',', '.') }}</strong></div>
  <div class="stat hijau"><span>Sudah Diatur</span><strong id="stat-sudah">{{ number_format($statistik['sudah'], 0, ',', '.') }}</strong></div>
  <div class="stat amber"><span>Belum Diatur</span><strong id="stat-belum">{{ number_format($statistik['belum'], 0, ',', '.') }}</strong></div>
</div>

{{-- ===== FILTER & PENCARIAN ===== --}}
<section class="kartu">
  <div class="kartu-kepala">
    <h2>Daftar Pegawai</h2>
  </div>

  <form method="get" action="{{ route('admin.atasan_langsung.index') }}" id="form-filter">
     <div class="grid gap-2" id="form-cari" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
      <input type="text" id="input-q" name="q" placeholder="Cari nama / email / unit…"
             value="{{ $q }}" autocomplete="off">
      <select id="input-status" name="status">
        <option value="">Semua </option>
        <option value="sudah" @selected($status === 'sudah')>Sudah ada atasan</option>
        <option value="belum" @selected($status === 'belum')>Belum ada atasan</option>
      </select>
      <button type="submit" class="btn btn-primer btn-kecil">Terapkan</button>
      <a href="{{ route('admin.atasan_langsung.index') }}" class="btn btn-garis btn-kecil">Reset</a>
    </div>
  </form>
  <div class="mt-2 teks-kecil teks-redup" id="status-cari"></div>

  {{-- ===== BARIS AKSI MASSAL ===== --}}
  <div id="baris-pilihan" class="hidden mt-3 mb-1 px-4 py-3 rounded-xl bg-biru/10 border border-biru/25 flex flex-wrap items-center gap-3">
    <span class="text-sm font-medium text-navy"><span id="jumlah-terpilih">0</span> pegawai dipilih</span>
    <button type="button" class="btn btn-navy btn-kecil" id="btn-aturan-terpilih">Atur Atasan Terpilih</button>
    <button type="button" class="btn btn-garis btn-kecil" id="btn-batal-pilihan">Batalkan Pilihan</button>
    <span class="teks-kecil teks-redup">Pemilihan tetap berlaku saat pindah halaman.</span>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th class="w-[42px] tengah">
            <input type="checkbox" id="pilih-semua" class="w-auto" aria-label="Pilih semua di halaman ini">
          </th>
          <th>Pegawai</th>
          <th>Unit / Sub Unit</th>
          <th>Atasan Langsung</th>
          <th class="w-[150px]">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-atasan">
        @include('admin.atasan_langsung.rows', ['rows' => $rows, 'peta' => $peta])
      </tbody>
    </table>
  </div>

  <div id="paginasi-atasan">
    @include('admin.atasan_langsung.paginasi', ['rows' => $rows])
  </div>
</section>

{{-- ===== FORMULIR SIMPAN (satu / banyak pegawai) ===== --}}
<form method="post" action="{{ route('admin.atasan_langsung.aksi') }}" id="form-atasan">
  @csrf
  <div id="kontainer-user-ids"></div>

  <div id="modal-atasan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
       onclick="if(event.target===this) window.atasanTutup()">
    <div class="kartu w-full max-w-lg max-h-[85vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="text-base font-bold text-navy">Atur Atasan Langsung</h3>
        <button type="button" class="btn btn-garis btn-kecil" onclick="window.atasanTutup()">×</button>
      </div>

      <div class="teks-kecil teks-redup mb-1">Target</div>
      <div id="target-pegawai" class="flex flex-wrap gap-1 mb-3"></div>

      <div class="teks-kecil teks-redup mb-1">Atasan yang dipilih</div>
      <div id="terpilih-atasan" class="flex flex-wrap gap-1 mb-3"></div>

      <div class="mb-3">
        <div class="flex gap-4 text-sm">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="mode" value="ganti" id="mode-ganti" class="w-auto">
            <span>Ganti seluruh atasan</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="mode" value="tambah" id="mode-tambah" class="w-auto">
            <span>Tambahkan saja</span>
          </label>
        </div>
        
      </div>

      <input type="text" id="cari-atasan" placeholder="Cari nama / email atasan…" class="mb-2" autocomplete="off">
      <div class="teks-kecil teks-redup mb-1" id="status-atasan"></div>

      <div id="daftar-atasan" class="max-h-[35vh] overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 mb-4">
        <div class="py-6 tengah teks-redup text-sm">Memuat kandidat atasan…</div>
      </div>

      <div class="flex justify-end gap-2">
        <button type="button" class="btn btn-garis" onclick="window.atasanTutup()">Batal</button>
        <button type="submit" class="btn btn-primer" id="btn-simpan-atasan">Simpan Atasan</button>
      </div>
    </div>
  </div>
</form>

@endsection

@section('script')
<script>
(function () {
  var inputQ      = document.getElementById('input-q');
  var inputStatus = document.getElementById('input-status');
  var tbody       = document.getElementById('tbody-atasan');
  var paginasi    = document.getElementById('paginasi-atasan');
  var statusCari  = document.getElementById('status-cari');
  var pilihSemua  = document.getElementById('pilih-semua');
  var barisPilih  = document.getElementById('baris-pilihan');
  var jmlPilih    = document.getElementById('jumlah-terpilih');
  var statTotal   = document.getElementById('stat-total');
  var statSudah   = document.getElementById('stat-sudah');
  var statBelum   = document.getElementById('stat-belum');

  var urlData    = '{{ route('admin.atasan_langsung.data') }}';
  var urlPilihan = '{{ route('admin.atasan_langsung.pilihan') }}';

  var halaman = 1;
  var jam = null;
  var jamAtasan = null;
  var sedangMuat = false;

  // Id pegawai yang dicentang, bertahan lintas halaman.
  var terpilih = new Set();
  // Pegawai yang sedang diatur (satu atau banyak).
  var target = [];
  // Id atasan yang dicentang di modal.
  var dipilihAtasan = new Set();
  var namaAtasan = {};
  var namaTarget = {};

  function angka(n) { return Number(n || 0).toLocaleString('id-ID'); }

  function chip(teks, kelas) {
    var s = document.createElement('span');
    s.className = 'badge ' + kelas;
    s.textContent = teks;
    return s;
  }

  /* ---------- Daftar pegawai (asinkron + debounce) ---------- */

  function muat() {
    if (sedangMuat) return;
    sedangMuat = true;
    statusCari.textContent = 'Memuat…';

    var params = new URLSearchParams({
      page: halaman,
      q: inputQ.value,
      status: inputStatus.value
    });

    fetch(urlData + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { statusCari.textContent = 'Gagal memuat data.'; return; }
        tbody.innerHTML = h.tbody;
        paginasi.innerHTML = h.paginasi;
        statTotal.textContent = angka(h.statistik.total);
        statSudah.textContent = angka(h.statistik.sudah);
        statBelum.textContent = angka(h.statistik.belum);
        statusCari.textContent = h.total === 0 ? 'Tidak ada hasil.' : '';
        halaman = h.halaman;
        pasangPaginasi();
        pasangBaris();
        gambarPilihSemua();
      })
      .catch(function () { statusCari.textContent = 'Terjadi kesalahan jaringan.'; })
      .then(function () { sedangMuat = false; });
  }

  function pasangPaginasi() {
    paginasi.querySelectorAll('a[data-page]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        halaman = parseInt(a.getAttribute('data-page'), 10) || 1;
        muat();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  function pasangBaris() {
    tbody.querySelectorAll('.chk-pilih').forEach(function (c) {
      c.checked = terpilih.has(Number(c.value));
      c.addEventListener('change', function () {
        var id = Number(c.value);
        if (c.checked) { terpilih.add(id); } else { terpilih.delete(id); }
        gambarPilihSemua();
        gambarBarisPilih();
      });
    });
    tbody.querySelectorAll('[data-atur]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = Number(b.getAttribute('data-atur'));
        var nama = b.closest('tr').querySelector('.font-medium').textContent.trim();
        bukaModal([{ id: id, nama: nama, atasan: (b.getAttribute('data-atasan') || '').split(',').filter(Boolean).map(Number) }]);
      });
    });
  }

  function gambarPilihSemua() {
    var chk = tbody.querySelectorAll('.chk-pilih');
    var tercentang = 0;
    chk.forEach(function (c) { if (c.checked) tercentang++; });
    pilihSemua.checked = chk.length > 0 && tercentang === chk.length;
    pilihSemua.indeterminate = tercentang > 0 && tercentang < chk.length;
    pilihSemua.disabled = chk.length === 0;
  }

  function gambarBarisPilih() {
    var n = terpilih.size;
    barisPilih.classList.toggle('hidden', n === 0);
    jmlPilih.textContent = n;
  }

  pilihSemua.addEventListener('change', function () {
    tbody.querySelectorAll('.chk-pilih').forEach(function (c) {
      c.checked = pilihSemua.checked;
      if (pilihSemua.checked) { terpilih.add(Number(c.value)); } else { terpilih.delete(Number(c.value)); }
    });
    gambarBarisPilih();
  });

  document.getElementById('btn-batal-pilihan').addEventListener('click', function () {
    terpilih.clear();
    tbody.querySelectorAll('.chk-pilih').forEach(function (c) { c.checked = false; });
    gambarPilihSemua();
    gambarBarisPilih();
  });

  document.getElementById('btn-aturan-terpilih').addEventListener('click', function () {
    var chk = tbody.querySelectorAll('.chk-pilih');
    var daftar = [];
    chk.forEach(function (c) {
      if (c.checked) daftar.push({ id: Number(c.value), nama: c.getAttribute('data-nama') });
    });
    if (! daftar.length) return;
    bukaModal(daftar);
  });

  inputQ.addEventListener('input', function () {
    clearTimeout(jam);
    jam = setTimeout(function () { halaman = 1; muat(); }, 350);
  });
  inputQ.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(jam); halaman = 1; muat(); }
  });
  inputStatus.addEventListener('change', function () { halaman = 1; muat(); });

  /* ---------- Modal atasan ---------- */

  function bukaModal(daftar) {
    target = daftar;
    // Satu pegawai mulai dari atasan yang sudah ada, mode bersama mulai dari kosong
    // supaya admin tidak sengaja menghapus relasi lama.
    dipilihAtasan = new Set(daftar.length === 1 ? (daftar[0].atasan || []) : []);
    document.getElementById('cari-atasan').value = '';
    document.getElementById('status-atasan').textContent = '';

    var wadah = document.getElementById('target-pegawai');
    wadah.innerHTML = '';
    daftar.slice(0, 12).forEach(function (t) { wadah.appendChild(chip(t.nama, 'badge-biru')); });
    if (daftar.length > 12) wadah.appendChild(chip('+' + (daftar.length - 12) + ' lainnya', 'badge-abu'));

    document.getElementById('mode-ganti').checked = daftar.length === 1;
    document.getElementById('mode-tambah').checked = daftar.length > 1;

    var modal = document.getElementById('modal-atasan');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    gambarTerpilihAtasan();
    muatPilihan('');
  }

  window.atasanTutup = function () {
    var modal = document.getElementById('modal-atasan');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

function muatPilihan(kata) {
    var status = document.getElementById('status-atasan');
    var kotak = document.getElementById('daftar-atasan');
    status.textContent = 'Memuat…';
    // Kosongkan dulu supaya centang milik pencarian sebelumnya tidak sempat
    // diklik dan ikut tersimpan.
    kotak.innerHTML = '<div class="py-6 tengah teks-redup text-sm">Memuat kandidat atasan…</div>';

    fetch(urlPilihan + '?' + new URLSearchParams({ q: kata }).toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { status.textContent = 'Gagal memuat kandidat.'; return; }
        kotak.innerHTML = h.rows;
        pasangPilihan();
        status.textContent = h.total === 0 ? 'Tidak ada kandidat.' : (h.total >= 50 ? 'Menampilkan 50 kandidat pertama, gunakan pencarian untuk mempersempit.' : '');
      })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  function pasangPilihan() {
    var idsTarget = new Set(target.map(function (t) { return t.id; }));

    document.querySelectorAll('#daftar-atasan .chk-atasan').forEach(function (c) {
      var id = Number(c.value);
      var label = c.closest('label');
      var nama = label.querySelector('span').textContent;
      namaAtasan[id] = nama;

      // Pegawai yang jadi target tidak mungkin menjadi atasan dirinya sendiri.
      c.disabled = idsTarget.has(id);
      if (c.disabled) {
        label.classList.add('opacity-50');
        return;
      }

      c.checked = dipilihAtasan.has(id);
      c.addEventListener('change', function () {
        if (c.checked) { dipilihAtasan.add(id); } else { dipilihAtasan.delete(id); }
        gambarTerpilihAtasan();
      });
    });
  }

  function gambarTerpilihAtasan() {
    var wadah = document.getElementById('terpilih-atasan');
    wadah.innerHTML = '';

    if (! dipilihAtasan.size) {
      wadah.appendChild(chip('Belum ada atasan dipilih', 'badge-abu'));
      return;
    }

    Array.from(dipilihAtasan).sort(function (a, b) {
      return String(namaAtasan[a] || a).localeCompare(String(namaAtasan[b] || b));
    }).forEach(function (id) {
      wadah.appendChild(chip(namaAtasan[id] || ('#' + id), 'badge-hijau'));
    });
  }

  document.getElementById('cari-atasan').addEventListener('input', function (e) {
    clearTimeout(jamAtasan);
    var kata = e.target.value;
    jamAtasan = setTimeout(function () { muatPilihan(kata); }, 350);
  });

  document.getElementById('form-atasan').addEventListener('submit', function (e) {
    var wadah = document.getElementById('kontainer-user-ids');
    wadah.innerHTML = '';
    target.forEach(function (t) {
      var s = document.createElement('input');
      s.type = 'hidden';
      s.name = 'user_ids[]';
      s.value = t.id;
      wadah.appendChild(s);
    });

    if (! dipilihAtasan.size) {
      e.preventDefault();
      alert('Pilih minimal satu atasan.');
      return;
    }

    if (! document.getElementById('mode-ganti').checked) return;

    var pesan = target.length > 1
      ? 'Ganti seluruh atasan ' + target.length + ' pegawai terpilih? Atasan lama akan ditimpa.'
      : 'Ganti seluruh atasan langsung ' + target[0].nama + '?';

    if (! confirm(pesan)) e.preventDefault();
  });

  document.addEventListener('keydown', function (e) {
    var modal = document.getElementById('modal-atasan');
    if (e.key === 'Escape' && ! modal.classList.contains('hidden')) window.atasanTutup();
  });

  // Baris dan paginasi pertama berasal dari render server, jadi tetap perlu
  // dipasang handler-nya.
  pasangPaginasi();
  pasangBaris();
  gambarPilihSemua();
  gambarBarisPilih();
})();
</script>
@endsection