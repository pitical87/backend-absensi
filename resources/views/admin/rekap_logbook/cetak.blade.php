<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekap Logbook Pegawai {{ BULAN_ID[$bulan] . ' ' . $tahun }} — {{ $namaInstansi }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font: 12px/1.5 "Segoe UI", Arial, sans-serif; color: #111; padding: 28px; }
  .kop { display: flex; align-items: center; gap: 14px; border-bottom: 3px double #0B3B66;
         padding-bottom: 12px; margin-bottom: 4px; }
  .kop img { width: 62px; height: 62px; }
  .kop h1 { font-size: 17px; color: #0B3B66; letter-spacing: .4px; }
  .kop p  { font-size: 11px; color: #333; }
  h2.judul { text-align: center; font-size: 14px; margin: 16px 0 2px; text-transform: uppercase;
             text-decoration: underline; }
  p.periode { text-align: center; font-size: 12px; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #444; padding: 4px 6px; font-size: 10.5px; }
  th { background: #E3F0FB; color: #0B3B66; }
  td.angka, th.angka { text-align: center; font-variant-numeric: tabular-nums; }
  .ttd { margin-top: 34px; display: flex; justify-content: flex-end; }
  .ttd .blok { text-align: center; font-size: 12px; width: 280px; }
  .ttd .spasi { height: 70px; }
  .bar-cetak { margin-bottom: 16px; }
  .bar-cetak button { background: #1568B8; color: #fff; border: 0; padding: 9px 18px;
                      border-radius: 8px; font-size: 13px; cursor: pointer; }
  @media print { .bar-cetak { display: none; } body { padding: 0; } }
  @page { size: A4 portrait; margin: 12mm; }
</style>
</head>
<body>

<div class="bar-cetak">
  <button type="button" onclick="window.print()">&#128424; Cetak / Simpan sebagai PDF</button>
  <span style="font-size:11px;color:#555;margin-left:8px">
    Pada dialog cetak, pilih tujuan <strong>"Save as PDF"</strong> untuk menyimpan berkas PDF.
  </span>
</div>

<div class="kop">
  <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
  <div>
    <h1>PEMERINTAH KABUPATEN MERAUKE<br>{{ strtoupper($namaInstansi) }}</h1>
    <p>Sistem Absensi Pegawai — Rekapitulasi Logbook</p>
  </div>
</div>

<h2 class="judul">Rekap Logbook per Pegawai</h2>
<p class="periode">Periode: <strong>{{ BULAN_ID[$bulan] . ' ' . $tahun }}</strong>@if($q)
  &nbsp;·&nbsp; Pencarian: <strong>"{{ $q }}"</strong>@endif &nbsp;·&nbsp;
  Jumlah pegawai: <strong>{{ count($daftar) }}</strong></p>

<table>
  <thead>
    <tr>
      <th class="angka" style="width:36px">No</th>
      <th>Nama Pegawai</th>
      <th style="width:140px">NIP</th>
      <th>Unit / Bidang</th>
      <th class="angka" style="width:110px">Jumlah Hari Kerja</th>
    </tr>
  </thead>
  <tbody>
    @php $no = 1; @endphp
    @foreach($daftar as $r)
    <tr>
      <td class="angka">{{ $no++ }}</td>
      <td>{{ $r->nama_lengkap }}</td>
      <td class="angka">{{ $r->nip ?: '—' }}</td>
      <td>{{ $r->unit_nama }}@if($r->sub_nama) — {{ $r->sub_nama }}@endif</td>
      <td class="angka">{{ (int) $r->jumlah_hari }}</td>
    </tr>
    @endforeach
    @if(! $daftar)
    <tr><td colspan="5" style="text-align:center;color:#666">Tidak ada data.</td></tr>
    @endif
  </tbody>
</table>

<div class="ttd">
  <div class="blok">
    Merauke, {{ tgl_id(now()->format('Y-m-d'), false) }}<br>
    Direktur {{ $namaInstansi }}
    <div class="spasi"></div>
    <strong>(______________________________)</strong><br>
    NIP.
  </div>
</div>

</body>
</html>
