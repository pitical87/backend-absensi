@extends('layouts.admin')

@section('content')

<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.lupa_absen.data') }}"
         data-url-aksi="{{ route('admin.lupa_absen.proses') }}"
         data-status-aktif="{{ $status }}">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Pengajuan Lupa Absen</h2>
    <span class="badge badge-amber"><span data-hitung="Menunggu">{{ $jumlah['Menunggu'] }}</span> menunggu</span>
  </div>

  <div class="petunjuk mb-3">
    Pengajuan lupa absen berasal dari pegawai yang memiliki jadwal kerja namun tercatat tidak absen/alpa dalam 1 bulan terakhir.
    Saat <strong>disetujui</strong>, kehadiran otomatis disimpan tanpa foto, tanpa koordinat, dan mendapat <strong>bintang 1</strong> untuk absen masuk dan absen pulang.
  </div>

  <div class="w-full flex flex-wrap items-center gap-2 mb-3">
    <input type="text" data-cari value="{{ $q }}" autocomplete="off"
           placeholder="Cari nama / NIP / alasan…" class="min-w-[200px]">
  </div>

  <div class="chips">
    @foreach(['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st)
      <a class="chip {{ $status === $st ? 'aktif' : '' }}" data-status="{{ $st }}"
         href="{{ route('admin.lupa_absen.index', array_filter(['status' => $st, 'q' => $q])) }}">{{ $st }}</a>
    @endforeach
  </div>

  <div id="pesan-lupa-absen" data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.lupa_absen.tabel')
  </div>
</section>

@endsection
