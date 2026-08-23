@extends('layouts.admin')

@section('content')

<section class="kartu">
 
 <div class="kartu-kepala"><h2>{!! ikon('jam') !!} Tambah Shift</h2></div>
  <form method="post" action="{{ url('admin/shift/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="tambah_shift">
    <input type="hidden" name="qs" value="{{ $qs }}">
    <select name="kategori" required>
      <option value="">Kategori…</option>
      <option>Pagi</option><option>Sore</option><option>Malam</option>
    </select>
    <input type="time" name="jam_masuk" required title="Jam masuk">
    <input type="time" name="jam_pulang" required title="Jam pulang">
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah</button>
  </form>
  <p class="petunjuk">Bila jam pulang lebih kecil atau sama dengan jam masuk, shift otomatis ditandai
    <em>lintas hari</em> (berakhir keesokan harinya), mis. 21.00 - 08.00.</p>
   
  <div class="kartu-kepala mt-3"><h2>{!! ikon('jam') !!} Daftar Shift Kerja</h2></div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Kategori</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Lintas Hari</th>
            <th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @foreach($shiftList as $s)
        <tr>
          <td><strong>Shift {{ $s->kategori }}</strong></td>
          <td class="angka">{{ $s->jam_masuk->format('H:i') }}</td>
          <td class="angka">{{ $s->jam_pulang->format('H:i') }}</td>
          <td>{{ $s->lintas_hari ? 'Ya (pulang keesokan hari)' : '—' }}</td>
          <td>{!! $s->aktif
                ? '<span class="badge badge-hijau">Aktif</span>'
                : '<span class="badge badge-abu">Nonaktif</span>' !!}</td>
          <td>
            <div class="aksi-baris">
              <form method="post" action="{{ url('admin/shift/aksi') }}">
                @csrf
                <input type="hidden" name="aksi" value="toggle_shift">
                <input type="hidden" name="id" value="{{ (int) $s->id }}">
                <input type="hidden" name="qs" value="{{ $qs }}">
                <button type="submit" class="btn btn-garis btn-kecil">
                  {{ $s->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
              <form method="post" action="{{ url('admin/shift/aksi') }}"
                    onsubmit="return confirm('Hapus shift ini?');">
                @csrf
                <input type="hidden" name="aksi" value="hapus_shift">
                <input type="hidden" name="id" value="{{ (int) $s->id }}">
                <input type="hidden" name="qs" value="{{ $qs }}">
                <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('atur') !!} Izin Pemilihan Shift Mandiri</h2></div>
  <form method="post" action="{{ url('admin/shift/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="izin_pilih">
    <input type="hidden" name="qs" value="{{ $qs }}">
    <label class="teks-kecil flex items-center gap-2">
      <input type="checkbox" name="izinkan" value="1" class="w-auto" {{ $izin ? 'checked' : '' }}>
      Pegawai dapat memilih/mengubah shiftnya sendiri melalui dasbor (terkunci otomatis setelah absen datang)
    </label>
    <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
  </form>
</section>
@endsection
