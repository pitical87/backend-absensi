<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul ?? 'Sistem Absensi Pegawai RSUD Merauke' }}</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="latar-pola">
<div class="min-h-dvh flex items-center justify-center p-7 md:p-4">
  <div class="w-full {{ (! empty($lebar)) ? 'max-w-[640px]' : 'max-w-[420px]' }} bg-putih border border-garis rounded-xl overflow-hidden">
    <div class="text-center py-[26px] px-6 pb-[14px]">
      <img class="w-[76px] h-[76px] mx-auto mb-2.5 block" src="{{ asset('assets/img/logo.svg') }}" alt="Logo RSUD Merauke">
      <h1 class="text-[1.12rem] text-white m-0">{!! $judulKartu ?? 'Sistem Absensi Pegawai<br>RSUD Merauke' !!}</h1>
      <p class="mt-0.5 text-[0.8rem] ">{{ $subJudul ?? 'Kabupaten Merauke — Papua Selatan' }}</p>
    </div>
    <div class="p-6">
      @if(session('success'))
        <div class="flash flash-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="flash flash-error">{{ session('error') }}</div>
      @endif
      @yield('content')
    </div>
  </div>
</div>
</body>
</html>
