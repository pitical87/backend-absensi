<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Pegawai {{ $namaInstansi }}</title>
<style>
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #111; }
  .kop { border-bottom: 3px double #0B3B66; padding-bottom: 8px; text-align: center; }
  .kop h1 { font-size: 15px; color: #0B3B66; margin: 0; letter-spacing: .4px; }
  .kop p  { font-size: 9px; color: #333; margin: 0; }
  h2.judul { text-align: center; font-size: 13px; margin: 14px 0 2px; text-transform: uppercase;
             text-decoration: underline; }
  .filter { text-align: center; font-size: 10px; margin-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th, td { border: 1px solid #444; padding: 3px 5px; font-size: 8.5px; }
  th { background: #E3F0FB; color: #0B3B66; }
  td.angka, th.angka { text-align: center; }
  .ttd { margin-top: 28px; text-align: right; }
  .ttd .blok { text-align: center; font-size: 10px; display: inline-block; }
  .ttd .spasi { height: 58px; }
</style>
</head>
<body>

<div class="kop">
  <h1>PEMERINTAH KABUPATEN MERAUKE<br>{{ strtoupper($namaInstansi) }}</h1>
  <p>Sistem Absensi Pegawai — Data Pegawai</p>
</div>

<h2 class="judul">Daftar Pegawai</h2>
<p class="filter">Filter: <strong>{{ $label }}</strong> &nbsp;·&nbsp; Jumlah: <strong>{{ number_format($daftar->count(), 0, ',', '.') }} pegawai</strong> &nbsp;·&nbsp; Dicetak: {{ $tanggalCetak }}</p>

<table>
  <thead>
    <tr>
      @foreach($kolomEkspor as $judulKolom)
      <th>{{ $judulKolom }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @foreach($barisEkspor as $baris)
    <tr>
      @foreach($baris as $nilai)
      <td>{{ $nilai ?: '—' }}</td>
      @endforeach
    </tr>
    @endforeach
    @if($daftar->isEmpty())
    <tr><td colspan="{{ count($kolomEkspor) }}" style="text-align:center;color:#666">Tidak ada data pegawai.</td></tr>
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
