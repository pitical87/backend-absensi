@extends('layouts.admin')

@section('content')

<div class="stat-admin">
  <div class="stat"><span>Total Pegawai Aktif</span><strong>{{ $totalPegawai }}</strong></div>
  <div class="stat hijau"><span>Hadir Hari Ini</span><strong>{{ $hadir }}</strong></div>
  <div class="stat amber"><span>Terlambat Hari Ini</span><strong>{{ $terlambat }}</strong></div>
  <div class="stat"><span>Izin/Sakit/Cuti Hari Ini</span><strong>{{ $izinHariIni }}</strong></div>
  <div class="stat merah"><span>Belum Hadir</span><strong>{{ $belum }}</strong></div>
</div>

@if($menunggu > 0 || $anomali > 0)
<section class="kartu">
  <div class="aksi-baris">
    @if($menunggu > 0)
      <a class="btn btn-navy btn-kecil" href="{{ url('admin/izin') }}">
        {!! ikon('surat', 15) !!} {{ $menunggu }} pengajuan izin menunggu persetujuan</a>
    @endif
    @if($anomali > 0)
      <a class="btn btn-garis btn-kecil" href="{{ url('admin/kehadiran?anomali=1') }}">
        {!! ikon('peringatan', 15) !!} {{ $anomali }} absensi hari ini terindikasi anomali GPS</a>
    @endif
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Kehadiran 30 Hari Terakhir</h2>
    <span class="teks-redup teks-kecil">jumlah pegawai hadir per hari</span>
  </div>
  <div class="grafik-bungkus">
    <svg viewBox="0 0 660 150" role="img" aria-label="Grafik kehadiran 30 hari terakhir">
      @foreach([0, .5, 1] as $p) @php $y = 120 - $p * 100; @endphp
        <line x1="34" y1="{{ $y }}" x2="654" y2="{{ $y }}" stroke="#D7E5F2" stroke-width="1"/>
        <text x="28" y="{{ $y + 3 }}" font-size="9" fill="#5C7189" text-anchor="end">{{ round($maks * $p) }}</text>
      @endforeach
      @foreach($grafik30 as $i => $g)
          @php
          $x = 38 + $i * 20.5;
          $h = $g['jml'] > 0 ? max(3, $g['jml'] / $maks * 100) : 0;
          @endphp
        @if($h > 0)
          <rect x="{{ $x }}" y="{{ 120 - $h }}" width="13" height="{{ $h }}" rx="2.5" fill="#1568B8">
            <title>{{ tgl_id($g['tgl'], false) }}: {{ $g['jml'] }} pegawai</title>
          </rect>
        @else
          <rect x="{{ $x }}" y="117" width="13" height="3" rx="1.5" fill="#DCE8F4"/>
        @endif
        @if($i % 5 === 0 || $i === 29)
          <text x="{{ $x + 6.5 }}" y="134" font-size="8.5" fill="#5C7189" text-anchor="middle">{{ (int) date('j', strtotime($g['tgl'])) }}/{{ (int) date('n', strtotime($g['tgl'])) }}</text>
        @endif
      @endforeach
    </svg>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Absensi Hari Ini</h2>
    <a class="btn btn-garis btn-kecil" href="{{ url('admin/kehadiran') }}">Lihat semua</a>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama Pegawai</th><th>Unit</th><th>Shift</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr>
      </thead>
      <tbody>
        @foreach($terbaru as $r)
        <tr {{ $r->flag_anomali ? 'class="anomali-baris"' : '' }}>
          <td>{{ $r->nama_lengkap }}
            @if($r->flag_anomali) <span class="badge badge-amber">⚠</span>@endif</td>
          <td>{{ $r->unit_nama ?? '—' }}@if($r->sub_nama) — {{ $r->sub_nama }}@endif</td>
          <td>{{ label_shift(['kategori' => $r->shift_kategori, 'jam_masuk' => $r->shift_masuk, 'jam_pulang' => $r->shift_pulang]) }}</td>
          <td class="angka">{{ jam_id($r->waktu_masuk) }}</td>
          <td class="angka">{{ jam_id($r->waktu_pulang) }}</td>
          <td>{!! badge_status(! $r->waktu_pulang ? 'Belum Pulang'
                 : ($r->status_masuk === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu'),
                 (int) $r->menit_terlambat) !!}</td>
        </tr>
        @endforeach
        @if(! $terbaru)
        <tr><td colspan="6" class="tengah teks-redup">Belum ada absensi hari ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
