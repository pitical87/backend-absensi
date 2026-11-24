<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Absen Harian {{ strtoupper($namaBulan) }} {{ $tahun }}</title>
<style>
  @page {
    size: A4 portrait;
    margin: 7mm 14mm 7mm 14mm;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: Helvetica, Arial, "DejaVu Sans", sans-serif;
    font-size: 8.5pt;
    color: #000000;
    line-height: 1.18;
  }

  .halaman {
    page-break-after: always;
  }

  .halaman:last-child {
    page-break-after: auto;
  }

  /* KOP SURAT */
  table.kop {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 2.2px solid #000000;
    margin-bottom: 3px;
  }

  table.kop td {
    border: none;
    vertical-align: middle;
    padding: 0 2px 3px 2px;
  }

  table.kop td.logo-kiri {
    width: 13%;
    text-align: center;
  }

  table.kop td.logo-kanan {
    width: 13%;
    text-align: center;
  }

  table.kop td.kop-tengah {
    width: 74%;
    text-align: center;
  }

  table.kop img {
    width: 48px;
    height: auto;
  }

  .kop-pemda {
    font-size: 12pt;
    font-weight: normal;
    letter-spacing: 0.2px;
    line-height: 1.12;
  }

  .kop-rsud {
    font-size: 12.5pt;
    font-weight: bold;
    letter-spacing: 0.2px;
    line-height: 1.12;
  }

  .kop-alamat {
    font-size: 7.8pt;
    font-weight: bold;
    margin-top: 1.5px;
    line-height: 1.15;
  }

  .kop-kontak {
    font-size: 7.8pt;
    font-weight: normal;
    line-height: 1.15;
  }

  .kop-email {
    color: #0563C1;
    font-style: italic;
    font-weight: bold;
    text-decoration: underline;
  }

  /* JUDUL */
  .judul-blok {
    text-align: center;
    margin: 2px 0 3px 0;
  }

  .judul-utama {
    font-size: 9.5pt;
    font-weight: bold;
    letter-spacing: 0.3px;
  }

  .judul-bulan {
    font-size: 9pt;
    font-weight: normal;
    margin-top: 1px;
  }

  /* IDENTITAS PEGAWAI */
  table.identitas {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
    font-size: 8pt;
  }

  table.identitas td {
    border: none;
    padding: 1px 2px;
    vertical-align: top;
  }

  table.identitas td.lbl-kiri {
    width: 16%;
    white-space: nowrap;
  }

  table.identitas td.titik {
    width: 2%;
    text-align: center;
  }

  table.identitas td.val-kiri {
    width: 31%;
  }

  table.identitas td.lbl-kanan {
    width: 12%;
    white-space: nowrap;
  }

  table.identitas td.val-kanan {
    width: 37%;
  }

  /* TABEL ABSEN HARIAN */
  table.absen {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000000;
    font-size: 7.8pt;
  }

  table.absen th {
    border: 1px solid #000000;
    border-bottom: 1.5px solid #000000;
    padding: 3px 3px;
    text-align: center;
    font-weight: bold;
    background-color: #FFFFFF;
  }

  table.absen td {
    border: 1px solid #000000;
    padding: 0 4px;
    vertical-align: middle;
    line-height: 1.1;
    height: 22px;
  }

  table.absen td.col-no {
    width: 5%;
    text-align: center;
  }

  table.absen td.col-tgl {
    width: 22%;
    text-align: left;
    white-space: nowrap;
  }

  table.absen td.col-tgl.minggu {
    background-color: #FF0000;
    color: #000000;
  }

  table.absen td.col-jam {
    width: 12%;
    text-align: center;
  }

  table.absen td.col-paraf {
    width: 12%;
    text-align: center;
    padding: 1px 2px;
    line-height: 0;
  }

  img.qr-paraf {
    width: 22px;
    height: 22px;
    display: inline-block;
    vertical-align: middle;
  }

  table.absen td.col-ket {
    width: 25%;
    text-align: left;
    font-size: 7.5pt;
  }

  /* FOOTER & TTD */
  .catatan {
    font-size: 7.6pt;
    font-weight: bold;
    margin-top: 3px;
    margin-bottom: 3px;
  }

  table.ttd {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.3pt;
  }

  table.ttd td {
    border: none;
    vertical-align: top;
  }

  table.ttd td.ttd-kiri {
    width: 56%;
  }

  table.ttd td.ttd-kanan {
    width: 44%;
    text-align: center;
  }

  .ttd-spasi {
    height: 24px;
  }
