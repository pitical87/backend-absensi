@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('gedung') !!} Tambah Unit Kerja</h2></div>
  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="tambah_unit">
    <input type="text" name="nama" placeholder="Nama unit kerja baru…" required>
    <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="punya_sub" value="1" class="w-auto"> Memiliki sub unit
    </label>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah</button>
  </form>
</section>

@foreach($unitList as $uk)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('gedung') !!} {{ $uk->nama }}
      <span class="badge badge-biru">{{ (int) $uk->jml_pegawai }} pegawai</span></h2>
    <form method="post" action="{{ url('admin/unit/aksi') }}"
          onsubmit="return confirm('Hapus unit {{ $uk->nama }} beserta seluruh sub unitnya?');">
      @csrf
      <input type="hidden" name="aksi" value="hapus_unit">
      <input type="hidden" name="id" value="{{ (int) $uk->id }}">
      <button type="submit" class="btn btn-bahaya btn-kecil">Hapus Unit</button>
    </form>
  </div>

  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="ubah_unit">
    <input type="hidden" name="id" value="{{ (int) $uk->id }}">
    <input type="text" name="nama" value="{{ $uk->nama }}" required>
    <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="punya_sub" value="1" class="w-auto" {{ $uk->punya_sub ? 'checked' : '' }}>
      Memiliki sub unit
    </label>
    <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
  </form>

  @if($uk->punya_sub)
    <h3 class="mt-4">Sub Unit</h3>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Nama Sub Unit</th><th>Jumlah Pegawai</th><th class="w-[110px]">Aksi</th></tr></thead>
        <tbody>
          @foreach($subPerUnit[(int) $uk->id] ?? [] as $su)
          <tr>
            <td>{{ $su->nama }}</td>
            <td class="angka">{{ (int) $su->jml_pegawai }}</td>
            <td>
              <form method="post" action="{{ url('admin/unit/aksi') }}"
                    onsubmit="return confirm('Hapus sub unit {{ $su->nama }}?');">
                @csrf
                <input type="hidden" name="aksi" value="hapus_sub">
                <input type="hidden" name="id" value="{{ (int) $su->id }}">
                <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            </td>
          </tr>
          @endforeach
          @if(empty($subPerUnit[(int) $uk->id]))
          <tr><td colspan="3" class="tengah teks-redup">Belum ada sub unit.</td></tr>
          @endif
        </tbody>
      </table>
    </div>

    <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat mt-3">
      @csrf
      <input type="hidden" name="aksi" value="tambah_sub">
      <input type="hidden" name="unit_kerja_id" value="{{ (int) $uk->id }}">
      <input type="text" name="nama" placeholder="Nama sub unit baru untuk {{ $uk->nama }}…" required>
      <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Sub Unit</button>
    </form>
  @endif
</section>
@endforeach

@endsection
