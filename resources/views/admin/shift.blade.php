@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('jam') !!} Daftar Shift Kerja</h2></div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Kategori</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Lintas Hari</th>
            <th>Dipakai</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @foreach($shiftList as $s)
        <tr>
          <td><strong>Shift {{ $s->kategori }}</strong></td>
          <td class="angka">{{ jam_singkat($s->jam_masuk) }}</td>
          <td class="angka">{{ jam_singkat($s->jam_pulang) }}</td>
          <td>{{ $s->lintas_hari ? 'Ya (pulang keesokan hari)' : '—' }}</td>
          <td class="angka">{{ (int) $s->jml }} pegawai</td>
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

  <h3 style="margin-top:18px">Tambah Shift Baru</h3>
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
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('atur') !!} Izin Pemilihan Shift Mandiri</h2></div>
  <form method="post" action="{{ url('admin/shift/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="izin_pilih">
    <input type="hidden" name="qs" value="{{ $qs }}">
    <label class="teks-kecil" style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" name="izinkan" value="1" style="width:auto" {{ $izin ? 'checked' : '' }}>
      Pegawai dapat memilih/mengubah shiftnya sendiri melalui dasbor (terkunci otomatis setelah absen datang)
    </label>
    <button type="submit" class="btn btn-navy btn-kecil">Simpan</button>
  </form>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('pegawai') !!} Shift Aktif per Pegawai</h2></div>

  <form method="get" action="{{ url('admin/shift') }}" class="bilah-alat">
    <input type="text" name="q" placeholder="Cari nama pegawai…" value="{{ $q }}">
    <select name="unit">
      <option value="">Semua Unit</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Terapkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama Pegawai</th><th>Unit</th><th>Profesi</th><th style="min-width:240px">Shift Aktif</th></tr>
      </thead>
      <tbody>
        @foreach($pegawai as $p)
        <tr>
          <td><strong>{{ $p->nama_lengkap }}</strong></td>
          <td>{{ $p->unit_nama ?? '—' }}@if($p->sub_nama) — {{ $p->sub_nama }}@endif</td>
          <td>{{ $p->profesi_nama ?? '—' }}</td>
          <td>
            <form method="post" action="{{ url('admin/shift/aksi') }}" class="bilah-alat" style="margin:0">
              @csrf
              <input type="hidden" name="aksi" value="atur_pegawai">
              <input type="hidden" name="user_id" value="{{ (int) $p->id }}">
              <input type="hidden" name="qs" value="{{ $qs }}">
              <select name="shift_id">
                <option value="">— Belum diatur —</option>
                @foreach($shiftGrup as $kategori => $daftar)
                  <optgroup label="Shift {{ $kategori }}">
                    @foreach($daftar as $s)
                      <option value="{{ (int) $s->id }}"
                        {{ (int) $p->shift_id === (int) $s->id ? 'selected' : '' }}>
                        {{ jam_singkat($s->jam_masuk) }} - {{ jam_singkat($s->jam_pulang) }}
                      </option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
              <button type="submit" class="btn btn-primer btn-kecil">Simpan</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $pegawai)
        <tr><td colspan="4" class="tengah teks-redup">Tidak ada pegawai yang cocok.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
