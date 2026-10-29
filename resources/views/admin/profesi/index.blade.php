@extends('layouts.admin')

@section('content')
<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.profesi.data') }}"
         data-url-aksi="{{ route('admin.profesi.aksi') }}">
  <div class="kartu-kepala flex items-center justify-between flex-wrap gap-2">
    <h2>Data Profesi</h2>
    <div class="flex items-center gap-2 flex-wrap">

      <button type="button" class="btn btn-primer btn-kecil" data-buka-profesi>
        {!! ikon('tambah', 15) !!} Tambah
      </button>
    </div>
  </div>

  <div data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>
  <div class="my-3">

    <input type="text" data-cari name="q" value="{{ $q }}" autocomplete="off"
               placeholder="Cari profesi…" class="min-w-[170px]">
  </div>

  <div data-tabel>
    @include('admin.profesi.tabel', ['daftar' => $daftar, 'q' => $q])
  </div>

  <p class="petunjuk">Profesi dipakai pada form Data Pegawai, filter rekap, import Excel,
    dan data mobile. Profesi yang masih dipakai pegawai tidak dapat dihapus.</p>

  <div class="modal-tirai" id="modal-profesi">
    <div class="modal-kamera">
      <header class="flex items-center justify-between">
        <span class="flex items-center text-black gap-2">{!! ikon('bintang', 19) !!}
          <span id="prof-judul">Tambah Profesi</span></span>
        <button type="button" id="modal-profesi-tutup" class="btn btn-garis btn-kecil">&times;</button>
      </header>
      <div class="isi">
        <form method="post" action="{{ route('admin.profesi.aksi') }}"
              data-aksi-form data-reset data-tutup="#modal-profesi-tutup">
          @csrf
          <input type="hidden" name="aksi" id="prof-aksi" value="tambah">
          <input type="hidden" name="id" id="prof-id" value="">
          <div class="form-grup">
            <label class="wajib">Nama Profesi</label>
            <input type="text" name="nama" id="prof-nama" maxlength="100" required
                   placeholder="cth. Rekam Medik">
          </div>
          <div class="aksi-baris justify-end">
            <button type="button" class="btn btn-garis btn-kecil" id="modal-profesi-batal">Batal</button>
            <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection

@section('script')
<script>
(function () {
  var modal = document.getElementById('modal-profesi');
  var aksi  = document.getElementById('prof-aksi');
  var id    = document.getElementById('prof-id');
  var nama  = document.getElementById('prof-nama');
  var judul = document.getElementById('prof-judul');

  function buka(mode, data) {
    aksi.value = mode;
    id.value = data && data.id ? data.id : '';
    nama.value = data && data.nama ? data.nama : '';
    judul.textContent = mode === 'ubah' ? 'Ubah Profesi' : 'Tambah Profesi';
    modal.classList.add('terbuka');
    nama.focus();
  }

  function tutup() { modal.classList.remove('terbuka'); }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-buka-profesi]')) { buka('tambah', null); return; }
    var btn = e.target.closest('.btn-ubah-profesi');
    if (btn) buka('ubah', { id: btn.dataset.id, nama: btn.dataset.nama });
  });

  document.getElementById('modal-profesi-tutup').addEventListener('click', tutup);
  document.getElementById('modal-profesi-batal').addEventListener('click', tutup);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutup(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('terbuka')) tutup();
  });
})();
</script>
@endsection
