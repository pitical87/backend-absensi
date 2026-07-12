<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul ?? 'Sistem Absensi Pegawai RSUD Merauke' }}</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="latar-pola">
<div class="auth-bungkus">
  <div class="auth-kartu {{ ! empty($lebar) ? 'lebar' : '' }}">
    <div class="auth-kepala">
      <img class="logo" src="{{ asset('assets/img/logo.svg') }}" alt="Logo RSUD Merauke">
      <h1>{!! $judulKartu ?? 'Sistem Absensi Pegawai<br>RSUD Merauke' !!}</h1>
      <p>{{ $subJudul ?? 'Kabupaten Merauke — Papua Selatan' }}</p>
      <svg class="denyut denyut-navy" viewBox="0 0 400 26" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 13h130l10-9 14 18 12-14 8 5h226" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="auth-isi">
      @if(session('flash_sukses'))
        <div class="flash flash-sukses">{{ session('flash_sukses') }}</div>
      @endif
      @if(session('flash_gagal'))
        <div class="flash flash-gagal">{{ session('flash_gagal') }}</div>
      @endif
      @yield('content')
    </div>
  </div>
</div>
</body>
</html>
