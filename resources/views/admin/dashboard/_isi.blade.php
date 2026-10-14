<div class="stat-admin">
  <div class="stat"><span>Total Pegawai Aktif</span><strong>{{ $totalPegawai }}</strong></div>
  <div class="stat hijau"><span>Hadir Hari Ini</span><strong>{{ $hadir }}</strong></div>
  <div class="stat amber"><span>Terlambat Hari Ini</span><strong>{{ $terlambat }}</strong></div>
  <div class="stat"><span>Izin/Sakit/Cuti Hari Ini</span><strong>{{ $izinHariIni }}</strong></div>
  <div class="stat merah"><span>Belum Hadir</span><strong>{{ $belum }}</strong></div>
</div>

@if($menunggu > 0 || $anomali > 0 || $NotActive > 0)
<section class="kartu">
  <div class="aksi-baris">
    @if($menunggu > 0)
      <a class="btn btn-garis " href="{{ url('admin/izin') }}">
        {!! ikon('surat', 15) !!} {{ $menunggu }} pengajuan izin menunggu persetujuan</a>
    @endif

    @if($NotActive > 0)
      <a class="btn btn-garis " href="{{ url('admin/pegawai') }}">
        {!! ikon('user', 15) !!} {{ $NotActive }} akun pegawai Belum aktif</a>
    @endif
    @if($anomali > 0)
      <a class="btn btn-garis " href="{{ url('admin/kehadiran?anomali=1') }}">
        {!! ikon('peringatan', 15) !!} {{ $anomali }} absensi hari ini terindikasi anomali GPS</a>
    @endif
  </div>
</section>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5 items-stretch">
  <section class="kartu mb-0 flex flex-col justify-between">
    <div>
      <div class="kartu-kepala">
        <h2>{!! ikon('centang') !!} Ketaatan Absen Bulan {{ BULAN_ID[(int) now()->format('n')] }} {{ now()->format('Y') }}</h2>
        <span class="teks-redup teks-kecil">{{ $ketaatan['hari_efektif'] }} hari kerja efektif dari {{ $ketaatan['hari_dalam_bulan'] }} hari</span>
      </div>
      @if($ketaatan['total'] > 0)
      <div class="flex flex-col sm:flex-row items-center gap-5">
        <div class="relative shrink-0 w-[150px] h-[150px] sm:w-[160px] sm:h-[160px]">
          <canvas id="grafik-ketaatan" role="img" aria-label="Diagram lingkaran ketaatan absen bulan ini"></canvas>
          <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <strong class="text-xl sm:text-2xl font-bold text-navy leading-none">{{ number_format($persenKetaatan, 2, ',', '.') }}%</strong>
            <span class="text-[0.6rem] sm:text-[0.65rem] text-slate-500 uppercase tracking-widest mt-1">{{ BULAN_ID[(int) now()->format('n')] }}/{{ now()->format('Y') }}</span>
          </div>
        </div>
        <ul class="space-y-2 text-sm w-full sm:flex-1 m-0 p-0 list-none">
          @foreach($irisanPie as $s)
          <li class="flex items-center gap-2">
            <i class="w-3.5 h-3.5 rounded-md shrink-0 inline-block" style="background: {{ $s['warna'] }}"></i>
            <span class="text-slate-700 truncate">{{ $s['label'] }}</span>
            <span class="ml-auto tabular-nums whitespace-nowrap"><strong>{{ $s['jml'] }}</strong>
              <span class="teks-redup teks-kecil">pegawai ({{ number_format($s['pct'], 2, ',', '.') }}%)</span></span>
          </li>
          @endforeach
        </ul>
      </div>
      @else
      <p class="teks-redup tengah m-0">Belum ada data absensi untuk bulan ini.</p>
      @endif
    </div>
  </section>

  <section class="kartu mb-0 flex flex-col justify-between">
    <div>
      <div class="kartu-kepala">
        <h2>{!! ikon('jam') !!} Absen Lebih Awal, Lebih Akhir & Sesuai Jam Bulan {{ BULAN_ID[(int) now()->format('n')] }} {{ now()->format('Y') }}</h2>
        <span class="teks-redup teks-kecil">toleransi ±{{ $lewatJadwal['toleransi'] }} menit · {{ $lewatJadwal['total'] }} pegawai melebihi jadwal</span>
      </div>
      @if($lewatJadwal['ada_data'])
      <div class="flex flex-col sm:flex-row items-center gap-5">
        <div class="relative shrink-0 w-[150px] h-[150px] sm:w-[160px] sm:h-[160px]">
          <canvas id="grafik-lewat-jadwal" role="img" aria-label="Diagram lingkaran pegawai datang lebih awal, pulang lebih akhir, dan sesuai jam bulan ini"></canvas>
          <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
            <strong class="text-xl sm:text-2xl font-bold text-navy leading-none">{{ number_format($lewatJadwal['persen'], 2, ',', '.') }}%</strong>
            <span class="text-[0.6rem] sm:text-[0.65rem] text-slate-500 uppercase tracking-widest mt-1">{{ BULAN_ID[(int) now()->format('n')] }}/{{ now()->format('Y') }}</span>
          </div>
        </div>
        <ul class="space-y-2 text-sm w-full sm:flex-1 m-0 p-0 list-none">
          @foreach($lewatJadwal['irisan'] as $s)
          <li class="flex items-center gap-2">
            <i class="w-3.5 h-3.5 rounded-md shrink-0 inline-block" style="background: {{ $s['warna'] }}"></i>
            <span class="text-slate-700 truncate">{{ $s['label'] }}</span>
            <span class="ml-auto tabular-nums whitespace-nowrap"><strong>{{ $s['jml'] }}</strong>
              <span class="teks-redup teks-kecil">pegawai ({{ number_format($s['pct'], 2, ',', '.') }}%)</span></span>
          </li>
          @endforeach
        </ul>
      </div>
      @else
      <p class="teks-redup tengah m-0">Belum ada data jam absensi pegawai bulan ini.</p>
      @endif
    </div>
  </section>
