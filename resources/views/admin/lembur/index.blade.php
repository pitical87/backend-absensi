@extends('layouts.admin')

@section('content')

<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.lembur.data') }}"
         data-url-aksi="{{ route('admin.lembur.proses') }}"
         data-status-aktif="{{ $status }}">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Pengajuan Lembur</h2>
    <span class="badge badge-amber"><span data-hitung="Menunggu">{{ $jumlah['Menunggu'] }}</span> menunggu</span>
  </div>

  @unless($lemburAktif)
    <div class="flash-info mb-4 teks-redup rounded-md p-4">
      Modul lembur sedang <strong>tidak aktif</strong>. Nyalakan pengaturan
      <em>“Aktifkan modul lembur”</em> di halaman Pengaturan untuk menerima pengajuan baru.
      Data di bawah tetap tampil untuk keperluan monitoring, namun aksi memproses dinonaktifkan.
    </div>
  @endunless

  <div class="w-full flex flex-wrap items-center gap-2 mb-3">
    <input type="text" data-cari value="{{ $q }}" autocomplete="off"
           placeholder="Cari nama / NIP / keterangan…" class="min-w-[200px]">
    
  </div>

  <div class="chips">
    @foreach(['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st)
      <a class="chip {{ $status === $st ? 'aktif' : '' }}" data-status="{{ $st }}"
         href="{{ route('admin.lembur.index', array_filter(['status' => $st, 'q' => $q])) }}">{{ $st }}</a>
    @endforeach
  </div>

  <div id="pesan-lembur" data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.lembur.tabel')
  </div>

 
</section>

@endsection