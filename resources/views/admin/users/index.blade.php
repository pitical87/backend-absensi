@extends('layouts.admin')

@section('content')
<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="tab-bar">
    @foreach($tabs as $kunci => $label)
      <button type="button" data-tab="{{ $kunci }}"
              class="tab-unit {{ $tabAktif === $kunci ? 'aktif' : '' }}">
        {{ $label }}
        <span id="badge-{{ $kunci }}">{{ (int) ($jumlahTab[$kunci] ?? 0) }}</span>
      </button>
    @endforeach
  </div>
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Manajemen User</h2>
  </div>

  <div class="d-flex items-center gap-2 mb-2">
    <input type="text" class="w-full" id="input-q" placeholder="Cari nama / email…" value="{{ $q }}">
  </div>
  <div class="mt-2 teks-kecil teks-redup" id="status-cari"></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th style="width:48px">No</th>
          <th>Nama</th>
          <th>Daftar</th>
          <th id="th-terakhir">{{ $tabAktif === 'deletes' ? 'Dihapus' : 'Terakhir Dilihat' }}</th>
          <th class="tengah" style="width:300px">Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-users">
        @include('admin.users.rows', ['users' => $users, 'tab' => $tabAktif])
      </tbody>
    </table>
  </div>

  <div id="paginasi-users">
    @include('admin.users.paginasi', ['users' => $users])
  </div>
</section>