</div>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Distribusi Shift Pegawai Bulan {{ BULAN_ID[(int) now()->format('n')] }} {{ now()->format('Y') }}</h2>
    <span class="teks-redup teks-kecil">Berdasarkan jadwal shift yang berlaku bulan ini</span>
  </div>
  @if($shiftPie)
  <div class="flex flex-col sm:flex-row items-center gap-6">
    <div class="relative shrink-0 w-[180px] h-[180px]">
      <canvas id="grafik-shift" role="img" aria-label="Diagram lingkaran distribusi shift pegawai bulan ini"></canvas>
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
        <strong class="text-2xl font-bold text-navy leading-none">{{ $shiftTotal ?? 0 }}</strong>
        <span class="text-[0.65rem] text-slate-500 uppercase tracking-widest mt-1">Total Pegawai</span>
      </div>
    </div>
    <ul class="space-y-2.5 text-sm w-full sm:flex-1 m-0 p-0 list-none">
      @foreach($shiftPie as $s)
      <li class="flex items-center gap-2.5">
        <i class="w-3.5 h-3.5 rounded-md shrink-0 inline-block" style="background: {{ $s['warna'] }}"></i>
        <span class="text-slate-700">{{ $s['label'] }}</span>
        <span class="ml-auto tabular-nums whitespace-nowrap"><strong>{{ $s['jml'] }}</strong>
          <span class="teks-redup teks-kecil">pegawai ({{ number_format($s['pct'], 2, ',', '.') }}%)</span></span>
      </li>
      @endforeach
    </ul>
  </div>
  @else
  <p class="teks-redup tengah m-0">Belum ada data jadwal shift untuk bulan ini.</p>
  @endif
</section>

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

@php
  $pieAktif = array_values(array_filter($irisanPie, fn ($s) => $s['jml'] > 0));
  $lewatAktif = array_values(array_filter($lewatJadwal['irisan'], fn ($s) => $s['jml'] > 0));
  $shiftAktif = array_values(array_filter($shiftPie ?? [], fn ($s) => $s['jml'] > 0));
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
    var LEWAT = @json($lewatAktif);
    var SHIFT = @json($shiftAktif);
    var TREN = @json($trenData);

    function palet() {
      var gelap = document.documentElement.classList.contains('dark');
      return {
        tick: gelap ? '#8CA1C0' : '#5C7189',
        grid: gelap ? '#22304A' : '#E4EEF7'
      };
    }

    // Diagram lingkaran untuk kartu ketaatan absen & kartu lewat jadwal
    function donat(el, data) {
      if (!el || !window.Chart || !data.length) {
        return null;
      }
      var ada = Chart.getChart(el);
      if (ada) {
        ada.destroy();
      }
      var ctx = el.getContext('2d');
      return new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: data.map(function (s) { return s.label; }),
          datasets: [{
            data: data.map(function (s) { return s.jml; }),
            backgroundColor: data.map(function (s) { return s.warna; }),
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

    function init() {
      donat(document.getElementById('grafik-ketaatan'), KETAATAN);
      donat(document.getElementById('grafik-lewat-jadwal'), LEWAT);
      donat(document.getElementById('grafik-shift'), SHIFT);

      var grafikTren = null;
      var elTren = document.getElementById('grafik-tren');
      if (elTren && window.Chart && TREN.labels.length > 0) {
        var adaTren = Chart.getChart(elTren);
        if (adaTren) {
          adaTren.destroy();
        }

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

    function jalankan() {
      if (window.Chart) {
        init();
      } else {
        setTimeout(jalankan, 50);
      }
    }

    jalankan();
  })();
</script>