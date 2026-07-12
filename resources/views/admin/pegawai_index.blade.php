@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Daftar Pegawai <span class="badge badge-biru">{{ count($pegawai) }}</span></h2>
    <a class="btn btn-primer btn-kecil" href="{{ url('admin/pegawai/form') }}">+ Tambah Pegawai</a>
  </div>

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
        <tr><th>Nama</th><th>Email</th><th>Unit / Sub Unit</th><th>Profesi</th>
            <th>Shift</th><th>Peran</th><th>Status</th><th>Aksi</th></tr>
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
</td>

<td>{{ $p->profesi_nama ?? '—' }}</td>

<td>
    {{
        label_shift(
            $p->shift_id
                ? (object) [
                    'kategori'  => $p->shift_kategori,
                    'jam_masuk' => $p->shift_masuk,
                    'jam_pulang'=> $p->shift_pulang,
                ]
                : null
        )
    }}
</td>

<td>
    {!! $p->role === 'admin'
        ? '<span class="badge badge-biru">Admin</span>'
        : 'Pegawai' !!}
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

            <form method="post" action="{{ url('admin/pegawai/status') }}" style="display:inline">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $p->id }}">

                <button type="submit" class="btn btn-garis btn-kecil">
                    {{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>

            <form method="post"
                  action="{{ url('admin/pegawai/hapus') }}"
                  style="display:inline"
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
        <tr><td colspan="8" class="tengah teks-redup">Tidak ada data pegawai.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
