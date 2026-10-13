<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="{{ csrf_token() }}">
<title>{{ $judulHalaman ?? 'Admin' }} — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ aset_admin('img/logo.svg') }}">
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
      <div id="konten-admin">
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
      </div>

      {{-- SKELETON LOADING (hanya area konten; sidebar/navbar tetap) --}}
      <div id="skeleton-admin" class="hidden min-h-[60vh]" aria-hidden="true">
        <div class="skel h-6 w-48 mb-2"></div>
        <div class="skel h-3.5 w-72 mb-5"></div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          @for($i = 0; $i < 4; $i++)
            <div class="kartu">
              <div class="flex items-center justify-between mb-3">
                <div class="skel h-3.5 w-20"></div>
                <div class="skel h-8 w-8 rounded-full"></div>
              </div>
              <div class="skel h-7 w-16 mb-2"></div>
              <div class="skel h-3 w-24"></div>
            </div>
          @endfor
        </div>

        <div class="kartu">
          <div class="flex flex-wrap items-center gap-2 mb-4">
            <div class="skel h-9 w-44"></div>
            <div class="skel h-9 w-32"></div>
            <div class="skel h-9 w-24 ms-auto"></div>
          </div>
          <div class="flex items-center gap-3 pb-2.5 mb-1 border-b border-slate-200 dark:border-slate-700">
            <div class="skel h-3 w-6"></div>
            <div class="skel h-3 flex-1"></div>
            <div class="skel h-3 w-24"></div>
            <div class="skel h-3 w-20"></div>
          </div>
          @for($i = 0; $i < 7; $i++)
            <div class="flex items-center gap-3 py-3 border-b border-slate-100 dark:border-slate-800/60 last:border-0">
              <div class="skel h-8 w-8 rounded-full"></div>
              <div class="skel h-3.5 flex-1"></div>
              <div class="skel h-3.5 w-28"></div>
              <div class="skel h-3.5 w-16"></div>
            </div>
          @endfor
        </div>
      </div>
    </main>

    {{-- FOOTER --}}
    @include('layouts.partials.footer')
  </div>
</div>

<style>
  html.memuat-halaman,
  html.memuat-halaman * { cursor: progress !important; }

  /* Kerangka abu-abu dengan kilau bergerak agar terasa "hidup". */
  .skel {
    position: relative;
    overflow: hidden;
    background-color: #e2e8f0;
    border-radius: .5rem;
  }
  .dark .skel { background-color: #1e293b; }
  .skel::after {
    content: "";
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.65), transparent);
    animation: skel-gelombang 1.5s ease-in-out infinite;
  }
  .dark .skel::after {
    background: linear-gradient(90deg, transparent, rgba(148,163,184,.18), transparent);
  }
  @keyframes skel-gelombang { 100% { transform: translateX(100%); } }
  @media (prefers-reduced-motion: reduce) {
    .skel::after { animation: none; }
  }
</style>

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

<script>
// ── Skeleton loading saat berpindah halaman admin ──
// Aplikasi ini multi-halaman (server-render), jadi jeda terasa paling saat
// berpindah menu. Overlay kerangka ditampilkan begitu navigasi dimulai supaya
// halaman lama tidak "membeku", lalu hilang ketika halaman baru tampil.
(function () {
  var SK = document.getElementById('skeleton-admin');
  var konten = document.getElementById('konten-admin');
  if (!SK) return;

  var jedaPengaman = null;

  function tampil() {
    if (konten) konten.classList.add('hidden');
    SK.classList.remove('hidden');
    document.documentElement.classList.add('memuat-halaman');
    // Pengaman: bila navigasi ternyata dibatalkan, konten tidak tersembunyi
    // selamanya.
    clearTimeout(jedaPengaman);
    jedaPengaman = setTimeout(sembunyi, 12000);
  }

  function sembunyi() {
    SK.classList.add('hidden');
    if (konten) konten.classList.remove('hidden');
    document.documentElement.classList.remove('memuat-halaman');
    clearTimeout(jedaPengaman);
  }

  // Halaman baru (termasuk restore dari back-forward cache) menutup skeleton.
  window.addEventListener('pageshow', sembunyi);

  function tautanLayak(a) {
    if (!a || !a.href || a.hasAttribute('download')) return false;
    if (a.dataset.noSkeleton !== undefined) return false;
    if (a.target && a.target !== '_self') return false;

    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#') return false;
    if (/^(mailto:|tel:|javascript:)/i.test(href)) return false;

    try {
      var tujuan = new URL(a.href, location.href);
      if (tujuan.origin !== location.origin) return false;
      // Anchor di halaman yang sama bukan navigasi halaman.
      if (tujuan.pathname === location.pathname
          && tujuan.search === location.search
          && tujuan.hash) return false;
    } catch (e) {
      return false;
    }
    return true;
  }

  document.addEventListener('click', function (e) {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!tautanLayak(a)) return;
    // Ditunda satu tick agar handler lain (mis. modal/AJAX) sempat membatalkan.
    setTimeout(function () { if (!e.defaultPrevented) tampil(); }, 0);
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.dataset.noSkeleton !== undefined) return;
    if (form.target && form.target !== '_self') return;
    setTimeout(function () { if (!e.defaultPrevented) tampil(); }, 0);
  });

  // Tutup manual bila navigasi tertahan.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') sembunyi();
  });

  // Bisa dipakai skrip lain (mis. navigasi lewat AJAX/JS).
  window.tampilkanSkeleton = tampil;
  window.sembunyikanSkeleton = sembunyi;
})();
</script>

@yield('script')

@if(session('uid') && session('email'))
@include('layouts.partials.script')
@endif
</body>
</html>
