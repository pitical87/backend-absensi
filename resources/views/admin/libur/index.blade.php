@extends('layouts.admin')

@section('content')

<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.libur.data') }}"
         data-url-aksi="{{ route('admin.libur.aksi') }}">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kalender Hari Libur {{ $tahun }}</h2>
    <form method="get" action="{{ route('admin.libur.index') }}" class="bilah-alat m-0">
      <input type="text" data-cari name="q" value="{{ $q }}" autocomplete="off"
             placeholder="Cari keterangan / tanggal…" class="min-w-[170px]">
      <select name="tahun" data-kategori="tahun" onchange="this.form.submit()">
        @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
          <option value="{{ $t }}" {{ $t === (int) $tahun ? 'selected' : '' }}>{{ $t }}</option>
        @endfor
      </select>
      <button type="submit" class="btn btn-navy btn-kecil">Cari</button>
    </form>
  </div>

  <form method="post" action="{{ route('admin.libur.aksi') }}" class="bilah-alat" data-aksi-form data-reset>
    @csrf
    <input type="hidden" name="aksi" value="tambah">
    <input type="hidden" name="tahun" value="{{ (int) $tahun }}">
    <input type="date" name="tanggal" required>
    <input type="text" name="keterangan" placeholder="cth. Hari Kemerdekaan RI / Cuti Bersama…" required>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Hari Libur</button>
  </form>

  <div id="pesan-libur" data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.libur.tabel')
  </div>

{{-- Modal Ubah Hari Libur --}}
<div id="modal-libur" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-sm">
    <div class="kartu-kepala">
      <h2>{!! ikon('kalender') !!} Ubah Hari Libur</h2>
      <button type="button" id="modal-libur-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <form method="post" action="{{ route('admin.libur.aksi') }}" class="px-3 pb-3 space-y-3"
          data-aksi-form data-tutup="#modal-libur-tutup">
      @csrf
      <input type="hidden" name="aksi" value="ubah">
      <input type="hidden" name="tahun" value="{{ (int) $tahun }}">
      <input type="hidden" name="id" value="" id="libur-id">
      <label class="blok">
        <span class="teks-kecil">Tanggal</span>
        <input type="date" name="tanggal" id="libur-tanggal" required>
      </label>
      <label class="blok">
        <span class="teks-kecil">Keterangan</span>
        <input type="text" name="keterangan" id="libur-keterangan" required>
      </label>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-garis" id="modal-libur-batal">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan</button>
      </div>
    </form>
  </section>
</div>
</section>

@endsection

@section('script')
<script>
(function () {
  var modal = document.getElementById('modal-libur');

  function buka(btn) {
    document.getElementById('libur-id').value = btn.dataset.id;
    document.getElementById('libur-tanggal').value = btn.dataset.tanggal;
    document.getElementById('libur-keterangan').value = btn.dataset.keterangan;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function tutup() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-ubah-libur');
    if (btn) buka(btn);
  });
  document.getElementById('modal-libur-tutup').addEventListener('click', tutup);
  document.getElementById('modal-libur-batal').addEventListener('click', tutup);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutup(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ! modal.classList.contains('hidden')) tutup();
  });
})();
</script>
@endsection