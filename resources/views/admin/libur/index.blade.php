@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kalender Hari Libur {{ (int) $tahun }}</h2>
    <form method="get" action="{{ url('admin/libur') }}" class="bilah-alat m-0">
      <input type="text" name="q" value="{{ $q }}" placeholder="Cari keterangan / tanggal…" class="min-w-[170px]">
      <select name="tahun" onchange="this.form.submit()">
        @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
          <option {{ $t === (int) $tahun ? 'selected' : '' }}>{{ $t }}</option>
        @endfor
      </select>
      <button type="submit" class="btn btn-navy btn-kecil">Cari</button>
    </form>
  </div>

  <form method="post" action="{{ url('admin/libur/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="tambah">
    <input type="date" name="tanggal" required>
    <input type="text" name="keterangan" placeholder="cth. Hari Kemerdekaan RI / Cuti Bersama…" required>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Hari Libur</button>
  </form>
  {{-- <p class="petunjuk">Tanggal yang terdaftar di sini tidak dihitung sebagai Alpa bagi pegawai yang
    tidak absen, sehingga rekap bulanan tetap adil. Pegawai yang tetap masuk pada hari libur
    tetap tercatat hadir beserta jam kerjanya.</p>
  <p class="petunjuk">Tanggal 1 Januari, 1 Mei, 1 Juni, 17 Agustus, dan 25 Desember
    <strong>otomatis tercatat setiap tahun</strong> (bertanda <span class="badge badge-teal teks-kecil">Otomatis</span>
    di bawah). Hari libur nasional/cuti bersama lain yang mengikuti penanggalan Hijriah, Imlek,
    Saka, atau Paskah (Idulfitri, Iduladha, Nyepi, Imlek, Waisak, dll.) baru diumumkan pemerintah
    lewat SKB 3 Menteri sekitar 3–4 bulan sebelum tahun berjalan, sehingga perlu ditambahkan manual
    begitu SKB terbit — tanggal tahun {{ (int) date('Y') }} sudah dimasukkan sejak pemasangan.</p> --}}

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th class="w-[100px]">Aksi</th></tr></thead>
      <tbody>
        @php $tetap = hari_libur_tetap((int) $tahun); @endphp
        @foreach($daftar as $h)
        <tr>
          <td class="angka">{{ $loop->iteration }}</td>
          <td class="angka">{{ tgl_id($h->tanggal) }}</td>
          <td>{{ $h->keterangan }}
            @if(isset($tetap[$h->tanggal->format('Y-m-d')]))
              <span class="badge badge-teal teks-kecil">Otomatis</span>
            @endif
          </td>
          <td class="whitespace-nowrap">
            <button type="button"
                    class="btn btn-garis btn-kecil btn-ubah-libur"
                    data-id="{{ (int) $h->id }}"
                    data-tanggal="{{ $h->tanggal->format('Y-m-d') }}"
                    data-keterangan="{{ $h->keterangan }}">Ubah</button>
            <form method="post" action="{{ url('admin/libur/aksi') }}" class="inline-block"
                  onsubmit="return confirm('Hapus hari libur ini?');">
              @csrf
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="{{ (int) $h->id }}">
              <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $daftar)
        <tr><td colspan="3" class="tengah teks-redup">Belum ada hari libur terdaftar pada tahun ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>


{{-- Modal Ubah Hari Libur --}}
<div id="modal-libur" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-sm">
    <div class="kartu-kepala">
      <h2>{!! ikon('kalender') !!} Ubah Hari Libur</h2>
      <button type="button" id="modal-libur-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <form method="post" action="{{ url('admin/libur/aksi') }}" class="px-3 pb-3 space-y-3">
      @csrf
      <input type="hidden" name="aksi" value="ubah">
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

@endsection

@section('script')
<script>
(function () {
  var modal     = document.getElementById('modal-libur');
  var tutupBtn  = document.getElementById('modal-libur-tutup');
  var batalBtn  = document.getElementById('modal-libur-batal');

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

  Array.prototype.forEach.call(document.querySelectorAll('.btn-ubah-libur'), function (btn) {
    btn.addEventListener('click', function () { buka(btn); });
  });
  if (tutupBtn) tutupBtn.addEventListener('click', tutup);
  if (batalBtn) batalBtn.addEventListener('click', tutup);
  if (modal) {
    modal.addEventListener('click', function (e) { if (e.target === modal) tutup(); });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ! modal.classList.contains('hidden')) tutup();
  });
})();
</script>
@endsection
