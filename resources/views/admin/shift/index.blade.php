@extends('layouts.admin')

@section('content')
<section class="kartu">
  <div class="kartu-kepala flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
    <h2>{!! ikon("jam", 16) !!} Daftar Shift Kerja</h2>
     <div class="flex flex-wrap gap-2">
      <button type="button" class="btn btn-primer" id="tombol-tambah">
        {!! ikon("tambah", 16) !!} Tambah Shift
      </button>
    </div>
    <div class="flex flex-wrap gap-2 w-full items-center">
      <input type="text" id="pencarian-shift" placeholder="Cari kategori atau jam..." autocomplete="off" class="w-full sm:w-56">
    </div>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Kategori</th>
          <th>Jam Masuk</th>
          <th>Jam Pulang</th>
          <th>Lintas Hari</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="tbody-shift">
        @include('admin.shift.rows', ['shiftList' => $shiftList])
      </tbody>
    </table>
  </div>
  <div class="flex items-center justify-between mt-3">
    <div class="teks-kecil teks-redup" id="info-shift">Menampilkan {{ count($shiftList) }} shift</div>
  </div>
</section>

<!-- Modal Tambah/Ubah Shift -->
<div id="modal-shift" class="fixed flex inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <section class="kartu w-full max-w-md flex flex-col">
      <div class="flex items-start justify-between gap-3 mb-3">
        <h3 class="text-base font-bold text-navy" id='judul-modal-shift'>Tambah Shift</h3>
        <button type="button" class="btn btn-garis btn-kecil" id="modal-shift-tutup">×</button>
      </div>
      <div class="p-3 flex flex-col gap-2 overflow-hidden flex-1 gap-2">
        <form id="form-shift" action="{{ route('admin.shift.aksi') }}" method="post">
          @csrf
          <input type="hidden" name="aksi" id="aksi-shift" value="tambah_shift">
          <input type="hidden" name="id" id="id-shift" value="">
          <div class="grid gap-2">
            <div>
              <label for="kategori-shift" class="teks-kecil teks-tebal blok mb-1">Kategori</label>
              <select id="kategori-shift" name="kategori" required class="w-full">
                <option value="">Pilih kategori...</option>
                <option value="Pagi">Pagi</option>
                <option value="Sore">Sore</option>
                <option value="Malam">Malam</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-2">
              <div>
                <label for="jam-masuk" class="teks-kecil teks-tebal blok mb-1">Jam Masuk</label>
                <input type="time" id="jam-masuk" name="jam_masuk" required class="w-full">
              </div>
              <div>
                <label for="jam-pulang" class="teks-kecil teks-tebal blok mb-1">Jam Pulang</label>
                <input type="time" id="jam-pulang" name="jam_pulang" required class="w-full">
              </div>
            </div>
          </div>
          <div class="modal-tindakan">
            <button type="button" class="btn btn-garis" id="modal-shift-batal">Batal</button>
            <button type="submit" class="btn btn-primer" id="btn-simpan-shift">Simpan</button>
          </div>
        </form>
      </div>
  </section>
</div>
@endsection

