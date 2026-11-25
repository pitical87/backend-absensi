<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Absen Harian {{ strtoupper($namaBulan) }} {{ $tahun }}</title>
<style>
  @page {
    size: A4 portrait;
    margin: 9mm 14mm 9mm 14mm;
  }

  body {
    margin: 0;
    padding: 0;
    font-family: Helvetica, Arial, "DejaVu Sans", sans-serif;
    font-size: 8.5pt;
    color: #000000;
    line-height: 1.2;
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
    margin-bottom: 4px;
  }

  table.kop td {
    border: none;
    vertical-align: middle;
    padding: 0 2px 4px 2px;
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
    width: 52px;
    height: auto;
  }

  .kop-pemda {
    font-size: 12.5pt;
    font-weight: normal;
    letter-spacing: 0.2px;
    line-height: 1.15;
  }

  .kop-rsud {
    font-size: 13pt;
    font-weight: bold;
    letter-spacing: 0.2px;
    line-height: 1.15;
  }

  .kop-alamat {
    font-size: 8pt;
    font-weight: bold;
    margin-top: 2px;
    line-height: 1.18;
  }

  .kop-kontak {
    font-size: 8pt;
    font-weight: normal;
    line-height: 1.18;
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
    margin: 3px 0 4px 0;
  }

  .judul-utama {
    font-size: 10pt;
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
    margin-bottom: 5px;
    font-size: 8.2pt;
  }

  table.identitas td {
    border: none;
    padding: 1.5px 2px;
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
    font-size: 8pt;
  }

  table.absen th {
    border: 1px solid #000000;
    border-bottom: 1.5px solid #000000;
    padding: 4px 4px;
    text-align: center;
    font-weight: bold;
    background-color: #FFFFFF;
  }

  table.absen td {
    border: 1px solid #000000;
    padding: 2.3px 5px;
    vertical-align: middle;
    line-height: 1.15;
  }

  table.absen td.col-no {
    width: 6%;
    text-align: center;
  }

  table.absen td.col-tgl {
    width: 25%;
    text-align: left;
    white-space: nowrap;
  }

  table.absen td.col-tgl.minggu {
    background-color: #FF0000;
    color: #000000;
  }

  table.absen td.col-jam {
    width: 15%;
    text-align: center;
  }

  table.absen td.col-ket {
    width: 39%;
    text-align: left;
    font-size: 7.8pt;
  }

  /* TABEL LOGBOOK HARIAN */
  table.logbook {
    width: 100%;
    border-collapse: collapse;
    border: 1.5px solid #000000;
    font-size: 8pt;
  }

  table.logbook thead {
    display: table-header-group;
  }

  table.logbook th {
    border: 1px solid #000000;
    border-bottom: 1.5px solid #000000;
    padding: 4px 4px;
    text-align: center;
    font-weight: bold;
    background-color: #FFFFFF;
  }

  table.logbook tbody tr {
    page-break-inside: avoid;
  }

  table.logbook td {
    border: 1px solid #000000;
    padding: 3px 5px;
    vertical-align: top;
    line-height: 1.25;
  }

  table.logbook th.col-lb-no,
  table.logbook td.col-lb-no {
    width: 6%;
    text-align: center;
  }

  table.logbook th.col-lb-tgl,
  table.logbook td.col-lb-tgl {
    width: 22%;
    text-align: left;
    white-space: nowrap;
  }

  table.logbook th.col-lb-jam,
  table.logbook td.col-lb-jam {
    width: 9%;
    text-align: center;
    white-space: nowrap;
  }

  table.logbook th.col-lb-isi,
  table.logbook td.col-lb-isi {
    width: 50%;
    text-align: left;
    word-wrap: break-word;
    overflow-wrap: break-word;
  }

  table.logbook th.col-lb-status,
  table.logbook td.col-lb-status {
    width: 13%;
    text-align: center;
    font-size: 7.5pt;
    white-space: nowrap;
  }

  table.logbook td.col-lb-kosong {
    text-align: center;
    font-style: italic;
    color: #555555;
    padding: 16px 8px;
  }

  .lb-status-ok {
    color: #166534;
    font-weight: bold;
  }

  .lb-status-pending {
    color: #6B7280;
  }

  .ringkasan-logbook {
    font-size: 7.8pt;
    font-weight: bold;
    margin-top: 5px;
    margin-bottom: 10px;
  }

  /* FOOTER & TTD */
  .catatan {
    font-size: 7.8pt;
    font-weight: bold;
    margin-top: 5px;
    margin-bottom: 8px;
  }

  table.ttd {
    width: 100%;
    border-collapse: collapse;
    font-size: 8.5pt;
    page-break-inside: avoid;
  }

  table.ttd td {
    border: none;
    vertical-align: top;
    text-align: center;
    width: 50%;
    padding: 0 8px;
  }

  .ttd-qr-wrap {
    height: 76px;
    padding-top: 4px;
  }

  .ttd-qr-wrap img {
    width: 70px;
    height: 70px;
    display: inline-block;
  }

  .ttd-spasi {
    height: 76px;
  }

  .ttd-nama {
    font-weight: bold;
    text-decoration: underline;
    margin-top: 2px;
  }

  .ttd-nip {
    font-size: 7.8pt;
    margin-top: 1px;
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
          <th>NO.</th>
          <th>HARI/TANGGAL</th>
          <th>JAM MASUK</th>
          <th>JAM PULANG</th>
          <th>KETERANGAN</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($lembar['baris'] as $b)
          <tr>
            <td class="col-no">{{ $b['no'] }}</td>
            <td class="col-tgl{{ $b['minggu'] ? ' minggu' : '' }}">{{ $b['hari_tanggal'] }}</td>
            <td class="col-jam">{{ $b['jam_masuk'] }}</td>
            <td class="col-jam">{{ $b['jam_pulang'] }}</td>
            <td class="col-ket">{{ $b['ket'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="catatan">*Bagi Pegawai Negeri Tolong Diisi NIP</div>

    <table class="ttd">
      <tr>
        <td>
          <div>&nbsp;</div>
          <div>Pegawai</div>
          <div class="ttd-qr-wrap">
            @if (! empty($lembar['pegawai']['qr_paraf']))
              <img src="{{ $lembar['pegawai']['qr_paraf'] }}" alt="QR Verifikasi Pegawai">
            @endif
          </div>
          <div class="ttd-nama">{{ $lembar['pegawai']['nama'] }}</div>
          @if ($lembar['pegawai']['nip'] !== '')
            <div class="ttd-nip">NIP. {{ $lembar['pegawai']['nip'] }}</div>
          @endif
        </td>
        <td>
          <div>Merauke, {{ $tanggalCetak }}</div>
          <div>Atasan Langsung</div>
          <div class="ttd-spasi"></div>
          <div class="ttd-nama">{{ $atasan['nama'] !== '' ? $atasan['nama'] : '(______________________________)' }}</div>
          @if ($atasan['nip'] !== '')
            <div class="ttd-nip">NIP. {{ $atasan['nip'] }}</div>
          @endif
        </td>
      </tr>
    </table>
  </div>
@endforeach

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
      <div class="judul-utama">DAFTAR LOGBOOK HARIAN</div>
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

    <table class="logbook">
      <thead>
        <tr>
          <th class="col-lb-no">NO.</th>
          <th class="col-lb-tgl">HARI/TANGGAL</th>
          <th class="col-lb-jam">JAM</th>
          <th class="col-lb-isi">URAIAN KEGIATAN / AKTIVITAS</th>
          <th class="col-lb-status">STATUS</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($lembar['logbook'] as $lb)
          <tr>
            <td class="col-lb-no">{{ $lb['no'] }}</td>
            <td class="col-lb-tgl">{{ $lb['hari_tanggal'] }}</td>
            <td class="col-lb-jam">{{ $lb['jam'] }}</td>
            <td class="col-lb-isi">{!! nl2br(e($lb['isi'])) !!}</td>
            <td class="col-lb-status">
              @if ($lb['terverifikasi'])
                <span class="lb-status-ok">Terverifikasi</span>
              @else
                <span class="lb-status-pending">Belum Verifikasi</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="col-lb-kosong">
              Belum ada catatan logbook pada bulan {{ $namaBulan }} {{ $tahun }}.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>

    <div class="ringkasan-logbook">
      Total: {{ $lembar['logbook_ringkasan']['total_entri'] }} kegiatan pada {{ $lembar['logbook_ringkasan']['total_hari'] }} hari
      ({{ $lembar['logbook_ringkasan']['terverifikasi'] }} terverifikasi)
    </div>

    <table class="ttd">
      <tr>
        <td>
          <div>&nbsp;</div>
          <div>Pegawai</div>
          <div class="ttd-qr-wrap">
            @if (! empty($lembar['pegawai']['qr_paraf']))
              <img src="{{ $lembar['pegawai']['qr_paraf'] }}" alt="QR Verifikasi Pegawai">
            @endif
          </div>
          <div class="ttd-nama">{{ $lembar['pegawai']['nama'] }}</div>
          @if ($lembar['pegawai']['nip'] !== '')
            <div class="ttd-nip">NIP. {{ $lembar['pegawai']['nip'] }}</div>
          @endif
        </td>
        <td>
          <div>Merauke, {{ $tanggalCetak }}</div>
          <div>Atasan Langsung</div>
          <div class="ttd-spasi"></div>
          <div class="ttd-nama">{{ $atasan['nama'] !== '' ? $atasan['nama'] : '(______________________________)' }}</div>
          @if ($atasan['nip'] !== '')
            <div class="ttd-nip">NIP. {{ $atasan['nip'] }}</div>
          @endif
        </td>
      </tr>
    </table>
  </div>
@endforeach
</body>
</html>
