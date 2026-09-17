@extends('layouts.admin')

@section('content')

<div class="stat-admin mb-4">
  <div class="stat"><span>Pengguna Aktif Login</span><strong id="total-pengguna">{{ number_format($totalPengguna, 0, ',', '.') }}</strong></div>
  <div class="stat hijau"><span>Perangkat / Sesi Aktif</span><strong id="total-perangkat">{{ number_format($totalPerangkat, 0, ',', '.') }}</strong></div>
</div>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kunci') !!} User Login Mobile</h2>
  </div>

  <div class="bilah-alat">
    <input type="text" id="input-q" placeholder="Cari nama / email / unit…" value="{{ $q }}">
  </div>
  <div class="mt-2 teks-kecil teks-redup" id="status-cari"></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Unit / Sub Unit</th>
          <th>Perangkat</th>
          <th>Terakhir Aktif</th>
          <th class="tengah">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-user-login">
        @include('admin.user_login.rows', ['rows' => $rows])
      </tbody>
    </table>
  </div>

  <div id="paginasi-user-login">
    @include('admin.user_login.paginasi', ['rows' => $rows])
  </div>
</section>

<div id="modal-detail" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-3xl max-h-[85vh] flex flex-col">
    <div class="kartu-kepala">
      <h2>{!! ikon('kunci') !!} <span id="detail-nama">—</span></h2>
      <div class="flex items-center gap-2">
        <span class="badge badge-biru" id="detail-jumlah">0 perangkat</span>
        <button type="button" id="modal-detail-tutup" class="btn btn-garis btn-kecil">&times;</button>
      </div>
    </div>
    <p class="px-3 teks-kecil teks-redup" id="detail-unit">—</p>
    <div id="modal-detail-badan" class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
      <div class="tabel-bungkus">
        <table class="tabel">
          <thead>
            <tr>
              <th>Perangkat</th>
              <th>IP</th>
              <th>Login sejak</th>
              <th>Terakhir aktif</th>
              <th>Kedaluwarsa</th>
              <th class="tengah">Status</th>
              <th class="tengah">Aksi</th>
            </tr>
          </thead>
          <tbody id="tbody-detail"></tbody>
        </table>
      </div>
    </div>
  </section>
</div>

@endsection

@section('script')
<script>
(function () {
  var inputQ   = document.getElementById('input-q');
  var tbody    = document.getElementById('tbody-user-login');
  var paginasi = document.getElementById('paginasi-user-login');
  var statPengguna   = document.getElementById('total-pengguna');
  var statPerangkat  = document.getElementById('total-perangkat');
  var status   = document.getElementById('status-cari');
  var csrf     = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var urlData  = '{{ route('admin.user_login.data') }}';
  var urlDetail = '{{ route('admin.user_login.detail') }}';

  var modal   = document.getElementById('modal-detail');
  var modalTutup = document.getElementById('modal-detail-tutup');
  var tbodyDetail = document.getElementById('tbody-detail');
  var detailNama  = document.getElementById('detail-nama');
  var detailUnit  = document.getElementById('detail-unit');
  var detailJumlah = document.getElementById('detail-jumlah');

  var halaman = 1;
  var jam = null;
  var userIdAktif = null;

  function formatAngka(n) { return Number(n || 0).toLocaleString('id-ID'); }

  function params() {
    return new URLSearchParams({ page: halaman, q: inputQ.value });
  }

  function pasangPaginasi() {
    paginasi.querySelectorAll('a[data-page]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        halaman = parseInt(a.getAttribute('data-page'), 10) || 1;
        muat();
      });
    });
  }

  function muat() {
    status.textContent = 'Memuat…';
    fetch(urlData + '?' + params().toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { status.textContent = 'Gagal memuat data.'; return; }
        tbody.innerHTML      = h.tbody;
        paginasi.innerHTML   = h.paginasi;
        statPengguna.textContent  = formatAngka(h.totalPengguna);
        statPerangkat.textContent = formatAngka(h.totalPerangkat);
        status.textContent = h.total === 0 ? 'Tidak ada hasil.' : '';
        halaman = h.halaman;
        pasangPaginasi();
        pasangAksi();
      })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  function bukaModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }
  function tutupModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  function bukaDetail(id) {
    userIdAktif = id;
    detailNama.textContent = '…';
    detailUnit.textContent = 'Memuat…';
    detailJumlah.textContent = '0 perangkat';
    tbodyDetail.innerHTML = '<tr><td colspan="7" class="tengah teks-redup">Memuat…</td></tr>';
    bukaModal();

    fetch(urlDetail + '?user_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          tbodyDetail.innerHTML = '<tr><td colspan="7" class="tengah teks-redup">' + (h.pesan || 'Gagal memuat.') + '</td></tr>';
          return;
        }
        detailNama.textContent = h.nama;
        detailUnit.textContent = [h.unit, h.sub_unit].filter(Boolean).join(' — ') || '—';
        detailJumlah.textContent = h.jumlah + ' perangkat';
        tbodyDetail.innerHTML = h.tbody;
        bukaModal();
      })
      .catch(function () {
        tbodyDetail.innerHTML = '<tr><td colspan="7" class="tengah teks-redup">Terjadi kesalahan jaringan.</td></tr>';
      });
  }

  function kirim(form, pesanKonfirmasi) {
    if (pesanKonfirmasi && ! confirm(pesanKonfirmasi)) return;
    var data = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        status.textContent = h.sukses ? h.pesan : (h.pesan || 'Gagal.');
        muat();
        if (userIdAktif) bukaDetail(userIdAktif);
      })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  function pasangAksi() {
    document.querySelectorAll('#tbody-user-login .btn-detail').forEach(function (b) {
      b.addEventListener('click', function () {
        bukaDetail(b.getAttribute('data-user-id'));
      });
    });
    document.querySelectorAll('#tbody-user-login .form-logout-semua').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var nama = f.getAttribute('data-nama') || 'pengguna';
        kirim(f, 'Putus semua sesi mobile milik ' + nama + '?');
      });
    });
  }

  inputQ.addEventListener('input', function () {
    clearTimeout(jam);
    jam = setTimeout(function () { halaman = 1; muat(); }, 350);
  });
  inputQ.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      clearTimeout(jam);
      halaman = 1;
      muat();
    }
  });

  modalTutup.addEventListener('click', tutupModal);
  modal.addEventListener('click', function (e) {
    if (e.target === modal) tutupModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ! modal.classList.contains('hidden')) tutupModal();
  });

  // Delegasi logout per perangkat yang ada di dalam modal
  tbodyDetail.addEventListener('submit', function (e) {
    var f = e.target;
    if (f && f.classList && f.classList.contains('form-logout')) {
      e.preventDefault();
      kirim(f, 'Putus sesi perangkat ini?');
    }
  });

  pasangPaginasi();
  pasangAksi();

})();
</script>
@endsection