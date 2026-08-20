<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="{{ csrf_token() }}">
<title>@isset($judul){{ $judul }}@else Dasbor Pegawai @endisset — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="latar-pola">
<header class="bg-gradient-to-r from-navy to-biru text-white shadow-[0_1px_2px_rgba(11,59,102,.06),0_6px_18px_rgba(11,59,102,.07)] sticky top-0 z-40">
  <div class="max-w-[820px] mx-auto py-[10px] px-4 flex items-center gap-3">
    <a class="flex min-w-0 items-center gap-2.5 text-white no-underline hover:no-underline" href="{{ route('dashboard') }}">
      <img class="w-[38px] h-[38px] shrink-0" src="{{ asset('assets/img/logo.svg') }}" alt="">
      <span class="flex flex-col items-start text-white">
        <span class="block text-[0.7rem] tracking-[0.08em] uppercase text-white">Sistem Absensi</span>
        <strong class="block text-[0.95rem] leading-tight text-white">RSUD Merauke</strong>
      </span>
    </a>
    <nav class="aksi-baris ml-auto">
      @if(session('posisi') && session('posisi') !== 'Staf')
        <a class="btn btn-garis btn-kecil" href="{{ route('persetujuan') }}">{!! ikon('centang', 15) !!} Persetujuan</a>
      @endif
      <a class="btn btn-garis btn-kecil" href="{{ route('izin') }}">{!! ikon('surat', 15) !!} Izin / Cuti</a>
      <a class="py-[7px] px-3 text-[0.82rem] text-white bg-white/14 border border-white/35 rounded-[9px] hover:bg-white/25 no-underline" href="{{ route('logout') }}">{!! ikon('keluar', 16) !!} Keluar</a>
    </nav>
  </div>
</header>
<main class="max-w-[820px] mx-auto py-[18px] px-4 pb-14">
  @if(session('success'))
    <div class="flash flash-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="flash flash-error">{{ session('error') }}</div>
  @endif
  @yield('content')
</main>
<footer class="text-center text-[0.74rem] text-teks-redup py-2.5 px-4 pb-7">Sistem Absensi Pegawai RSUD Merauke · {{ date('Y') }}</footer>
@yield('skrip')
</body>
</html>