<div id="modal-ganti-password" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center text-black gap-2">{!! ikon('kunci', 16) !!} Ganti Password User</span>
      <button type="button" class="btn-tutup-modal btn btn-garis btn-kecil">&times;</button>
    </header>
    <div class="isi">
      <p class="teks-kecil teks-redup mt-0 mb-3" id="label-ganti-password"></p>
      <form method="post" action="{{ route('admin.users.password') }}" class="space-y-3.5">
        @csrf
        <input type="hidden" name="id" id="input-ganti-id">
        <input type="hidden" name="tab" id="input-ganti-tab" value="{{ $tabAktif }}">
        <div class="form-grup mb-0">
          <label class="wajib">Password Baru</label>
          <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password">
        </div>
        <div class="form-grup mb-0">
          <label class="wajib">Konfirmasi Password Baru</label>
          <input type="password" name="password_konfirmasi" required minlength="6" placeholder="Ulangi password baru" autocomplete="new-password">
        </div>
        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" class="btn btn-garis btn-kecil btn-tutup-modal">Batal</button>
          <button type="submit" class="btn btn-primer btn-kecil">Simpan Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="modal-hapus-user" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center text-black gap-2">{!! ikon('hapus', 16) !!} Hapus User</span>
      <button type="button" class="btn-tutup-modal btn btn-garis btn-kecil">&times;</button>
    </header>
    <div class="isi">
      <p class="teks-kecil teks-redup mt-0 mb-3" id="label-hapus-user"></p>
      <form method="post" action="{{ route('admin.users.hapus') }}" class="space-y-3.5">
        @csrf
        <input type="hidden" name="id" id="input-hapus-id">
        <input type="hidden" name="tab" id="input-hapus-tab" value="{{ $tabAktif }}">
        <div class="form-grup mb-0">
          <label class="wajib">Catatan Penghapusan</label>
          <textarea name="catatan" id="input-hapus-catatan" rows="3" required
                    placeholder="Contoh: pegawai keluar, meninggal, pensiun…"></textarea>
          <div class="petunjuk">Riwayat absen & data lain tetap tersimpan. Catatan ini tersimpan di
            daftar Deletes dan dapat dipulihkan.</div>
        </div>
        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" class="btn btn-garis btn-kecil btn-tutup-modal">Batal</button>
          <button type="submit" class="btn btn-bahaya btn-kecil">Hapus User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="modal-catatan" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center text-black gap-2">{!! ikon('log', 16) !!} Catatan Penghapusan</span>
      <button type="button" class="btn-tutup-modal btn btn-garis btn-kecil">&times;</button>
    </header>
    <div class="isi">
      <p id="catatan-nama" class="font-semibold text-slate-800 m-0"></p>
      <p id="catatan-email" class="teks-kecil teks-redup mb-3"></p>

      <div class="form-grup mb-0">
        <label class="teks-kecil teks-redup">Catatan</label>
        <div id="catatan-isi" class="p-3 rounded-xl bg-slate-100 border border-slate-200 text-sm whitespace-pre-line"></div>
      </div>

      <p id="catatan-meta" class="teks-kecil teks-redup mt-3 mb-0"></p>
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  var tabBar   = document.getElementById('tab-bar');
  var inputQ   = document.getElementById('input-q');
  var tbody    = document.getElementById('tbody-users');
  var paginasi = document.getElementById('paginasi-users');
  var status   = document.getElementById('status-cari');
  var urlData  = '{{ route('admin.users.data') }}';
  var csrf     = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  var tab = '{{ $tabAktif }}';
  var halaman = 1;
  var jam = null;

  function params() {
    return new URLSearchParams({ tab: tab, page: halaman, q: inputQ.value });
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

  function aktifkanTab(kunci) {
    tab = kunci;
    tabBar.querySelectorAll('.tab-unit[data-tab]').forEach(function (b) {
      b.classList.toggle('aktif', b.getAttribute('data-tab') === kunci);
    });
    var th = document.getElementById('th-terakhir');
    if (th) th.textContent = kunci === 'deletes' ? 'Dihapus' : 'Terakhir Dilihat';
    halaman = 1;
    muat();
  }

  function muat() {
    status.textContent = 'Memuat…';
    fetch(urlData + '?' + params().toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { status.textContent = 'Gagal memuat data.'; return; }
        tbody.innerHTML    = h.tbody;
        paginasi.innerHTML = h.paginasi;
        status.textContent = h.total === 0 ? 'Tidak ada data.' : '';
        halaman = h.halaman;
        pasangPaginasi();
        pasangAksi();
      })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  /* ── Modal ganti password ── */
  var modal       = document.getElementById('modal-ganti-password');
  var inputGanti  = document.getElementById('input-ganti-id');
  var inputTab    = document.getElementById('input-ganti-tab');
  var labelGanti  = document.getElementById('label-ganti-password');

  function bukaModal(id, nama) {
    inputGanti.value = id;
    inputTab.value = tab;
    labelGanti.textContent = 'Ganti password untuk: ' + nama;
    modal.classList.add('terbuka');
  }
  function tutupModal() { modal.classList.remove('terbuka'); }

  modal.querySelectorAll('.btn-tutup-modal').forEach(function (b) {
    b.addEventListener('click', tutupModal);
  });
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });

  /* ── Modal hapus (wajib catatan) ── */
  var modalHapus      = document.getElementById('modal-hapus-user');
  var inputHapusId    = document.getElementById('input-hapus-id');
  var inputHapusTab   = document.getElementById('input-hapus-tab');
  var inputHapusNote  = document.getElementById('input-hapus-catatan');
  var labelHapus      = document.getElementById('label-hapus-user');

  function bukaHapus(id, nama) {
    inputHapusId.value = id;
    inputHapusTab.value = tab;
    inputHapusNote.value = '';
    labelHapus.textContent = 'Catatan untuk menghapus: ' + nama;
    modalHapus.classList.add('terbuka');
    inputHapusNote.focus();
  }
  function tutupHapus() { modalHapus.classList.remove('terbuka'); }

  modalHapus.querySelectorAll('.btn-tutup-modal').forEach(function (b) {
    b.addEventListener('click', tutupHapus);
  });
  modalHapus.addEventListener('click', function (e) { if (e.target === modalHapus) tutupHapus(); });

  /* ── Modal catatan penghapusan ── */
  var modalCatatan = document.getElementById('modal-catatan');
  var catatanNama  = document.getElementById('catatan-nama');
  var catatanEmail = document.getElementById('catatan-email');
  var catatanIsi   = document.getElementById('catatan-isi');
  var catatanMeta  = document.getElementById('catatan-meta');

  function bukaCatatan(btn) {
    catatanNama.textContent  = btn.getAttribute('data-nama') || '';
    catatanEmail.textContent = btn.getAttribute('data-email') || '';
    catatanIsi.textContent   = btn.getAttribute('data-catatan') || '';

    var oleh    = btn.getAttribute('data-oleh') || '';
    var dihapus = btn.getAttribute('data-dihapus') || '';
    var bagian  = [];
    if (oleh)    bagian.push('Dihapus oleh: ' + oleh);
    if (dihapus) bagian.push('Dihapus pada: ' + dihapus);
    catatanMeta.textContent = bagian.join(' • ');

    modalCatatan.classList.add('terbuka');
  }
  function tutupCatatan() { modalCatatan.classList.remove('terbuka'); }

  modalCatatan.querySelectorAll('.btn-tutup-modal').forEach(function (b) {
    b.addEventListener('click', tutupCatatan);
  });
  modalCatatan.addEventListener('click', function (e) { if (e.target === modalCatatan) tutupCatatan(); });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    tutupModal();
    tutupHapus();
    tutupCatatan();
  });

  /* ── Aksi baris (update/password/aktifkan/hapus) ── */
  function pasangAksi() {
    tbody.querySelectorAll('.btn-ganti-password').forEach(function (b) {
      b.addEventListener('click', function () {
        bukaModal(b.getAttribute('data-id'), b.getAttribute('data-nama'));
      });
    });

    tbody.querySelectorAll('.btn-hapus').forEach(function (b) {
      b.addEventListener('click', function () {
        bukaHapus(b.getAttribute('data-id'), b.getAttribute('data-nama'));
      });
    });

    tbody.querySelectorAll('.btn-catatan').forEach(function (b) {
      b.addEventListener('click', function () { bukaCatatan(b); });
    });

    tbody.querySelectorAll('.form-status').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        kirim(f, 'Ubah status akun ' + (f.getAttribute('data-nama') || '') + '?');
      });
    });

    tbody.querySelectorAll('.form-pulihkan').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var nama = f.getAttribute('data-nama') || '';
        kirim(f, 'Pulihkan akun ' + nama + '?');
      });
    });

    tbody.querySelectorAll('.form-hapus-permanen').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        var nama = f.getAttribute('data-nama') || '';
        if (! confirm('Hapus permanen ' + nama + '?\nSeluruh riwayat absen dan data terkait akan hilang dan tidak dapat dikembalikan.')) return;
        kirim(f, null);
      });
    });
  }

  function kirim(form, pesan) {
    if (pesan && ! confirm(pesan)) return;
    var data = new FormData(form);
    data.set('tab', tab);
    status.textContent = 'Menyimpan…';
    fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
      .then(function () { window.location = '{{ url('admin/users') }}?tab=' + tab; })
      .catch(function () { status.textContent = 'Terjadi kesalahan jaringan.'; });
  }

  tabBar.addEventListener('click', function (e) {
    var b = e.target.closest('.tab-unit[data-tab]');
    if (b) aktifkanTab(b.getAttribute('data-tab'));
  });

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

  pasangPaginasi();
  pasangAksi();
})();
</script>
@endsection
