@extends('layouts.admin')

@php
  $kategori = match (true) {
      ! $hasil['sukses'] => ['Gagal', 'badge-merah'],
      $hasil['ms_total'] < 100 => ['Cepat', 'badge-hijau'],
      $hasil['ms_total'] < 500 => ['Sedang', 'badge-amber'],
      default => ['Lambat', 'badge-merah'],
  };
@endphp

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>Cek Koneksi SIMRS</h2>
    <a href="{{ route('admin.simrs.koneksi') }}" class="btn btn-garis btn-kecil">{!! ikon('centang', 14) !!} Cek Ulang</a>
  </div>

  <div class="p-4">

    @if($hasil['sukses'])
      <div class="flex items-center gap-2 mb-4 flex-wrap">
        {!! ikon('centang', 20) !!}
        <strong>Terhubung ke SIMRS</strong>
        <span class="badge {{ $kategori[1] }}">{{ $kategori[0] }} &middot; {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms</span>
        <span class="teks-redup teks-kecil">Dicek {{ now()->translatedFormat('d/m/Y H:i:s') }}</span>
      </div>
    @else
      <div class="flex items-center gap-2 mb-4 flex-wrap">
        {!! ikon('silang', 20) !!}
        <strong>Gagal terhubung ke SIMRS</strong>
        <span class="teks-redup teks-kecil">Dicek {{ now()->translatedFormat('d/m/Y H:i:s') }}</span>
      </div>
      <p class="text-red-600 text-sm mb-1">{{ $hasil['pesan'] ?? 'Koneksi tidak dapat dibuat.' }}</p>
      <p class="teks-redup teks-kecil mb-4">Dibatalkan setelah {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms (timeout {{ $timeout }} detik).</p>
    @endif

    <div class="tabel-bungkus">
      <table class="tabel">
        <tbody>
          <tr>
            <td style="width:220px" class="teks-redup">Host</td>
            <td><code>{{ $hasil['host'] }}</code></td>
          </tr>
          
          @if($hasil['sukses'])
          <tr>
            <td class="teks-redup">Waktu koneksi</td>
            <td>{{ number_format($hasil['ms_koneksi'], 0, ',', '.') }} ms</td>
          </tr>
          <tr>
            <td class="teks-redup">Respons query</td>
            <td>{{ number_format($hasil['ms_query'], 0, ',', '.') }} ms</td>
          </tr>
          <tr>
            <td class="teks-redup">Total kecepatan</td>
            <td>
              {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms
              <span class="badge {{ $kategori[1] }}">{{ $kategori[0] }}</span>
            </td>
          </tr>
          <tr>
            <td class="teks-redup">Versi server MySQL</td>
            <td><code>{{ $hasil['versi_server'] }}</code></td>
          </tr>
          <tr>
            <td class="teks-redup">Waktu pada server SIMRS</td>
            <td>{{ \Carbon\Carbon::parse($hasil['waktu_server'])->translatedFormat('d/m/Y H:i:s') }}
              @if(abs(now()->getTimestamp() - \Carbon\Carbon::parse($hasil['waktu_server'])->getTimestamp()) > 60)
                <span class="badge badge-amber">Selisih jam dengan aplikasi</span>
              @endif
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>

  </div>
</section>

@endsection
