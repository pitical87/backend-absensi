@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Rekap Lembur Bulan {{ BULAN_ID[$bulan] . ' ' . $tahun }}</h2>
    <span class="badge badge-biru">{{ count($pegawai) }} pegawai</span>
  </div>

  <form method="get" action="{{ url('admin/rekap_lembur') }}" class="bilah-alat flex-wrap">
    <select name="bulan">
      @foreach(BULAN_ID as $i => $nb)
        <option value="{{ $i }}" {{ $i === $bulan ? 'selected' : '' }}>{{ $nb }}</option>
      @endforeach
    </select>
    <select name="tahun">
      @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
        <option {{ $t === $tahun ? 'selected' : '' }}>{{ $t }}</option>
      @endfor
    </select>
    <select name="unit">
      <option value="">Semua Unit Kerja</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>{{ $uk->nama }}</option>
      @endforeach
    </select>
    <select name="jab" title="Kategori jabatan">
      <option value="">Semua Jabatan</option>
      @foreach($kategoriJab as $k)
        <option {{ $f['jab'] === $k ? 'selected' : '' }}>{{ $k }}</option>
      @endforeach
    </select>
    <select name="njab" title="Nama jabatan tertentu">
      <option value="">Semua Nama Jabatan</option>
      @foreach($jabPilihan as $kat => $daftar)
        <optgroup label="{{ $kat }}">
          @foreach($daftar as $j)
            <option value="{{ (int) $j['id'] }}" {{ $f['njab'] === (int) $j['id'] ? 'selected' : '' }}>{{ $j['nama'] }}</option>
          @endforeach
        </optgroup>
      @endforeach
    </select>
    <select name="org" title="Bidang / Bagian pada struktur organisasi">
      <option value="">Semua Bidang/Bagian</option>
      @foreach($orgList as $o)
        <option value="{{ (int) $o['id'] }}" {{ $f['org'] === (int) $o['id'] ? 'selected' : '' }}>{{ $o['unit_label'] }}</option>
      @endforeach
    </select>
    <select name="prof">
      <option value="">Semua Profesi</option>
      @foreach($profList as $pr)
        <option value="{{ (int) $pr->id }}" {{ $f['prof'] === (int) $pr->id ? 'selected' : '' }}>{{ $pr->nama }}</option>
      @endforeach
    </select>
    <input type="text" name="q" placeholder="Cari nama / NIP" value="{{ $f['q'] }}">
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="aksi-baris mb-3.5">
    <a class="btn btn-garis btn-kecil" href="{{ url('admin/rekap_lembur/cetak?' . $qs) }}">{!! ikon('cetak', 15) !!} Cetak Laporan (PDF)</a>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Pegawai</th><th>NIP</th><th>Unit / Sub Unit</th>
          <th class="angka">Pengajuan Disetujui</th><th class="angka">Total Hari</th>
          <th class="angka">Total Jam</th><th class="angka">Hari Absen Aktual</th>
          <th class="angka">Jam Aktual (AbsenLembur)</th>
        </tr>
      </thead>
      <tbody>
        @php $no = 1; @endphp
        @foreach($pegawai as $p)
        @php $r = $rekapPer[(int) $p->id]; @endphp
        <tr>
          <td><strong>{{ $p->nama_lengkap }}</strong>@if($p->profesi_nama)<br><span class="teks-redup teks-kecil">{{ $p->profesi_nama }}</span>@endif</td>
          <td>{{ $p->nip ?: '—' }}</td>
          <td>{{ $p->unit_nama }}@if($p->sub_nama)<br><span class="teks-redup teks-kecil">{{ $p->sub_nama }}</span>@endif</td>
          <td class="angka">{{ $r['jumlah_pengajuan'] }}</td>
          <td class="angka">{{ $r['jumlah_hari'] }}</td>
          <td class="angka">{{ number_format($r['total_jam'], 1, ',', '.') }}</td>
          <td class="angka">{{ $r['jumlah_hari_aktual'] }}</td>
          <td class="angka">{{ $r['total_menit_aktual'] > 0 ? number_format($r['total_menit_aktual'] / 60, 1, ',', '.') : '—' }}</td>
        </tr>
        @endforeach
        @if($pegawai)
        <tr class="tebal">
          <td colspan="5" class="tengah teks-redup">Total Jam Lembur (disetujui)</td>
          <td class="angka"><strong>{{ number_format(array_sum(array_column($rekapPer, 'total_jam')), 1, ',', '.') }}</strong></td>
          <td class="angka" colspan="2">&nbsp;</td>
        </tr>
        @endif
        @if(! $pegawai)
        <tr><td colspan="8" class="tengah teks-redup">Belum ada data.</td></tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($totalHal > 1)
  <div class="paginasi">
    @php
      $dasar = url('admin/rekap_lembur?' . $qs);
      $pisah = str_contains($dasar, '?') ? '&' : '?';
    @endphp
    @for($h = max(1, $halaman - 3); $h <= min($totalHal, $halaman + 3); $h++)
      @if($h === $halaman)
        <span class="aktif">{{ $h }}</span>
      @else
        <a href="{{ $dasar . $pisah . 'hal=' . $h }}">{{ $h }}</a>
      @endif
    @endfor
    <span class="info">hal. {{ $halaman }} / {{ $totalHal }}</span>
  </div>
  @endif
</section>

@endsection