@section('script')
<script>
(function () {
  var tbody = document.getElementById('tbody-shift');
  var cari = document.getElementById('pencarian-shift');
  var info = document.getElementById('info-shift');
  var btnTambah = document.getElementById('tombol-tambah');
  var modal = document.getElementById('modal-shift');
  var form = document.getElementById('form-shift');
  var judul = document.getElementById('judul-modal-shift');
  var aksi = document.getElementById('aksi-shift');
  var idShift = document.getElementById('id-shift');
  var kat = document.getElementById('kategori-shift');
  var jm = document.getElementById('jam-masuk');
  var jp = document.getElementById('jam-pulang');
  var tutup = document.getElementById('modal-shift-tutup');
  var batal = document.getElementById('modal-shift-batal');
  var csrf = document.querySelector('meta[name="csrf"]')?.content;
  var urlData = @json(route('admin.shift.data'));
  var urlAksi = form.getAttribute('action');

  function tampilModal(tampilkan, tipe, data) {
    if (!modal) return;
    modal.classList.toggle('hidden', !tampilkan);
    if (!tampilkan) return;
    if (tipe === 'tambah') {
      judul.textContent = 'Tambah Shift';
      aksi.value = 'tambah_shift';
      idShift.value = '';
      form.reset();
    } else if (tipe === 'ubah' && data) {
      judul.textContent = 'Ubah Shift';
      aksi.value = 'ubah_shift';
      idShift.value = data.id || '';
      kat.value = data.kategori || '';
      jm.value = data.masuk || '';
      jp.value = data.pulang || '';
    }
  }

  if (btnTambah) btnTambah.addEventListener('click', function () { tampilModal(true, 'tambah'); });
  if (tutup) tutup.addEventListener('click', function () { tampilModal(false); });
  if (batal) batal.addEventListener('click', function () { tampilModal(false); });
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target.classList.contains('modal-latar')) tampilModal(false);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) tampilModal(false);
  });

  function muatData(q) {
    var params = new URLSearchParams();
    if (q) params.set('q', q);
    var url = params.toString() ? (urlData + '?' + params.toString()) : urlData;
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (tbody) tbody.innerHTML = h.html || '';
        if (info) info.textContent = 'Menampilkan ' + (h.total || 0) + ' shift';
      })
      .catch(function () {});
  }

  if (cari) {
    cari.addEventListener('input', function () {
      muatData(cari.value.trim());
    });
  }

  tbody.addEventListener('click', function (e) {
    var btnUbah = e.target.closest('.tombol-ubah');
    if (btnUbah) {
      tampilModal(true, 'ubah', {
        id: btnUbah.dataset.id,
        kategori: btnUbah.dataset.kategori,
        masuk: btnUbah.dataset.masuk,
        pulang: btnUbah.dataset.pulang,
        lintas: btnUbah.dataset.lintas,
        aktif: btnUbah.dataset.aktif
      });
      return;
    }

    var btnToggle = e.target.closest('.tombol-toggle');
    if (btnToggle) {
      fetch(urlAksi, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': csrf || ''
        },
        body: new URLSearchParams({ aksi: 'toggle_shift', id: btnToggle.dataset.id })
      })
        .then(function (r) { return r.json(); })
        .then(function (h) {
          if (tbody && h.html) tbody.innerHTML = h.html;
          if (info) info.textContent = 'Menampilkan ' + (h.total || 0) + ' shift';
        })
        .catch(function () {});
      return;
    }

    var btnHapus = e.target.closest('.tombol-hapus');
    if (btnHapus) {
      if (!confirm('Hapus shift ini?')) return;
      fetch(urlAksi, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': csrf || ''
        },
        body: new URLSearchParams({ aksi: 'hapus_shift', id: btnHapus.dataset.id })
      })
        .then(function (r) { return r.json(); })
        .then(function (h) {
          if (tbody && h.html) tbody.innerHTML = h.html;
          if (info) info.textContent = 'Menampilkan ' + (h.total || 0) + ' shift';
        })
        .catch(function () {});
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btn-simpan-shift');
    if (btn) btn.disabled = true;
    var fd = new FormData(form);
    var body = new URLSearchParams(fd);
    fetch(urlAksi, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': csrf || ''
      },
      body: body
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (btn) btn.disabled = false;
        if (h.sukses) {
          if (tbody && h.html) tbody.innerHTML = h.html;
          if (info) info.textContent = 'Menampilkan ' + (h.total || 0) + ' shift';
          tampilModal(false);
        } else {
          alert(h.pesan || 'Gagal menyimpan data.');
        }
      })
      .catch(function () {
        if (btn) btn.disabled = false;
        alert('Terjadi kesalahan saat menyimpan.');
      });
  });
})();
</script>
@endsection
