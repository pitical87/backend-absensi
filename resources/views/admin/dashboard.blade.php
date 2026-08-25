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

@if($teladan)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('bintang') !!} Pegawai Teladan Bulan Ini</h2>
    <span class="teks-redup teks-kecil">bintang rata-rata ≥ 4.5</span>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Nama Pegawai</th><th>Unit</th><th>Bintang</th><th>Kehadiran</th></tr>
      </thead>
      <tbody>
        @foreach($teladan as $t)
        <tr>
          <td>{{ $t['nama'] }}</td>
          <td>{{ $t['unit'] }}</td>
          <td><span class="teks-bintang">{{ str_repeat('★', (int) round($t['bintang'])) }}{{ str_repeat('☆', 5 - (int) round($t['bintang'])) }}</span>
            <span class="teks-redup teks-kecil">({{ $t['bintang'] }})</span></td>
          <td>{{ $t['hadir'] }} hari</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('centang') !!} Ketaatan Absen Bulan {{ BULAN_ID[(int) now()->format('n')] }} {{ now()->format('Y') }}</h2>
    <span class="teks-redup teks-kecil">{{ $ketaatan['hari_efektif'] }} hari kerja efektif dari {{ $ketaatan['hari_dalam_bulan'] }} hari</span>
  </div>
  @if($ketaatan['total'] > 0)
  <div class="flex flex-col sm:flex-row items-center gap-6">
    <div class="relative shrink-0 w-[180px] h-[180px]">
      <canvas id="grafik-ketaatan" role="img" aria-label="Diagram lingkaran ketaatan absen bulan ini"></canvas>
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
        <strong class="text-2xl font-bold text-navy leading-none">{{ $persenKetaatan }}%</strong>
        <span class="text-[0.65rem] text-slate-500 uppercase tracking-widest mt-1">ketaatan</span>
      </div>
    </div>
    <ul class="space-y-2.5 text-sm w-full sm:flex-1 m-0 p-0 list-none">
      @foreach($irisanPie as $s)
      <li class="flex items-center gap-2.5">
        <i class="w-3.5 h-3.5 rounded-md shrink-0 inline-block" style="background: {{ $s['warna'] }}"></i>
        <span class="text-slate-700">{{ $s['label'] }}</span>
        <span class="ml-auto tabular-nums whitespace-nowrap"><strong>{{ $s['jml'] }}</strong>
          <span class="teks-redup teks-kecil">pegawai ({{ $s['pct'] }}%)</span></span>
      </li>
      @endforeach
    </ul>
  </div>
  @else
  <p class="teks-redup tengah m-0">Belum ada data absensi untuk bulan ini.</p>
  @endif
</section>

{{-- <section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Kehadiran Bulan {{ BULAN_ID[(int) now()->format('n')] }} {{ now()->format('Y') }}</h2>
    <span class="teks-redup teks-kecil">jumlah pegawai hadir per hari</span>
  </div>
  <div class="grafik-bungkus">
    @php
      $jmlBar = count($grafikBulan);
      $langkah = $jmlBar > 0 ? 616 / $jmlBar : 616;
      $lebarBar = max(4, round($langkah * 0.63));
    @endphp
    <svg viewBox="0 0 660 150" role="img" aria-label="Grafik kehadiran bulan {{ now()->format('n Y') }}">
      @foreach([0, .5, 1] as $p) @php $y = 120 - $p * 100; @endphp
        <line x1="34" y1="{{ $y }}" x2="654" y2="{{ $y }}" stroke="#D7E5F2" stroke-width="1"/>
        <text x="28" y="{{ $y + 3 }}" font-size="9" fill="#5C7189" text-anchor="end">{{ round($maks * $p) }}</text>
      @endforeach
      @foreach($grafikBulan as $i => $g)
          @php
          $x = 38 + $i * $langkah;
          $h = $g['jml'] > 0 ? max(3, $g['jml'] / $maks * 100) : 0;
          @endphp
        @if($h > 0)
          <rect x="{{ $x }}" y="{{ 120 - $h }}" width="{{ $lebarBar }}" height="{{ $h }}" rx="3" fill="#007AFC">
            <title>{{ tgl_id($g['tgl'], false) }}: {{ $g['jml'] }} pegawai</title>
          </rect>
        @else
          <rect x="{{ $x }}" y="117" width="{{ $lebarBar }}" height="3" rx="1.5" fill="#DCE8F4"/>
        @endif
        @if(($i + 1) % 5 === 0 || $i === $jmlBar - 1)
          <text x="{{ $x + $lebarBar / 2 }}" y="134" font-size="8.5" fill="#5C7189" text-anchor="middle">{{ (int) date('j', strtotime($g['tgl'])) }}</text>
        @endif
      @endforeach
    </svg>
  </div>
</section> --}}

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Grafik Absensi Bulan {{ BULAN_ID[(int) now()->format('n')] }}</h2>
    <a class="btn btn-garis btn-kecil" href="{{ url('admin/kehadiran') }}">Lihat semua</a>
  </div>
  <div class="h-[250px]">
    <canvas id="grafik-tren" role="img" aria-label="Grafik kombinasi batang dan garis absensi harian bulan ini"></canvas>
  </div>
  <div class="legenda">
    <span><i style="background: #059669"></i> Tepat Waktu</span>
    <span><i style="background: #D97706"></i> Terlambat</span>
    <span><i style="background: #DC2626"></i> Tidak Absen</span>
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
        <tr><th>Nama Pegawai</th><th>Unit</th><th>Shift</th><th>Status</th></tr>
      </thead>
      <tbody>
        @foreach($terbaru as $r)
        <tr {{ $r->flag_anomali ? 'class="anomali-baris"' : '' }}>
          <td>{{ $r->nama_lengkap }}
            @if($r->flag_anomali) <span class="badge badge-amber">⚠</span>@endif</td>
          <td>{{ $r->unit_nama ?? '—' }}@if($r->sub_nama) — {{ $r->sub_nama }}@endif</td>
          <td>{{ label_shift((object)['kategori' => $r->shift_kategori, 'jam_masuk' => $r->shift_masuk, 'jam_pulang' => $r->shift_pulang]) }}</td>
          
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

