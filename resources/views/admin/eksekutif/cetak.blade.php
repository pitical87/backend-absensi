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
  p.periode { text-align: center; font-size: 12px; margin-bottom: 14px; }
  .ringkasan { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin: 10px 0 16px; }
  .rk { border: 1px solid #9CC3E5; background: #E3F0FB; border-radius: 8px; padding: 8px 14px; min-width: 120px; text-align: center; }
  .rk strong { display: block; font-size: 17px; color: #0B3B66; font-variant-numeric: tabular-nums; }
  .rk span { font-size: 10px; color: #333; text-transform: uppercase; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #444; padding: 4px 6px; font-size: 10.5px; }
  th { background: #E3F0FB; color: #0B3B66; }
  td.angka, th.angka { text-align: center; font-variant-numeric: tabular-nums; }
  h3.judul2 { font-size: 12px; margin: 18px 0 2px; }
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
    Periode <strong>{{ $f['dari'] }} s/d {{ $f['sampai'] }}</strong>
  @endif
  &nbsp;·&nbsp; {{ $namaInstansi }}
</p>

@if($ringkasan)
<h3 class="judul2">Ringkasan Periode</h3>
<div class="ringkasan">
  @php $rr = $ringkasan; @endphp
  <div class="rk"><strong>{{ $rr['total_pegawai'] }}</strong><span>Pegawai</span></div>
  <div class="rk"><strong>{{ $rr['absensi']['hadir'] }}</strong><span>Kehadiran</span></div>
  <div class="rk"><strong>{{ $rr['absensi']['terlambat'] }}</strong><span>Terlambat</span></div>
  <div class="rk"><strong>{{ $rr['izin']['disetujui'] }}</strong><span>Izin/Cuti</span></div>
  <div class="rk"><strong>{{ number_format($rr['lembur']['total_jam'], 1, ',', '.') }}</strong><span>Jam Lembur</span></div>
  <div class="rk"><strong>{{ $rr['logbook']['jumlah'] }}</strong><span>Entri Logbook</span></div>
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
@else
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