</style>
</head>
<body>
@foreach ($lembarList as $lembar)
  <div class="halaman">
    <table class="kop">
      <tr>
        <td class="logo-kiri">
          @if ($logoKiri)
            <img src="{{ $logoKiri }}" alt="Logo Kabupaten Merauke">
          @endif
        </td>
        <td class="kop-tengah">
          <div class="kop-pemda">PEMERINTAH KABUPATEN MERAUKE</div>
          <div class="kop-rsud">RUMAH SAKIT UMUM DAERAH KELAS C<br>MERAUKE</div>
          <div class="kop-alamat">Jalan Soekarjo Wiryopranoto No. 1 &ndash; Kelurahan Karang Indah - Merauke</div>
          <div class="kop-kontak">Pos-el : <span class="kop-email">humasrsudmerauke@gmail.com</span> . Kode Pos : 99614</div>
        </td>
        <td class="logo-kanan">
          @if ($logoKanan)
            <img src="{{ $logoKanan }}" alt="Logo RSUD Merauke">
          @endif
        </td>
      </tr>
    </table>

    <div class="judul-blok">
      <div class="judul-utama">ABSEN HARIAN</div>
      <div class="judul-bulan">BULAN : {{ strtoupper($namaBulan) }} {{ $tahun }}</div>
    </div>

    <table class="identitas">
      <tr>
        <td class="lbl-kiri">NAMA PEGAWAI</td>
        <td class="titik">:</td>
        <td class="val-kiri">{{ $lembar['pegawai']['nama'] }}</td>
        <td class="lbl-kanan">JABATAN</td>
        <td class="titik">:</td>
        <td class="val-kanan">{{ $lembar['pegawai']['jabatan'] }}</td>
      </tr>
      <tr>
        <td class="lbl-kiri">NIP</td>
        <td class="titik">:</td>
        <td class="val-kiri">{{ $lembar['pegawai']['nip'] }}</td>
        <td class="lbl-kanan">UNIT KERJA</td>
        <td class="titik">:</td>
        <td class="val-kanan">{{ $lembar['pegawai']['unit_kerja'] }}</td>
      </tr>
      <tr>
        <td class="lbl-kiri">PANGKAT/GOL</td>
        <td class="titik">:</td>
        <td class="val-kiri">{{ $lembar['pegawai']['pangkat_gol'] }}</td>
        <td class="lbl-kanan"></td>
        <td class="titik"></td>
        <td class="val-kanan"></td>
      </tr>
    </table>

    <table class="absen">
      <thead>
        <tr>
          <th>NO</th>
          <th>HARI/TANGGAL</th>
          <th>JAM MASUK</th>
          <th>PARAF</th>
          <th>JAM PULANG</th>
          <th>PARAF</th>
          <th>KET</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($lembar['baris'] as $b)
          <tr>
            <td class="col-no">{{ $b['no'] }}</td>
            <td class="col-tgl{{ $b['minggu'] ? ' minggu' : '' }}">{{ $b['hari_tanggal'] }}</td>
            <td class="col-jam">{{ $b['jam_masuk'] }}</td>
            <td class="col-paraf">
              @if ($b['jam_masuk'] !== '' && ! empty($lembar['pegawai']['qr_paraf']))
                <img class="qr-paraf" src="{{ $lembar['pegawai']['qr_paraf'] }}" alt="QR Paraf">
              @endif
            </td>
            <td class="col-jam">{{ $b['jam_pulang'] }}</td>
            <td class="col-paraf">
              @if ($b['jam_pulang'] !== '' && ! empty($lembar['pegawai']['qr_paraf']))
                <img class="qr-paraf" src="{{ $lembar['pegawai']['qr_paraf'] }}" alt="QR Paraf">
              @endif
            </td>
            <td class="col-ket">{{ $b['ket'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="catatan">*Bagi Pegawai Negeri Tolong Diisi NIP</div>

    <table class="ttd">
      <tr>
        <td class="ttd-kiri"></td>
        <td class="ttd-kanan">
          <div>MERAUKE,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ strtoupper($namaBulan) }} {{ $tahun }}</div>
          <div style="margin-top: 2px;">KEPALA RUANGAN</div>
          <div class="ttd-spasi"></div>
        </td>
      </tr>
    </table>
  </div>
@endforeach
</body>
</html>
