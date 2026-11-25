<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>{{ $matriks['judul'] }} — {{ $matriks['nama_bulan'] }} {{ $matriks['tahun'] }}</title>
<style>
  @page {
    size: A4 landscape;
    margin: 8mm 10mm 9mm 10mm;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: Helvetica, Arial, "DejaVu Sans", sans-serif;
    font-size: 7.5pt;
    color: #000000;
    line-height: 1.15;
  }

  /* KOP SURAT */
  table.kop {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 2.5px double #000000;
    margin-bottom: 6px;
  }

  table.kop td {
    border: none;
    vertical-align: middle;
    padding: 0 4px 5px 4px;
  }

  table.kop td.logo-kiri,
  table.kop td.logo-kanan {
    width: 14%;
    text-align: center;
  }

  table.kop td.kop-tengah {
    width: 72%;
    text-align: center;
  }

  table.kop img {
    height: 54px;
    width: auto;
  }

  .kop-baris {
    font-size: 10pt;
    font-weight: bold;
    letter-spacing: 0.2px;
    line-height: 1.2;
  }

  .kop-alamat {
    font-size: 8pt;
    font-weight: bold;
    line-height: 1.25;
  }

  .judul-laporan {
    text-align: center;
    font-size: 9.5pt;
    font-weight: bold;
    margin: 6px 0 3px 0;
    letter-spacing: 0.25px;
  }

  .periode-baris {
    font-size: 8pt;
    font-weight: bold;
    margin: 0 0 4px 0;
  }

  /* TABEL MATRIKS */
  table.matriks {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 8px;
  }

  table.matriks thead {
    display: table-header-group;
  }

  table.matriks th,
  table.matriks td {
    border: 0.6px solid #000000;
    padding: 2px 0.5px;
    text-align: center;
    vertical-align: middle;
    word-wrap: break-word;
  }

  table.matriks th {
    background-color: #E5E7EB;
    font-weight: bold;
    font-size: 6.8pt;
  }

  table.matriks th.merah {
    background-color: #C00000;
    color: #FFFFFF;
  }

  table.matriks tr.grup-baris td {
    background-color: #F3F4F6;
    text-align: left;
    font-weight: bold;
    font-size: 7.2pt;
    padding: 3px 5px;
    letter-spacing: 0.2px;
  }

  table.matriks td.kol-no {
    font-size: 6.8pt;
  }

  table.matriks td.kol-nama {
    text-align: left;
    font-weight: bold;
    font-size: 6.6pt;
    padding: 2px 3px;
    width: 48mm;
  }

  table.matriks td.kol-ket {
    text-align: left;
    font-size: 6.2pt;
    padding: 2px 3px;
  }

  table.matriks td.sel-hadir {
    color: #15803D;
    font-size: 6.4pt;
  }

  table.matriks td.sel-libur {
    font-weight: bold;
    color: #111827;
    background-color: #F9FAFB;
    font-size: 6.6pt;
  }

  table.matriks td.sel-alpa {
    font-weight: bold;
    color: #C00000;
    font-size: 6.6pt;
  }

  table.matriks td.sel-izin,
  table.matriks td.sel-dinas {
    font-weight: bold;
    color: #1D4ED8;
    font-size: 6.4pt;
  }

  table.matriks td.sel-gabung {
    background-color: #EFF6FF;
    font-size: 6.3pt;
    letter-spacing: 0.1px;
  }

  .legenda {
    font-size: 7pt;
    font-style: italic;
    margin-top: 2px;
    margin-bottom: 10px;
  }

  table.ttd {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    page-break-inside: avoid;
  }

  table.ttd td {
    border: none;
    vertical-align: top;
    font-size: 8pt;
  }

  table.ttd td.kanan {
    width: 34%;
    text-align: center;
  }

  .ttd-spasi {
    height: 46px;
  }
</style>
</head>
<body>

