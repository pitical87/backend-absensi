<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="{{ csrf_token() }}">
<title>{{ $judulHalaman ?? 'Admin' }} — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
<script>
  (function () {
    try {
      if (localStorage.getItem('tema-admin') === 'gelap') {
        document.documentElement.classList.add('dark');
      }
    } catch (e) {}
  })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-latar text-slate-800 antialiased selection:bg-[#007afc] selection:text-white">
<div class="flex min-h-screen">

  {{-- SIDEBAR --}}
  @include('layouts.partials.sidebar')

  {{-- MAIN CONTAINER --}}
  <div class="flex-1 min-w-0 flex flex-col">
    
    {{-- NAVBAR / HEADER --}}
    @include('layouts.partials.navbar')

    {{-- PAGE CONTENT --}}
    <main class="p-4 sm:p-6 lg:p-7 max-w-[1240px] w-full mx-auto flex-1">
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
    @include('layouts.partials.footer')
  </div>
</div>

<script>
(function () {
  const tombolMenu = document.getElementById('tombol-menu');
  const sidebar = document.getElementById('sidebar');
  const tirai = document.getElementById('tirai');

  function bukaSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    tirai.classList.remove('hidden');
  }

  function tutupSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebar.classList.remove('translate-x-0');
    tirai.classList.add('hidden');
  }

  if (tombolMenu && sidebar) {
    tombolMenu.addEventListener('click', function (e) {
      e.stopPropagation();
      if (sidebar.classList.contains('-translate-x-full')) {
        bukaSidebar();
      } else {
        tutupSidebar();
      }
    });
  }

  if (tirai) {
    tirai.addEventListener('click', tutupSidebar);
  }
})();
</script>

@yield('script')

@if(session('uid') && session('email'))
@include('layouts.partials.script')
@endif
</body>
</html>
