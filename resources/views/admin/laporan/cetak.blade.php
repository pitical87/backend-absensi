<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Rekap Absensi {{ BULAN_ID[$bulan] . ' ' . $tahun }} — RSUD Merauke</title>
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
  @page { size: A4 landscape; margin: 12mm; }
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
    <h1>PEMERINTAH KABUPATEN MERAUKE<br>RUMAH SAKIT UMUM DAERAH MERAUKE</h1>
    <p>Sistem Absensi Pegawai — Laporan Kehadiran Bulanan</p>
  </div>
</div>

<h2 class="judul">Rekapitulasi Kehadiran Pegawai</h2>
<p class="periode">Periode: <strong>{{ BULAN_ID[$bulan] . ' ' . $tahun }}</strong> &nbsp;·&nbsp;
  Unit: <strong>{{ $namaUnit }}</strong></p>

<table>
  <thead>
    <tr>
      <th class="angka">No</th><th>Nama Pegawai</th><th>Unit / Sub Unit</th><th>Jabatan</th><th>Profesi</th>
      <th class="angka">Hari Efektif</th><th class="angka">Hadir</th>
      <th class="angka">Tepat</th><th class="angka">Telat</th><th class="angka">Alpa</th>
      <th class="angka">Izin</th><th class="angka">Sakit</th><th class="angka">Cuti</th>
      <th class="angka">Dinas</th><th class="angka">Total Jam</th><th class="angka">%</th>
    </tr>
  </thead>
  <tbody>
    @php $no = 1; @endphp @foreach($pegawai as $p) @php $r = $rekapPer[(int) $p->id]; @endphp
    <tr>
      <td class="angka">{{ $no++ }}</td>
      <td>{{ $p->nama_lengkap }}</td>
      <td>{{ $p->unit_nama ?? '—' }}@if($p->sub_nama) — {{ $p->sub_nama }}@endif</td>
      <td>{{ $p->jabatan_nama ?? ($p->jabatan_kategori ?? '—') }}</td>
      <td>{{ $p->profesi_nama ?? '—' }}</td>
      <td class="angka">{{ $r['hari_efektif'] }}</td>
      <td class="angka">{{ $r['hadir'] }}</td>
      <td class="angka">{{ $r['tepat'] }}</td>
      <td class="angka">{{ $r['terlambat'] }}</td>
      <td class="angka">{{ $r['alpa'] }}</td>
      <td class="angka">{{ $r['izin'] }}</td>
      <td class="angka">{{ $r['sakit'] }}</td>
      <td class="angka">{{ $r['cuti'] }}</td>
      <td class="angka">{{ $r['dinas_luar'] }}</td>
      <td class="angka">{{ menit_ke_teks($r['total_menit']) }}</td>
      <td class="angka">{{ $r['persen'] }}%</td>
    </tr>
    @endforeach
    @if(! $pegawai)
    <tr><td colspan="16" style="text-align:center;color:#666">Tidak ada data.</td></tr>
    @endif
  </tbody>
</table>

<div class="ttd">
  <div class="blok">
    Merauke, {{ tgl_id(date('Y-m-d'), false) }}<br>
    Direktur RSUD Merauke
    <div class="spasi"></div>
    <strong>(______________________________)</strong><br>
    NIP.
  </div>
</div>

</body>
</html>
