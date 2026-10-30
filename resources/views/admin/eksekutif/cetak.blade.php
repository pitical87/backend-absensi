<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Eksekutif — {{ $namaInstansi }}</title>
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
  p.periode { text-align: center; font-size: 12px; margin-bottom: 2px; }
  p.filter { text-align: center; font-size: 10.5px; color: #444; margin-bottom: 14px; }
  .ringkasan-tabel { width: 60%; margin-top: 8px; }
  .ringkasan-tabel td:first-child { width: 70%; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #444; padding: 4px 6px; font-size: 10.5px; }
  th { background: #E3F0FB; color: #0B3B66; }
  td.angka, th.angka { text-align: center; font-variant-numeric: tabular-nums; }
  h3.judul2 { font-size: 12px; margin: 18px 0 2px; }
  .grafik-cetak { display: flex; align-items: flex-end; gap: 3px; height: 150px;
                  border-bottom: 1px solid #555; padding-top: 4px; margin: 6px 0 4px;
                  page-break-inside: avoid; }
  .g-kolom { flex: 1; min-width: 0; height: 100%; display: flex; flex-direction: column;
             align-items: center; justify-content: flex-end; gap: 2px; }
  .g-nilai { font-size: 7px; line-height: 1; font-variant-numeric: tabular-nums; }
  .g-batang { width: 100%; min-height: 1px; background: #1568B8; border-radius: 2px 2px 0 0;
              -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .g-label { font-size: 7px; line-height: 1.1; white-space: nowrap; }
  .ttd { margin-top: 34px; display: flex; justify-content: flex-end; }
  .ttd .blok { text-align: center; font-size: 12px; width: 280px; }
  .ttd .spasi { height: 70px; }
  .bar-cetak { margin-bottom: 16px; }
  .bar-cetak button { background: #1568B8; color: #fff; border: 0; padding: 9px 18px;
                      border-radius: 8px; font-size: 13px; cursor: pointer; }
  @media print { .bar-cetak { display: none; } body { padding: 0; } }
  .baru { page-break-before: always; }
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
    <p>Sistem Absensi Pegawai — Laporan Eksekutif</p>
  </div>
</div>

<h2 class="judul">Laporan Eksekutif Kepegawaian</h2>
<p class="periode">
  @if($f['mode'] === 'tren')
    Tren kehadiran, izin, lembur & logbook tahun <strong>{{ $f['tahun'] }}</strong>
  @else
    Periode <strong>{{ $labelPeriode }}</strong>
  @endif
  &nbsp;·&nbsp; {{ $namaInstansi }}
</p>
<p class="filter">Filter: <strong>{{ $labelFilter }}</strong></p>

@if($ringkasan)
<h3 class="judul2">Ringkasan Periode</h3>
@php $rr = $ringkasan; @endphp
<table class="ringkasan-tabel">
  <tbody>
    <tr><td>Pegawai</td><td class="angka">{{ $rr['total_pegawai'] }}</td></tr>
    <tr><td>Kehadiran</td><td class="angka">{{ $rr['absensi']['hadir'] }}</td></tr>
    <tr><td>Terlambat</td><td class="angka">{{ $rr['absensi']['terlambat'] }}</td></tr>
    <tr><td>Izin/Cuti</td><td class="angka">{{ $rr['izin']['disetujui'] }}</td></tr>
    <tr><td>Jam Lembur</td><td class="angka">{{ number_format($rr['lembur']['total_jam'], 1, ',', '.') }}</td></tr>
    <tr><td>Entri Logbook</td><td class="angka">{{ $rr['logbook']['jumlah'] }}</td></tr>
  </tbody>
</table>
@endif

@php
  $grafik = [];
  if ($f['mode'] === 'tren') {
      $judulGrafik = 'Grafik Kehadiran per Bulan — '.$f['tahun'];
      foreach ($tren as $t) {
          $grafik[] = ['label' => $t['label'], 'nilai' => $t['hadir'], 'judul' => $t['label']];
      }
  } elseif (in_array($f['mode'], ['bulan', 'minggu'], true)) {
      $judulGrafik = 'Grafik Kehadiran Harian — '.$labelPeriode;
      foreach ($harian as $h) {
          $grafik[] = [
              'label' => $f['mode'] === 'bulan' ? (int) date('j', strtotime($h['tanggal'])) : substr($h['hari'], 0, 3),
              'nilai' => $h['hadir'],
              'judul' => $h['hari'].', '.$h['label'],
          ];
      }
  }
  $maksGrafik = $grafik ? max(1, ...array_column($grafik, 'nilai')) : 1;
@endphp

@if($grafik)
<h3 class="judul2">{{ $judulGrafik }}</h3>
<div class="grafik-cetak">
  @foreach($grafik as $g)
  <div class="g-kolom">
    <span class="g-nilai">{{ $g['nilai'] }}</span>
    <div class="g-batang" style="height:{{ round($g['nilai'] * 100 / $maksGrafik) }}%" title="{{ $g['judul'] }}: {{ $g['nilai'] }} hadir"></div>
    <span class="g-label">{{ $g['label'] }}</span>
  </div>
  @endforeach
</div>
@endif

@if($f['mode'] === 'tren')
<h3 class="judul2">Rincian Tren per Bulan — {{ $f['tahun'] }}</h3>
<table>
  <thead>
    <tr>
      <th>Bulan</th><th class="angka">Hadir</th><th class="angka">Terlambat</th>
      <th class="angka">Izin/Cuti</th><th class="angka">Jam Lembur</th><th class="angka">Entri Logbook</th>
    </tr>
  </thead>
  <tbody>
    @foreach($tren as $t)
    <tr>
      <td>{{ $t['label'] }}</td>
      <td class="angka">{{ $t['hadir'] }}</td>
      <td class="angka">{{ $t['terlambat'] }}</td>
      <td class="angka">{{ $t['izin'] }}</td>
      <td class="angka">{{ number_format($t['jam_lembur'], 1, ',', '.') }}</td>
      <td class="angka">{{ $t['logbook'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@elseif($f['mode'] === 'unit')
<h3 class="judul2">Perbandingan Antar Unit Kerja</h3>
<table>
  <thead>
    <tr>
      <th>Unit Kerja</th><th class="angka">Pegawai</th><th class="angka">Hadir</th>
      <th class="angka">Terlambat</th><th class="angka">Izin/Cuti</th>
      <th class="angka">Jam Lembur</th><th class="angka">Entri Logbook</th>
    </tr>
  </thead>
  <tbody>
    @foreach($perUnit as $u)
    <tr>
      <td>{{ $u['unit_nama'] }}</td>
      <td class="angka">{{ $u['total_pegawai'] }}</td>
      <td class="angka">{{ $u['hadir'] }}</td>
      <td class="angka">{{ $u['terlambat'] }}</td>
      <td class="angka">{{ $u['izin'] }}</td>
      <td class="angka">{{ number_format($u['jam_lembur'], 1, ',', '.') }}</td>
      <td class="angka">{{ $u['logbook'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@else
<h3 class="judul2">Rincian Harian — {{ $labelPeriode }}</h3>
<table>
  <thead>
    <tr>
      <th>Tanggal</th><th class="angka">Hadir</th><th class="angka">Tepat</th>
      <th class="angka">Terlambat</th><th class="angka">Izin/Cuti</th>
      <th class="angka">Jam Lembur</th><th class="angka">Entri Logbook</th>
    </tr>
  </thead>
  <tbody>
    @foreach($harian as $h)
    <tr>
      <td>{{ $h['hari'] }}, {{ $h['label'] }}</td>
      <td class="angka">{{ $h['hadir'] }}</td>
      <td class="angka">{{ $h['tepat'] }}</td>
      <td class="angka">{{ $h['terlambat'] }}</td>
      <td class="angka">{{ $h['izin'] }}</td>
      <td class="angka">{{ number_format($h['jam_lembur'], 1, ',', '.') }}</td>
      <td class="angka">{{ $h['logbook'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

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
