@extends('layouts.admin')

@section('content')
<section class="kartu">
  <div class="kartu-kepala flex items-center justify-between">
    <h2>{!! ikon('atur') !!} Master Jabatan</h2>
    <a href="{{ route('admin.jabatan.create') }}" class="btn btn-primer btn-kecil">{!! ikon('tambah', 15) !!} Tambah Jabatan</a>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Jabatan</th>
          <th>Kategori</th>
          <th>Atasan Langsung</th>
          <th>Label Unit</th>
          <th>Urutan</th>
          <th>Pegawai</th>
          <th class="w-[140px] tengah">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @if($jabatans->isEmpty())
          <tr><td colspan="7" class="tengah teks-redup py-6">Belum ada data jabatan.</td></tr>
        @else
          @foreach($jabatans as $j)
            <tr>
              <td>
                <div class="font-medium">{{ $j->nama }}</div>
                @if($j->anak->count() > 0)
                  <div class="teks-kecil teks-redup">{{ $j->anak->count() }} jabatan bawahan</div>
                @endif
              </td>
              <td>
                <span class="badge {{ in_array($j->kategori, ['Kepala Bidang', 'Kepala Bagian']) ? 'badge-biru' : (in_array($j->kategori, ['Kepala Seksi', 'Kepala Sub Bagian']) ? 'badge-hijau' : 'badge-amber') }}">
                  {{ $j->kategori }}
                </span>
              </td>
              <td class="teks-kecil">
                {{ $j->induk?->nama ?? '—' }}
              </td>
              <td class="teks-kecil">
                {{ $j->unit_label ?? '—' }}
              </td>
              <td class="tengah angka">{{ $j->urutan }}</td>
              <td class="tengah angka">
                {{ $j->users->count() }}
                @if($j->users->count() > 0)
                  <div class="teks-kecil teks-redup">
                    @foreach($j->users->take(2) as $u)
                      {{ $u->nama_lengkap }}@unless($loop->last), @endunless
                    @endforeach
                    @if($j->users->count() > 2) +{{ $j->users->count() - 2 }} lainnya@endif
                  </div>
                @endif
              </td>
              <td class="tengah">
                <div class="flex items-center justify-center gap-1">
                  <a href="{{ route('admin.jabatan.edit', $j) }}" class="btn btn-garis btn-kecil" title="Ubah">{!! ikon('ubah', 14) !!}</a>
                  <form method="post" action="{{ route('admin.jabatan.destroy', $j) }}" onsubmit="return confirm('Hapus jabatan {{ $j->nama }}?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-bahaya btn-kecil" title="Hapus">{!! ikon('hapus', 14) !!}</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>

  {{ $jabatans->links() }}
</section>
@endsection