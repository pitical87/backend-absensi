@php
$hariIni = date('Y-m-d');
@endphp
@extends('layouts.pegawai')

@section('content')

<!-- ============ IDENTITAS ============ -->
<section class="kartu identitas">
  <span class="eyebrow">{{ tgl_id($hariIni) }}</span>
  <h1>{{ $u['nama_lengkap'] }}</h1>
  <svg class="denyut denyut-navy" viewBox="0 0 400 26" preserveAspectRatio="none" aria-hidden="true">
    <path d="M0 13h110l10-9 14 18 12-14 8 5h246" fill="none" stroke="currentColor" stroke-width="2"
          stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <div class="identitas-grid">
    <div class="item"><span>NIP</span><strong>{{ $u['nip'] ?: '—' }}</strong></div>
    <div class="item"><span>Jabatan</span><strong>{{ label_jabatan((object)$u) }}</strong></div>
    <div class="item"><span>Unit Kerja</span><strong>{{ unit_organisasi((object)$u) }}</strong></div>
    <div class="item"><span>Profesi</span><strong>{{ $u['profesi_nama'] ?? 'Belum diatur' }}</strong></div>
    <div class="item"><span>Tempat Tugas</span><strong>{{ tempat_tugas((object)$u) }}</strong></div>
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

<!-- ============ SHIFT ============ -->
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Shift Kerja Hari Ini</h2>
    <span class="badge badge-biru angka">{{ label_shift($u['shift_id'] ? (object) [
        'kategori'   => $u['shift_kategori'],
        'jam_masuk'  => $u['shift_jam_masuk'],
        'jam_pulang' => $u['shift_jam_pulang'],
    ] : null) }}</span>
  </div>

  @if($bolehPilihShift)
    <div class="form-grup mb-0">
      <label for="pilih-shift">Ubah shift (berlaku sampai diubah kembali)</label>
      <select id="pilih-shift">
        <option value="">— Pilih shift —</option>
        @foreach($shiftGrup as $kategori => $daftar)
          <optgroup label="Shift {{ $kategori }}">
            @foreach($daftar as $s)
              <option value="{{ (int) $s->id }}" {{ (int) $u['shift_id'] === (int) $s->id ? 'selected' : '' }}>
                {{ jam_singkat($s->jam_masuk) }} - {{ jam_singkat($s->jam_pulang) }}
              </option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
      <div class="petunjuk">Shift yang dipilih tetap aktif setiap hari sampai dilakukan perubahan
        berikutnya oleh Anda atau admin.</div>
    </div>
  @else
    <p class="teks-redup teks-kecil mb-0">
      @if(!$bolehDatang)
        Shift terkunci karena absensi hari ini sudah berjalan. Perubahan shift dapat dilakukan oleh admin.
      @else
        Pengaturan shift dilakukan oleh admin atau petugas yang berwenang.
      @endif
    </p>
  @endif
</section>

<!-- ============ ABSENSI ============ -->
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('peta') !!} Absensi Kehadiran</h2>
    @if($wajibSelfie)
      <span class="teks-redup teks-kecil">{!! ikon('kamera', 14) !!} disertai foto selfie</span>
    @endif
  </div>

  <div class="absen-baris">
    <button type="button" id="btn-datang" class="btn-absen btn-datang" {{ $bolehDatang ? '' : 'disabled' }}>
      <span class="lingkar">{!! ikon('masuk', 26) !!}</span>
      <span>
        <strong>ABSEN DATANG</strong>
        <small id="ket-datang">
          @if($recTampil && $recTampil->waktu_masuk)
            Tercatat pukul {{ jam_id($recTampil->waktu_masuk) }}
          @else 
            Tekan saat tiba di lokasi RSUD
          @endif
        </small>
      </span>
    </button>

    <button type="button" id="btn-pulang" class="btn-absen btn-pulang" {{ $bolehPulang ? '' : 'disabled' }}>
      <span class="lingkar">{!! ikon('pulang', 26) !!}</span>
      <span>
        <strong>ABSEN PULANG</strong>
        <small id="ket-pulang">
          @if($recTampil && $recTampil->waktu_pulang)
            Tercatat pukul {{ jam_id($recTampil->waktu_pulang) }}
          @elseif($recBuka)
            Tekan saat mengakhiri tugas hari ini
          @else 
            Lakukan absen datang terlebih dahulu
          @endif
        </small>
      </span>
    </button>
  </div>

  <div id="hasil-absen">
    @if($selesai)
      <div class="pesan-hasil pesan-info">{!! ikon('centang', 20) !!}
        <span>Absensi Anda hari ini sudah lengkap. Terima kasih atas dedikasi Anda hari ini.</span>
      </div>
    @elseif($recBuka && $recBuka->tanggal !== $hariIni)
      <div class="pesan-hasil pesan-info">{!! ikon('info', 20) !!}
        <span>Anda masih tercatat bertugas pada shift tanggal {{ tgl_id($recBuka->tanggal, false) }}
          (shift malam). Silakan absen pulang untuk menutupnya.</span>
      </div>
    @elseif($izinHariIni)
      <div class="pesan-hasil pesan-info">{!! ikon('surat', 20) !!}
        <span>Anda tercatat <strong>{{ $izinHariIni->jenis }}</strong>
          ({{ tgl_id($izinHariIni->tanggal_mulai, false) }} s.d.
          {{ tgl_id($izinHariIni->tanggal_selesai, false) }}) — tidak perlu absen hari ini.</span>
      </div>
    @endif
  </div>

  <div class="jam-hari-ini">
    <div class="kotak"><span>Jam Masuk</span>
      <strong id="jam-masuk">{{ jam_id($recTampil->waktu_masuk ?? null) }}</strong></div>
    <div class="kotak"><span>Jam Pulang</span>
      <strong id="jam-pulang">{{ jam_id($recTampil->waktu_pulang ?? null) }}</strong></div>
  </div>

  <div class="status-gps" id="status-gps">
    <span class="titik"></span>
    <span id="teks-gps">Sistem memerlukan izin lokasi (GPS){{ $wajibSelfie ? ' dan kamera' : '' }}
      saat Anda menekan tombol absen.</span>
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

@section('skrip')
<script>
const ABSEN_CFG = {
  urlAbsen: @json(url('absen')),
  urlShift: @json(url('pilih-shift')),
  wajibSelfie: {{ $wajibSelfie ? 'true' : 'false' }},
};
</script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@endsection
