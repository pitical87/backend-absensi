@php
$menuAktif = $menuAktif ?? '';
$badgeIzin = $badgeIzin ?? 0;
$menus = [
    'dashboard'  => ['admin',            'beranda',  'Dashboard'],
    'pegawai'    => ['admin/pegawai',    'pegawai',  'Data Pegawai'],
    'unit'       => ['admin/unit',       'gedung',   'Data Unit Kerja'],
    'struktur'   => ['admin/struktur',   'struktur', 'Struktur Organisasi'],
    'shift'      => ['admin/shift',      'jam',      'Pengaturan Shift'],
    'jadwal'     => ['admin/jadwal',     'kalender', 'Jadwal Shift'],
    'kehadiran'  => ['admin/kehadiran',  'peta',     'Data Kehadiran'],
    'izin'       => ['admin/izin',       'surat',    'Persetujuan Izin'],
    'libur'      => ['admin/libur',      'kalender', 'Hari Libur'],
    'rekap'      => ['admin/rekap',      'grafik',   'Rekap Bulanan'],
    'aktivitas'  => ['admin/aktivitas',  'log',      'Log Aktivitas'],
    'pengaturan' => ['admin/pengaturan', 'atur',     'Pengaturan'],
];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="{{ csrf_token() }}">
<title>{{ $judulHalaman ?? 'Admin' }} — Absensi RSUD Merauke</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="flex min-h-screen">

  <aside class="w-[236px] shrink-0 bg-gradient-to-b from-navy to-navy-tua text-white flex flex-col sticky top-0 h-screen z-50 max-lg:fixed max-lg:left-0 max-lg:top-0 max-lg:bottom-0 max-lg:h-dvh max-lg:w-[236px] max-lg:-translate-x-full max-lg:transition-transform max-lg:duration-200 max-lg:shadow-[0_10px_30px_rgba(11,59,102,.16)]" id="sidebar">
    <div class="flex items-center gap-2.5 py-3.5 px-[18px] pb-2.5">
      <img class="w-9 h-9 shrink-0" src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
      <div><strong class="block text-[0.9rem] leading-tight">RSUD Merauke</strong><span class="text-[0.62rem] tracking-[0.1em] uppercase opacity-75">Sistem Absensi — Admin</span></div>
    </div>
    <nav class="flex-1 py-1 px-2.5 overflow-y-auto">
      @foreach($menus as $kunci => [$jalur, $namaIkon, $label])
        <a class="nav-item {{ $menuAktif === $kunci ? 'aktif' : '' }}" href="{{ url($jalur) }}">
          {!! ikon($namaIkon, 16) !!}<span>{{ $label }}</span>
          @if($kunci === 'izin' && $badgeIzin > 0)
            <span class="badge badge-amber ml-auto">{{ $badgeIzin }}</span>
          @endif
        </a>
      @endforeach
    </nav>
    <div class="py-2 px-[18px] pb-3 text-[0.68rem] text-white/55">
      <a class="nav-item" href="{{ route('dashboard') }}">{!! ikon('pegawai', 18) !!}<span>Tampilan Pegawai</span></a>
      <a class="nav-item" href="{{ route('logout') }}">{!! ikon('keluar', 18) !!}<span>Keluar</span></a>
    </div>
  </aside>

  <div class="hidden fixed inset-0 bg-navy-tua/45 z-[45] max-lg:block" id="tirai"></div>

  <div class="flex-1 min-w-0">
    <header class="bg-putih border-b border-garis py-3 px-[22px] flex items-center gap-3 sticky top-0 z-40">
      <button type="button" class="hidden inline-flex bg-transparent border-0 text-navy cursor-pointer p-1 max-lg:inline-flex" id="tombol-menu" aria-label="Buka menu">{!! ikon('menu', 20) !!}</button>
      <h1 class="m-0 text-[1.1rem] flex-1">{{ $judulHalaman ?? '' }}</h1>
      <div class="text-[0.82rem] text-teks-redup">
        <span class="teks-kecil teks-redup">{{ tgl_id(date('Y-m-d')) }}</span>
        <strong class="ml-1">{{ session('nama') ?? 'Admin' }}</strong>
      </div>
    </header>
    <main class="p-[22px] max-w-[1180px] max-lg:p-4">
      @if(session('success'))
        <div class="flash flash-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="flash flash-error">{{ session('error') }}</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
<script>
(function () {
  var t = document.getElementById('tombol-menu'), s = document.getElementById('sidebar'),
      r = document.getElementById('tirai');
  function tutup(){ s.classList.remove('max-lg:translate-x-0'); r.classList.remove('block'); r.classList.add('hidden'); }
  if (t) t.addEventListener('click', function(){ s.classList.toggle('max-lg:translate-x-0'); r.classList.toggle('hidden'); r.classList.toggle('block'); });
  if (r) r.addEventListener('click', tutup);
})();
</script>
@yield('skrip')
</body>
</html>
