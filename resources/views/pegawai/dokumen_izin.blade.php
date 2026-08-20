<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dokumen {{ $iz->jenis_cuti ?: $iz->jenis }} — {{ $u['nama_lengkap'] }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font: 12px/1.55 "Segoe UI", Arial, sans-serif; color: #111; padding: 24px; }
  .bar-cetak { margin-bottom: 18px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .bar-cetak button, .bar-cetak a.tombol {
    background: #1568B8; color: #fff; border: 0; padding: 9px 18px; border-radius: 8px;
    font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block;
  }
  .bar-cetak .info { font-size: 11.5px; color: #555; }
  .lembar { background: #fff; padding: 26px 30px; max-width: 780px; margin: 0 auto 30px;
            border: 1px solid #ddd; }
  .kop { display: flex; align-items: center; gap: 14px; border-bottom: 3px double #0B3B66; padding-bottom: 10px; }
  .kop img { width: 56px; height: 56px; }
  .kop h1 { font-size: 15px; color: #0B3B66; }
  .kop p { font-size: 10.5px; color: #333; }
  h2.judul { text-align: center; font-size: 13.5px; margin: 16px 0 2px; text-transform: uppercase; text-decoration: underline; }
  p.nomor { text-align: center; font-size: 11.5px; margin-bottom: 14px; }
  table.data { width: 100%; border-collapse: collapse; margin: 10px 0; }
  table.data td { padding: 3px 4px; vertical-align: top; font-size: 11.5px; }
  table.data td.label { width: 190px; }
  table.data td.titik { width: 14px; }
  table.tahap { width: 100%; border-collapse: collapse; margin-top: 14px; }
  table.tahap th, table.tahap td { border: 1px solid #888; padding: 6px 8px; font-size: 11px; }
  table.tahap th { background: #E3F0FB; color: #0B3B66; }
  .ttd-grid { display: flex; justify-content: space-between; margin-top: 30px; gap: 10px; flex-wrap: wrap; }
  .ttd-blok { text-align: center; font-size: 11.5px; width: 200px; }
  .ttd-spasi { height: 60px; display: flex; align-items: center; justify-content: center; }
  .ttd-elektronik { font-size: 10px; color: #1568B8; border: 1px dashed #1568B8; border-radius: 6px;
                    padding: 6px; line-height: 1.4; }
  .kotak-cuti { border: 1px solid #C9DDF0; border-radius: 8px; padding: 10px 14px; margin: 14px 0;
                background: #F5FAFF; font-size: 11.5px; }
  .catatan-kecil { font-size: 10.5px; color: #666; margin-top: 4px; }
  @media print { .bar-cetak { display: none; } body { padding: 0; } .lembar { border: none; margin: 0 auto; page-break-after: always; } }
  @page { size: A4; margin: 14mm; }
</style>
</head>
<body>

<div class="bar-cetak">
  <button type="button" onclick="window.print()">&#128424; Cetak / Simpan sebagai PDF (kedua berkas)</button>
  @if(! empty($bolehTtd) && ! $iz->ttd_digital)
    <form method="post" action="{{ url('izin/tanda-tangan/' . (int) $iz->id) }}"
          onsubmit="return confirm('Bubuhkan tanda tangan elektronik Direktur pada dokumen ini?');">
      @csrf
      <button type="submit" style="background:#178A50">✓ Tanda Tangan Digital (Direktur)</button>
    </form>
  @endif
  <span class="info">Nomor surat: <strong>{{ $iz->nomor_surat ?? '—' }}</strong> ·
    Kode verifikasi: <strong>{{ $iz->kode_verifikasi ?? '—' }}</strong></span>
</div>

<!-- ================= BERKAS 1: FORMULIR PERMOHONAN ================= -->
<div class="lembar">
  <div class="kop">
    <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
    <div>
      <h1>PEMERINTAH KABUPATEN MERAUKE<br>RUMAH SAKIT UMUM DAERAH MERAUKE</h1>
      <p>Formulir Permohonan Izin / Cuti Pegawai</p>
    </div>
  </div>
  <h2 class="judul">Formulir Permohonan {{ $iz->jenis_cuti ?: $iz->jenis }}</h2>
  <p class="nomor">Nomor: {{ $iz->nomor_surat ?? '—' }}</p>

  <table class="data">
    <tr><td class="label">Nama Pegawai</td><td class="titik">:</td><td><strong>{{ $u['nama_lengkap'] }}</strong></td></tr>
    <tr><td class="label">NIP</td><td class="titik">:</td><td>{{ $u['nip'] ?: '—' }}</td></tr>
    <tr><td class="label">Jabatan</td><td class="titik">:</td><td>{{ label_jabatan($u) }}</td></tr>
    <tr><td class="label">Unit Kerja</td><td class="titik">:</td><td>{{ unit_organisasi($u) }}</td></tr>
    <tr><td class="label">Status Kepegawaian</td><td class="titik">:</td><td>{{ $u['status_pegawai'] ?? 'Non-PNS' }}</td></tr>
    <tr><td class="label">Jenis Permohonan</td><td class="titik">:</td><td><strong>{{ $iz->jenis_cuti ?: $iz->jenis }}</strong></td></tr>
    <tr><td class="label">Lama</td><td class="titik">:</td>
        <td>{{ (int) $iz->lama_hari ?: '' }} hari kerja — mulai <strong>{{ tgl_id($iz->tanggal_mulai, false) }}</strong>
            sampai dengan <strong>{{ tgl_id($iz->tanggal_selesai, false) }}</strong></td></tr>
    <tr><td class="label">Alamat Selama Izin/Cuti</td><td class="titik">:</td><td>{{ $iz->alamat_izin ?: '—' }}</td></tr>
    <tr><td class="label">Alasan / Keperluan</td><td class="titik">:</td><td>{{ $iz->keterangan }}</td></tr>
  </table>

  @if($iz->jenis === 'Cuti' && $iz->jenis_cuti === 'Cuti Tahunan')
    <div class="kotak-cuti">
      <strong>Rincian Hak Cuti Tahunan {{ date('Y', strtotime($iz->tanggal_mulai)) }}:</strong>
      Hak 12 hari kerja — permohonan ini menggunakan {{ (int) $iz->lama_hari }} hari kerja.
    </div>
  @endif

  <table class="tahap">
    <thead><tr><th>Tahap</th><th>Pejabat</th><th>Status</th><th>Waktu</th><th>Catatan</th></tr></thead>
    <tbody>
      @foreach($tahap as $t)
        @if($t->status === 'Dilewati') @continue @endif
        <tr>
          <td>{{ $t->posisi_tahap }}</td>
          <td>{{ $t->user->nama_lengkap ?? '—' }}</td>
          <td>{{ $t->status }}</td>
          <td>{{ $t->waktu ? tgl_id($t->waktu, false) . ' ' . jam_id($t->waktu) : '—' }}</td>
          <td>{{ $t->catatan ?? '—' }}</td>
        </tr>
      @endforeach
      @if(! count(array_filter($tahap, fn ($t) => $t->status !== 'Dilewati')))
        <tr><td colspan="5">Disetujui otomatis — posisi pemohon berada di puncak alur persetujuan.</td></tr>
      @endif
    </tbody>
  </table>

  <div class="ttd-grid">
    <div class="ttd-blok">Pemohon,<div class="ttd-spasi"></div><strong>{{ $u['nama_lengkap'] }}</strong></div>
    @foreach(array_filter($tahap, fn ($t) => $t->status === 'Disetujui') as $t)
    <div class="ttd-blok">{{ $t->posisi_tahap }},<div class="ttd-spasi"></div>
      <strong>{{ $t->user->nama_lengkap }}</strong></div>
    @endforeach
  </div>
</div>

<!-- ================= BERKAS 2: SURAT KETERANGAN RESMI ================= -->
<div class="lembar">
  <div class="kop">
    <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
    <div>
      <h1>PEMERINTAH KABUPATEN MERAUKE<br>RUMAH SAKIT UMUM DAERAH MERAUKE</h1>
      <p>Jalan — Merauke, Papua Selatan</p>
    </div>
  </div>
  <h2 class="judul">Surat Keterangan {{ $iz->jenis_cuti ?: $iz->jenis }}</h2>
  <p class="nomor">Nomor: {{ $iz->nomor_surat ?? '—' }}</p>

  <p style="margin-top:10px">Yang bertanda tangan di bawah ini, Direktur Rumah Sakit Umum Daerah Merauke,
    menerangkan bahwa telah menyetujui permohonan {{ strtolower($iz->jenis_cuti ?: $iz->jenis) }}
    bagi pegawai:</p>

  <table class="data" style="margin-top:10px">
    <tr><td class="label">Nama</td><td class="titik">:</td><td><strong>{{ $u['nama_lengkap'] }}</strong></td></tr>
    <tr><td class="label">NIP</td><td class="titik">:</td><td>{{ $u['nip'] ?: '—' }}</td></tr>
    <tr><td class="label">Jabatan / Unit Kerja</td><td class="titik">:</td>
        <td>{{ label_jabatan($u) }} — {{ unit_organisasi($u) }}</td></tr>
    <tr><td class="label">Terhitung Mulai Tanggal</td><td class="titik">:</td><td>{{ tgl_id($iz->tanggal_mulai, false) }}</td></tr>
    <tr><td class="label">Sampai Dengan</td><td class="titik">:</td><td>{{ tgl_id($iz->tanggal_selesai, false) }}</td></tr>
    <tr><td class="label">Lama</td><td class="titik">:</td><td>{{ (int) $iz->lama_hari ?: '' }} hari kerja</td></tr>
    <tr><td class="label">Alamat Selama Izin/Cuti</td><td class="titik">:</td><td>{{ $iz->alamat_izin ?: '—' }}</td></tr>
  </table>

  <p style="margin-top:10px">Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

  <div class="ttd-grid" style="justify-content:flex-end">
    <div class="ttd-blok">
      Merauke, {{ tgl_id($iz->processed_at ?? date('Y-m-d'), false) }}<br>
      Direktur RSUD Merauke
      @if($iz->ttd_digital && $ttdOleh)
        <div class="ttd-elektronik" style="margin-top:10px">
          Dokumen ini telah ditandatangani secara elektronik oleh<br>
          <strong>{{ $ttdOleh->nama_lengkap }}</strong><br>
          pada {{ tgl_id($iz->ttd_waktu, false) }} pukul {{ jam_id($iz->ttd_waktu) }}<br>
          Keabsahan dapat diperiksa di:<br>
          {{ url('verifikasi/' . $iz->kode_verifikasi) }}
        </div>
      @else
        <div class="ttd-spasi"></div>
        <strong>(______________________________)</strong><br>NIP.
      @endif
    </div>
  </div>
  <p class="catatan-kecil">Kode verifikasi: {{ $iz->kode_verifikasi ?? '—' }} —
    keabsahan surat ini dapat diperiksa melalui {{ url('verifikasi') }}</p>
</div>

</body>
</html>
