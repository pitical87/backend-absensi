<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekap Lembur {{ BULAN_ID[$bulan] . ' ' . $tahun }} — {{ $namaInstansi }}</title>
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
  tr.total td { font-weight: bold; background: #F2F7FD; }
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
    <h1>PEMERINTAH KABUPATEN MERAUKE<br>{{ strtoupper($namaInstansi) }}</h1>
    <p>Sistem Absensi Pegawai — Rekapitulasi Lembur</p>
  </div>
</div>

<h2 class="judul">Rekapitulasi Lembur Pegawai</h2>
<p class="periode">Periode: <strong>{{ BULAN_ID[$bulan] . ' ' . $tahun }}</strong>@if($f['q'])
  &nbsp;·&nbsp; Pencarian: <strong>"{{ $f['q'] }}"</strong>@endif &nbsp;·&nbsp;
  Jumlah pegawai: <strong>{{ count($pegawai) }}</strong></p>

<table>
  <thead>
    <tr>
      <th class="angka" style="width:36px">No</th>
      <th>Nama Pegawai</th>
      <th style="width:130px">NIP</th>
      <th>Unit / Sub Unit</th>
      <th class="angka">Pengajuan Disetujui</th>
      <th class="angka">Total Hari</th>
      <th class="angka">Total Jam</th>
      <th class="angka">Hari Absen Aktual</th>
      <th class="angka">Jam Aktual</th>
    </tr>
  </thead>
  <tbody>
    @php $no = 1; @endphp
    @foreach($pegawai as $p)
    @php $r = $rekapPer[(int) $p->id]; @endphp
    <tr>
      <td class="angka">{{ $no++ }}</td>
      <td>{{ $p->nama_lengkap }}</td>
      <td class="angka">{{ $p->nip ?: '—' }}</td>
      <td>{{ $p->unit_nama }}@if($p->sub_nama) — {{ $p->sub_nama }}@endif</td>
      <td class="angka">{{ $r['jumlah_pengajuan'] }}</td>
      <td class="angka">{{ $r['jumlah_hari'] }}</td>
      <td class="angka">{{ number_format($r['total_jam'], 1, ',', '.') }}</td>
      <td class="angka">{{ $r['jumlah_hari_aktual'] }}</td>
      <td class="angka">{{ $r['total_menit_aktual'] > 0 ? number_format($r['total_menit_aktual'] / 60, 1, ',', '.') : '—' }}</td>
    </tr>
    @endforeach
    <tr class="total">
      <td colspan="6" style="text-align:right">Total Jam Lembur (disetujui)</td>
      <td class="angka">{{ number_format(array_sum(array_column($rekapPer, 'total_jam')), 1, ',', '.') }}</td>
      <td colspan="2"></td>
    </tr>
    @if(! $pegawai)
    <tr><td colspan="9" style="text-align:center;color:#666">Tidak ada data.</td></tr>
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
