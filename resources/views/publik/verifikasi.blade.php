<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Dokumen — RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="latar-pola">
<header class="bg-gradient-to-r from-navy to-biru text-white shadow-[0_1px_2px_rgba(11,59,102,.06),0_6px_18px_rgba(11,59,102,.07)] sticky top-0 z-40">
  <div class="max-w-[820px] mx-auto py-[10px] px-4 flex items-center gap-3">
    <a class="flex min-w-0 items-center gap-2.5 text-white no-underline hover:no-underline" href="{{ url('/') }}">
      <img class="w-[38px] h-[38px] shrink-0" src="{{ asset('assets/img/logo.svg') }}" alt="">
      <span class="flex flex-col items-start text-white">
        <span class="block text-[0.7rem] tracking-[0.08em] uppercase text-white">Sistem Absensi</span>
        <strong class="block text-[0.95rem] leading-tight text-white">RSUD Merauke</strong>
      </span>
    </a>
  </div>
</header>
<main class="max-w-[640px] mx-auto py-[18px] px-4 pb-14">
  <section class="kartu">
    <div class="kartu-kepala"><h2>{!! ikon('perisai') !!} Verifikasi Keabsahan Surat Izin/Cuti</h2></div>
    <form method="get" action="{{ url('verifikasi') }}" class="bilah-alat">
      <input type="text" name="kode" placeholder="Masukkan kode verifikasi…" value="{{ $kode }}"
             class="uppercase" required>
      <button type="submit" class="btn btn-navy btn-kecil">Periksa</button>
    </form>

    @if($kode !== '')
      @if($hasil)
        <div class="flash flash-success mt-4">Dokumen dengan kode ini sah dan tercatat dalam sistem.</div>
        <table class="tabel mt-2.5">
          <tr><th class="w-[180px]">Nomor Surat</th><td>{{ $hasil->nomor_surat ?? '—' }}</td></tr>
          <tr><th>Nama Pegawai</th><td>{{ $hasil->user?->nama_lengkap }}</td></tr>
          <tr><th>NIP</th><td>{{ $hasil->user?->nip ?: '—' }}</td></tr>
          <tr><th>Jenis</th><td>{{ $hasil->jenis_cuti ?: $hasil->jenis }}</td></tr>
          <tr><th>Periode</th><td>{{ tgl_id($hasil->tanggal_mulai, false) }} s.d.
              {{ tgl_id($hasil->tanggal_selesai, false) }} ({{ (int) $hasil->lama_hari }} hari kerja)</td></tr>
          <tr><th>Status</th><td>{!! badge_izin($hasil->status) !!}</td></tr>
          <tr><th>Tanda Tangan</th>
            <td>{{ $hasil->ttd_digital
                  ? 'Elektronik oleh ' . $hasil->ttdOleh?->nama_lengkap . ' pada ' . tgl_id($hasil->ttd_waktu, false)
                  : 'Manual (ditandatangani basah oleh Direktur)' }}</td></tr>
        </table>
      @else
        <div class="flash flash-error mt-4">
          Kode verifikasi tidak ditemukan. Periksa kembali penulisan kode pada dokumen.</div>
      @endif
    @endif
  </section>
</main>
<footer class="text-center text-[0.74rem] text-teks-redup py-2.5 px-4 pb-7">Sistem Absensi Pegawai RSUD Merauke · {{ date('Y') }}</footer>
</body>
</html>
