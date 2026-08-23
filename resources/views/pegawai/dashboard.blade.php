@php
$hariIni = date('Y-m-d');
@endphp
@extends('layouts.pegawai')

@section('content')

<!-- ============ IDENTITAS ============ -->
<section class="bg-blue-500 text-white p-4 rounded-xl mb-3">
  <span class="text-white">{{ tgl_id($hariIni) }}</span>
  <div class="text-4xl text-white font-bold" >Hai, {{ $u['nama_lengkap'] }}</div>
  
  <div class="identitas-grid">
    <div class="item"><span>Status Hari Ini</span>
      <strong>
        @if ($selesai)
          Absensi Lengkap
        @elseif ($recBuka)
          Sedang Bertugas
        @elseif ($izinHariIni)
          {{ $izinHariIni->jenis }}
        @else
          Belum Absen
        @endif
      </strong>
    </div>
  </div>
</section>


<!-- ============ REKAP BULANAN ============ -->
@php
$bulan = (int) date('n');
$tahun = (int) date('Y');
$warna = ['tepat' => '#178A50', 'telat' => '#D9930D', 'jalan' => '#2F87DE',
          'izin' => '#8A5FC8', 'alpa' => '#E5B4B4', 'libur' => '#B9C7D6'];
@endphp
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Rekap Kehadiran Bulan Ini</h2>
    <span class="teks-redup teks-kecil">{{ BULAN_ID[$bulan] . ' ' . $tahun }}</span>
  </div>

  <div class="stat-grid">
    <div class="stat"><span>Hari Efektif</span><strong>{{ $rekap['hari_efektif'] }}</strong></div>
    <div class="stat"><span>Hadir</span><strong>{{ $rekap['hadir'] }}</strong></div>
    <div class="stat hijau"><span>Tepat Waktu</span><strong>{{ $rekap['tepat'] }}</strong></div>
    <div class="stat amber"><span>Terlambat</span><strong>{{ $rekap['terlambat'] }}</strong></div>
    <div class="stat merah"><span>Alpa</span><strong>{{ $rekap['alpa'] }}</strong></div>
    <div class="stat"><span>Izin/Sakit/Cuti</span>
      <strong>{{ $rekap['izin'] + $rekap['sakit'] + $rekap['cuti'] }}</strong></div>
    <div class="stat"><span>Total Jam Kerja</span>
      <strong class="text-[1.02rem]">{{ menit_ke_teks($rekap['total_menit']) }}</strong></div>
    <div class="stat"><span>Kehadiran</span><strong>{{ $rekap['persen'] }}%</strong></div>
  </div>
  <p class="petunjuk">Hari efektif tidak menghitung hari libur dan izin/sakit/cuti yang disetujui,
    sehingga persentase kehadiran Anda tetap adil.</p>

  @if($rekap['hari_berjalan'] > 0)
    @php $lebar = 40 + $rekap['hari_dalam_bulan'] * 20; @endphp
    <h3 class="mt-4">Grafik Jam Kerja Harian</h3>
    <div class="grafik-bungkus">
      <svg viewBox="0 0 {{ $lebar }} 160" role="img" aria-label="Grafik jam kerja harian bulan ini">
        @foreach([0, 4, 8, 12] as $j) @php $y = 130 - $j / 12 * 100; @endphp
          <line x1="30" y1="{{ $y }}" x2="{{ $lebar - 6 }}" y2="{{ $y }}" stroke="#D7E5F2" stroke-width="1"/>
          <text x="24" y="{{ $y + 3 }}" font-size="9" fill="#5C7189" text-anchor="end">{{ $j }}j</text>
        @endforeach
        @for($d = 1; $d <= $rekap['hari_berjalan']; $d++)
            @php
            $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $inf = $rekap['per_tanggal'][$tgl];
            $x   = 34 + ($d - 1) * 20;
            $rec = $inf['rec'];
            @endphp
            @if($rec)
                @php
                $kelas = ! $rec['waktu_pulang'] ? 'jalan'
                       : ($rec['status_masuk'] === 'Terlambat' ? 'telat' : 'tepat');
                $menit = ! $rec['waktu_pulang']
                       ? min(720, max(0, (int) floor((time() - strtotime($rec['waktu_masuk'])) / 60)))
                       : min(720, (int) $rec['total_menit_kerja']);
                $h = max(3, $menit / 720 * 100);
                @endphp
              <rect x="{{ $x }}" y="{{ 130 - $h }}" width="12" height="{{ $h }}" rx="2.5"
                    fill="{{ $warna[$kelas] }}">
                <title>Tanggal {{ $d }}: {{ menit_ke_teks($menit) }}</title>
              </rect>
            @elseif(in_array($inf['status'], ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'], true))
              <rect x="{{ $x }}" y="122" width="12" height="8" rx="2" fill="{{ $warna['izin'] }}">
                <title>Tanggal {{ $d }}: {{ $inf['status'] }}</title>
              </rect>
            @elseif($inf['status'] === 'Libur')
              <rect x="{{ $x }}" y="127" width="12" height="3" rx="1.5" fill="{{ $warna['libur'] }}"/>
            @else
              <rect x="{{ $x }}" y="127" width="12" height="3" rx="1.5" fill="{{ $warna['alpa'] }}"/>
            @endif
            <text x="{{ $x + 6 }}" y="143" font-size="8.5" fill="#5C7189" text-anchor="middle">{{ $d }}</text>
        @endfor
      </svg>
    </div>
    <div class="legenda">
      <span><i class="bg-[#178A50]"></i> Tepat waktu</span>
      <span><i class="bg-[#D9930D]"></i> Terlambat</span>
      <span><i class="bg-[#2F87DE]"></i> Sedang bertugas</span>
      <span><i class="bg-[#8A5FC8]"></i> Izin/Sakit/Cuti/Dinas</span>
      <span><i class="bg-[#B9C7D6]"></i> Libur</span>
      <span><i class="bg-[#E5B4B4]"></i> Alpa</span>
    </div>
  @endif

  <h3 class="mt-5">Tabel Rekap</h3>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Tanggal</th><th>Shift</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr>
      </thead>
      <tbody>
        @for($d = $rekap['hari_berjalan']; $d >= 1; $d--)
            @php
            $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $inf = $rekap['per_tanggal'][$tgl];
            $rec = $inf['rec'];
            @endphp
        <tr>
          <td class="angka">{{ tgl_id($tgl, false) }}</td>
          <td>{{ $rec ? label_shift( (object) ['kategori' => $rec['shift_kategori'],
                'jam_masuk' => $rec['shift_masuk'], 'jam_pulang' => $rec['shift_pulang']]) : '—' }}</td>
          <td class="angka">{{ jam_id($rec['waktu_masuk'] ?? null) }}</td>
          <td class="angka">{{ jam_id($rec['waktu_pulang'] ?? null) }}</td>
          <td>{!! badge_status($inf['status'], (int) ($rec['menit_terlambat'] ?? 0)) !!}</td>
        </tr>
        @endfor
        @if($rekap['hari_berjalan'] === 0)
          <tr><td colspan="5" class="tengah teks-redup">Belum ada data pada bulan ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

<!-- ============ MODAL KAMERA SELFIE ============ -->
<div class="modal-tirai" id="modal-kamera">
  <div class="modal-kamera">
    <header>{!! ikon('kamera', 19) !!} Foto Selfie Absensi</header>
    <div class="isi">
      <div class="bingkai-kamera">
        <video id="kamera-video" autoplay playsinline muted></video>
        <img id="kamera-hasil" alt="" hidden>
        <div class="garis"></div>
      </div>
      <p class="ket" id="kamera-ket">Posisikan wajah Anda di dalam bingkai, lalu ambil foto.</p>
      <div class="aksi-baris">
        <button type="button" class="btn btn-garis btn-kecil" id="kamera-batal">Batal</button>
        <button type="button" class="btn btn-garis btn-kecil" id="kamera-ulang" hidden>Ulangi</button>
        <button type="button" class="btn btn-navy btn-kecil" id="kamera-ambil">{!! ikon('kamera', 15) !!} Ambil Foto</button>
        <button type="button" class="btn btn-primer btn-kecil" id="kamera-kirim" hidden>{!! ikon('centang', 15) !!} Kirim Absen</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
const ABSEN_CFG = {
  urlAbsen: @json(url('absen')),
  urlShift: @json(url('pilih-shift')),
  wajibSelfie: {{ $wajibSelfie ? 'true' : 'false' }},
};
</script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@endsection
