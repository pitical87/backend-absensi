<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Data Pegawai — {{ $namaInstansi }}</title>
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
  p.keterangan { text-align: center; font-size: 12px; margin-bottom: 8px; }
  .filter { text-align: center; font-size: 11px; color: #444; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { border: 1px solid #444; padding: 4px 6px; font-size: 10px; }
  th { background: #E3F0FB; color: #0B3B66; }
  td.angka, th.angka { text-align: center; font-variant-numeric: tabular-nums; }
  .ttd { margin-top: 34px; display: flex; justify-content: flex-end; }
  .ttd .blok { text-align: center; font-size: 12px; width: 280px; }
  .ttd .spasi { height: 70px; }
  .bar-cetak { margin-bottom: 16px; }
  .bar-cetak button { background: #1568B8; color: #fff; border: 0; padding: 9px 18px;
                      border-radius: 8px; font-size: 13px; cursor: pointer; }
  @media print { .bar-cetak { display: none; } body { padding: 0; } }
  @page { size: A4 landscape; margin: 12mm; }
</style>
</head>
<body>

<div class="bar-cetak">
  <button type="button" onclick="window.print()">&#128424; Cetak</button>
  <span style="font-size:11px;color:#555;margin-left:8px">Pilih tujuan <strong>"Save as PDF"</strong> di dialog cetak untuk menyimpan berkas PDF.</span>
</div>

<div class="kop">
  <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
  <div>
    <h1>PEMERINTAH KABUPATEN MERAUKE<br>{{ strtoupper($namaInstansi) }}</h1>
    <p>Sistem Absensi Pegawai — Data Pegawai</p>
  </div>
</div>

<h2 class="judul">Daftar Pegawai</h2>
<p class="filter">Filter: <strong>{{ $label }}</strong> &nbsp;·&nbsp; Jumlah: <strong>{{ number_format($daftar->count(), 0, ',', '.') }} pegawai</strong></p>

<table>
  <thead>
    <tr>
      <th class="angka" style="width:34px">No</th>
      <th>Nama Pegawai</th>
      <th style="width:150px">NIP</th>
      <th>Jabatan</th>
      <th>Bidang</th>
      <th>Sub Bidang</th>
      <th>Status Pegawai</th>
    </tr>
  </thead>
  <tbody>
    @php $no = 1; @endphp
    @foreach($daftar as $p)
    <tr>
      <td class="angka">{{ $no++ }}</td>
      <td>{{ $p->nama_lengkap }}</td>
      <td class="angka">{{ $p->nip ?: '—' }}</td>
      <td>{{ $p->jabatan_nama ?? ($p->jabatan_kategori ?? 'Staf/Pelaksana') }}</td>
      <td>{{ $p->unit_nama ?? '—' }}</td>
      <td>{{ $p->sub_nama ?: '—' }}</td>
      <td class="angka">{{ $p->status_pegawai }}</td>
    </tr>
    @endforeach
    @if($daftar->isEmpty())
    <tr><td colspan="7" style="text-align:center;color:#666">Tidak ada data pegawai.</td></tr>
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