@section('script')
@php
  $pieAktif = array_values(array_filter($irisanPie, fn ($s) => $s['jml'] > 0));
  $trenData = [
      'labels' => array_map(fn ($g) => (int) substr($g['tgl'], 8, 2), $grafikGaris),
      'hadir'  => array_column($grafikGaris, 'hadir'),
      'tepat'  => array_column($grafikGaris, 'tepat'),
      'telat'  => array_column($grafikGaris, 'telat'),
      'tidak'  => array_column($grafikGaris, 'tidak'),
      'maks'   => $maksGaris,
  ];
@endphp
<script>
(function () {
  'use strict';

  var KETAATAN = @json($pieAktif);
  var TREN = @json($trenData);

  function palet() {
    var gelap = document.documentElement.classList.contains('dark');
    return {
      tick: gelap ? '#8CA1C0' : '#5C7189',
      grid: gelap ? '#22304A' : '#E4EEF7'
    };
  }

  function init() {
    var elPie = document.getElementById('grafik-ketaatan');
    if (elPie && window.Chart && KETAATAN.length) {
      new Chart(elPie, {
        type: 'doughnut',
        data: {
          labels: KETAATAN.map(function (s) { return s.label; }),
          datasets: [{
            data: KETAATAN.map(function (s) { return s.jml; }),
            backgroundColor: KETAATAN.map(function (s) { return s.warna; }),
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '64%',
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (c) { return c.label + ': ' + c.parsed + ' pegawai'; }
              }
            }
          }
        }
      });
    }

    var grafikTren = null;
    var elTren = document.getElementById('grafik-tren');
    if (elTren && window.Chart && TREN.labels.length > 1) {
      // Garis rincian status (digambar di atas bar)
      function garis(label, data, warna, extra) {
        return Object.assign({
          type: 'line',
          label: label,
          data: data,
          borderColor: warna,
          backgroundColor: warna,
          borderWidth: 2,
          tension: 0,
          pointRadius: 0,
          pointHoverRadius: 5,
          spanGaps: true,
          order: 1
        }, extra || {});
      }

      grafikTren = new Chart(elTren, {
        type: 'bar',
        data: {
          labels: TREN.labels,
          datasets: [
            garis('Terlambat',   TREN.telat, '#D97706'),
            garis('Tidak Absen', TREN.tidak, '#DC2626', { borderDash: [6, 4] }),
            {
              type: 'bar',
              label: 'Tepat Waktu',
              data: TREN.tepat,
              backgroundColor: 'rgba(5, 150, 105, .30)',
              hoverBackgroundColor: 'rgba(5, 150, 105, .55)',
              borderColor: '#059669',
              borderWidth: 1.5,
              borderRadius: 6,
              maxBarThickness: 20,
              order: 2
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function (items) { return 'Tanggal ' + items[0].label + ' {{ BULAN_ID[(int) now()->format("n")] }}'; },
                label: function (c) { return c.dataset.label + ': ' + c.parsed.y + ' pegawai'; }
              }
            }
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { color: palet().tick, font: { size: 11 }, maxRotation: 0, autoSkipPadding: 14 }
            },
            y: {
              beginAtZero: true,
              suggestedMax: TREN.maks,
              ticks: { color: palet().tick, precision: 0 },
              grid: { color: palet().grid }
            }
          }
        }
      });

      // Ikut berganti tema saat toggle dark/light diklik
      var tombolMode = document.getElementById('tombol-mode');
      if (tombolMode) {
        tombolMode.addEventListener('click', function () {
          setTimeout(function () {
            var p = palet();
            if (grafikTren) {
              grafikTren.options.scales.x.ticks.color = p.tick;
              grafikTren.options.scales.y.ticks.color = p.tick;
              grafikTren.options.scales.y.grid.color = p.grid;
              grafikTren.update();
            }
          }, 0);
        });
      }
    }
  }

  if (window.Chart) {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
</script>
@endsection
