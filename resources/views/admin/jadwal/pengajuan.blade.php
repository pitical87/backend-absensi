@extends('layouts.admin')

@section('content')

<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.jadwal.pengajuan.data') }}"
         data-url-aksi="{{ route('admin.jadwal_pengajuan.proses') }}"
         data-status-aktif="{{ $status }}">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Pengajuan Perubahan Jadwal Shift</h2>
    <span class="badge badge-amber"><span data-hitung="Menunggu">{{ $jumlah['Menunggu'] }}</span> menunggu</span>
  </div>

  <div class="w-full flex flex-wrap items-center gap-2 mb-3">
    <input type="text" data-cari value="{{ $q }}" autocomplete="off"
           placeholder="Cari nama / NIP / alasan…" class="min-w-[200px]">
    
  </div>

  <div class="chips">
    @foreach(['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st)
      <a class="chip {{ $status === $st ? 'aktif' : '' }}" data-status="{{ $st }}"
         href="{{ route('admin.jadwal.pengajuan', array_filter(['status' => $st, 'q' => $q])) }}">{{ $st }}</a>
    @endforeach
  </div>

  <div id="pesan-jadwal" data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.jadwal.pengajuan_tabel')
  </div>


</section>

@endsection