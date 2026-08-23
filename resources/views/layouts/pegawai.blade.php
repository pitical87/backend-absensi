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
<body class="latar-pola text-slate-800 antialiased min-h-screen flex flex-col justify-between">

{{-- HEADER PEGAWAI DENGAN TEMA BIRU MODERN --}}
<header class="bg-white  text-blue-500  sticky top-0 z-40 transition-all shadow-sm">
  <div class="max-w-[860px] mx-auto py-3 px-4 sm:px-6 flex items-center justify-between gap-3">
    
    {{-- Brand Logo & Info --}}
    <a class="flex min-w-0 items-center gap-3 text-white no-underline hover:no-underline group" href="{{ route('dashboard') }}">
      <div class="w-10 h-10   flex items-center justify-center shrink-0  group-hover:scale-105 transition-transform">
        <img class="w-8 h-8 object-contain" src="{{ asset('assets/img/logo.svg') }}" alt="Logo RSUD Merauke">
      </div>
      <span class="flex flex-col items-start text-blue-500">
        <strong class="block text-[0.95rem] font-bold leading-tight tracking-tight">{{env('APP_NAME' ?? 'RSUD MERAUKE')}}</strong>
      </span>
    </a>

    {{-- Navigasi Aksi Pegawai --}}
  @include('layouts.partials.navbar-pegawai')
  </div>
</header>

{{-- KONTEN UTAMA --}}
<main class="max-w-[860px] w-full mx-auto py-6 px-4 sm:px-6 flex-1">
  @if(session('success'))
    <div class="flash flash-success">
      <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if(session('error'))
    <div class="flash flash-error">
      <svg class="w-5 h-5 shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @yield('content')
</main>

{{-- FOOTER --}}
<footer class="text-center text-xs text-slate-400 py-4 px-4 pb-8 font-normal">
  &copy; {{ date('Y') }} PIT RSUD Merauke · Sistem Absensi Pegawai
</footer>

@yield('script')

@if(session('uid') && session('email'))
<script>
(function () {
  try {
    const KEY = 'RSUD_MERAUKE_ABSENSI_SECURE_V1_2026';
    const SALT = 0x7e;
    const STORAGE_KEY = 'rsud_merauke_login_as_enc';
    function encrypt(text) {
      const utf8 = encodeURIComponent(text);
      let res = '';
      for (let i = 0; i < utf8.length; i++) {
        const c = utf8.charCodeAt(i);
        const k = KEY.charCodeAt(i % KEY.length);
        const e = (c ^ k ^ SALT) & 0xff;
        res += e.toString(16).padStart(2, '0');
      }
      return btoa(res);
    }
    function decrypt(cipher) {
      try {
        const hex = atob(cipher);
        let utf8 = '';
        for (let i = 0; i < hex.length; i += 2) {
          const e = parseInt(hex.substr(i, 2), 16);
          const k = KEY.charCodeAt((i / 2) % KEY.length);
          const c = (e ^ k ^ SALT) & 0xff;
          utf8 += String.fromCharCode(c);
        }
        return decodeURIComponent(utf8);
      } catch (e) { return null; }
    }
    const raw = localStorage.getItem(STORAGE_KEY);
    let list = [];
    if (raw) {
      try { list = JSON.parse(decrypt(raw) || '[]'); } catch (e) { list = []; }
    }
    const email = @json(session('email'));
    const nama = @json(session('nama'));
    list = list.filter(u => u.email.toLowerCase() !== email.toLowerCase());
    list.unshift({
      email: email,
      name: nama || email.split('@')[0],
      initial: (nama || email).trim().charAt(0).toUpperCase(),
      role: @json(session('posisi') ?? 'Pegawai'),
      lastLogin: Date.now()
    });
    list = list.slice(0, 5);
    localStorage.setItem(STORAGE_KEY, encrypt(JSON.stringify(list)));
  } catch (e) {}
})();
</script>
@endif
</body>
</html>
