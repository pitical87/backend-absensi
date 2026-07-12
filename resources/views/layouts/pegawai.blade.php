<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="{{ csrf_token() }}">
<title>@isset($judul){{ $judul }}@else Dasbor Pegawai @endisset — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="latar-pola">
<header class="topbar">
  <div class="topbar-isi">
    <a class="topbar-judul" href="{{ route('dashboard') }}">
      <img class="logo" src="{{ asset('assets/img/logo.svg') }}" alt="">
      <span class="topbar-merek">
        <span class="baris-kecil">Sistem Absensi</span>
        <strong>RSUD Merauke</strong>
      </span>
    </a>
    <nav class="aksi-baris">
      @if(session('posisi') && session('posisi') !== 'Staf')
        <a class="btn btn-garis btn-kecil" href="{{ route('persetujuan') }}">{!! ikon('centang', 15) !!} Persetujuan</a>
      @endif
      <a class="btn btn-garis btn-kecil" href="{{ route('izin') }}">{!! ikon('surat', 15) !!} Izin / Cuti</a>
      <a class="btn-keluar" href="{{ route('logout') }}">{!! ikon('keluar', 16) !!} Keluar</a>
    </nav>
  </div>
</header>
<main class="wadah">
  @if(session('flash_sukses'))
    <div class="flash flash-sukses">{{ session('flash_sukses') }}</div>
  @endif
  @if(session('flash_gagal'))
    <div class="flash flash-gagal">{{ session('flash_gagal') }}</div>
  @endif
  @yield('content')
</main>
<footer class="kaki">Sistem Absensi Pegawai RSUD Merauke · {{ date('Y') }}</footer>
@yield('skrip')
</body>
</html>