<table class="kop">
  <tr>
    <td class="logo-kiri">
      @if($logoKiri)<img src="{{ $logoKiri }}" alt="Logo Merauke">@endif
    </td>
    <td class="kop-tengah">
      <div class="kop-baris">DINAS KESEHATAN</div>
      <div class="kop-baris">RUMAH SAKIT UMUM DAERAH KELAS C</div>
      <div class="kop-baris">MERAUKE</div>
      <div class="kop-alamat">Jalan Soekarjo Wiryopranoto No. 1-Kelurahan Karang Indah-Merauke</div>
      <div class="kop-alamat">Pos-el : rsudmerauke1@gmail.com. Kode Pos : 99614</div>
    </td>
    <td class="logo-kanan">
      @if($logoKanan)<img src="{{ $logoKanan }}" alt="Logo RSUD">@endif
    </td>
  </tr>
</table>

<div class="judul-laporan">{{ $matriks['judul'] }}</div>
<div class="periode-baris">{{ $matriks['periode_label'] }}</div>

@php
  $hariDalamBulan = $matriks['hari_dalam_bulan'];
  $totalKolom = $hariDalamBulan + 5;
  $lebarHariMm = round(128 / $hariDalamBulan, 2);
@endphp

@forelse($matriks['kelompok'] as $kelompok)
  <table class="matriks">
    <thead>
      <tr>
        <th rowspan="2" style="width: 6mm;">NO</th>
        <th rowspan="2" style="width: 48mm;">N A M A</th>
        <th colspan="{{ $hariDalamBulan }}">T A N G G A L</th>
        <th rowspan="2" style="width: 8.5mm;">JMLH</th>
        <th rowspan="2" style="width: 7mm;">%</th>
        <th rowspan="2" style="width: 36mm;">KET</th>
      </tr>
      <tr>
        @for($d = 1; $d <= $hariDalamBulan; $d++)
          <th style="width: {{ $lebarHariMm }}mm;" class="{{ $matriks['tanggal_meta'][$d]['merah'] ? 'merah' : '' }}">{{ $d }}</th>
        @endfor
      </tr>
    </thead>
    <tbody>
      @if($kelompok['nama'] !== '')
        <tr class="grup-baris">
          <td colspan="{{ $totalKolom }}">{{ $kelompok['nama'] }}</td>
        </tr>
      @endif
      @foreach($kelompok['pegawai'] as $idx => $p)
        <tr>
          <td class="kol-no">{{ $idx + 1 }}</td>
          <td class="kol-nama">{{ $p['nama'] }}</td>
          @foreach($p['sel'] as $cell)
            <td
              @if($cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
              class="sel-{{ $cell['tipe'] }} {{ $cell['gabung'] ? 'sel-gabung' : '' }}"
            >{{ $cell['teks'] }}</td>
          @endforeach
          <td>{{ $p['jmlh'] }}</td>
          <td>{{ $p['persen'] }}</td>
          <td class="kol-ket">{{ $p['ket'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@empty
  <table class="matriks">
    <tbody>
      <tr>
        <td style="padding: 12px; text-align: center; color: #555;">
          Tidak ada data pegawai sesuai filter yang dipilih.
        </td>
      </tr>
    </tbody>
  </table>
@endforelse

<div class="legenda">
  Keterangan Kode: <strong>H</strong> = Hadir, <strong>L</strong> = Libur / Minggu,
  <strong>I</strong> = Izin, <strong>S</strong> = Sakit, <strong>C</strong> = Cuti,
  <strong>DL</strong> = Dinas Luar, <strong>A</strong> = Alpa.
</div>

<table class="ttd">
  <tr>
    <td></td>
    <td class="kanan">
      Merauke, {{ $tanggalCetak }}<br>
      Direktur RSUD Merauke
      <div class="ttd-spasi"></div>
      <strong>(______________________________)</strong>
    </td>
  </tr>
</table>

</body>
</html>
