@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Daftar Pegawai <span class="badge badge-biru">{{ count($pegawai) }}</span></h2>
    <div class="flex items-center gap-2">
      <a class="btn btn-garis btn-kecil" href="{{ route('admin.pegawai.template') }}">{!! ikon('unduh', 15) !!} Template Excel</a>
      <a class="btn btn-primer btn-kecil" href="{{ url('admin/pegawai/form') }}">+ Tambah Pegawai</a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.pegawai.import') }}" enctype="multipart/form-data"
        class="bilah-alat" style="border-top:1px dashed var(--warna-garis);padding-top:12px;margin-top:4px">
    @csrf
    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    <button type="submit" class="btn btn-navy btn-kecil">{!! ikon('unduh', 15) !!} Import Excel</button>
    <span class="teks-kecil teks-redup">
      Kolom mengikuti template. Baris dengan email yang sudah terdaftar akan dilewati.
    </span>
  </form>

  <form method="get" action="{{ url('admin/pegawai') }}" class="bilah-alat">
    <input type="text" name="q" placeholder="Cari nama / email…" value="{{ $q }}">
   <select name="unit">
    <option value="">Semua Unit</option>

    @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}"
            {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
            {{ $uk->nama }}
        </option>
    @endforeach
</select>
    <button type="submit" class="btn btn-navy btn-kecil">Terapkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama</th><th>Email</th><th>Unit / Sub Unit</th>
            <th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @foreach($pegawai as $p)
        <tr>
          <td><strong>{{ $p->nama_lengkap }}</strong>
            <br><span class="teks-kecil teks-redup">{{ $p->jabatan_nama
                  ?? ($p->jabatan_kategori ?? 'Staf/Pelaksana') }}@if(
                  ! empty($p->nip)) · NIP {{ $p->nip }}@endif</span>
            @if(! empty($p->posisi) && $p->posisi !== 'Staf')
              <br><span class="badge badge-ungu teks-kecil">{{ $p->posisi }}</span>
            @endif
            @if(($p->status_pegawai ?? '') === 'PNS')
              <span class="badge badge-teal teks-kecil">PNS</span>
            @endif</td>
          <td>{{ $p->email }}</td>

<td>
    {{ $p->unit_nama ?? '—' }}
    @if($p->sub_nama)
        — {{ $p->sub_nama }}
    @endif
    <br><span class="badge badge-hijau">{{ $p->profesi_nama ?? '—' }}</span>
</td>

<td>
    {!! $p->status === 'aktif'
        ? '<span class="badge badge-hijau">Aktif</span>'
        : '<span class="badge badge-abu">Nonaktif</span>' !!}
</td>

<td>
    <div class="aksi-baris">
        <a class="btn btn-garis btn-kecil"
           href="{{ url('admin/pegawai/form/' . (int) $p->id) }}">
            Ubah
        </a>

        @if((int) $p->id !== (int) session('uid'))

            <form method="post" action="{{ url('admin/pegawai/status') }}">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $p->id }}">

                <button type="submit" class="btn btn-garis btn-kecil">
                    {{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>

            <form method="post"
                  action="{{ url('admin/pegawai/hapus') }}"
                  onsubmit="return confirm('Hapus permanen {{ $p->nama_lengkap }}?\nSeluruh data absensinya ikut terhapus dan tidak dapat dikembalikan.');">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $p->id }}">

                <button type="submit" class="btn btn-bahaya btn-kecil">
                    Hapus
                </button>
            </form>

        @endif
    </div>
</td>
        </tr>
        @endforeach
        @if(! $pegawai)
        <tr><td colspan="5" class="tengah teks-redup">Tidak ada data pegawai.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
