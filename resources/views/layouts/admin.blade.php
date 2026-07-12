@php
$menuAktif = $menuAktif ?? '';
$badgeIzin = $badgeIzin ?? 0;
$menus = [
    'dashboard'  => ['admin',            'beranda',  'Dashboard'],
    'pegawai'    => ['admin/pegawai',    'pegawai',  'Data Pegawai'],
    'unit'       => ['admin/unit',       'gedung',   'Data Unit Kerja'],
    'struktur'   => ['admin/struktur',   'struktur', 'Struktur Organisasi'],
    'shift'      => ['admin/shift',      'jam',      'Pengaturan Shift'],
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
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body>
<div class="admin-kerangka">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-merek">
      <img class="logo" src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
      <div><strong>RSUD Merauke</strong><span>Sistem Absensi — Admin</span></div>
    </div>
    <nav>
      @foreach($menus as $kunci => [$jalur, $namaIkon, $label])
        <a class="nav-item {{ $menuAktif === $kunci ? 'aktif' : '' }}" href="{{ url($jalur) }}">
          {!! ikon($namaIkon, 16) !!}<span>{{ $label }}</span>
          @if($kunci === 'izin' && $badgeIzin > 0)
            <span class="badge badge-amber" style="margin-left:auto">{{ $badgeIzin }}</span>
          @endif
        </a>
      @endforeach
    </nav>
    <div class="sidebar-kaki">
      <a class="nav-item" href="{{ route('dashboard') }}">{!! ikon('pegawai', 18) !!}<span>Tampilan Pegawai</span></a>
      <a class="nav-item" href="{{ route('logout') }}">{!! ikon('keluar', 18) !!}<span>Keluar</span></a>
    </div>
  </aside>

  <div class="tirai" id="tirai"></div>

  <div class="admin-utama">
    <header class="admin-atas">
      <button type="button" class="tombol-menu" id="tombol-menu" aria-label="Buka menu">{!! ikon('menu', 20) !!}</button>
      <h1>{{ $judulHalaman ?? '' }}</h1>
      <div class="pengguna">
        <span class="teks-kecil teks-redup">{{ tgl_id(date('Y-m-d')) }}</span>
        <strong>{{ session('nama') ?? 'Admin' }}</strong>
      </div>
    </header>
    <main class="admin-isi">
      @if(session('flash_sukses'))
        <div class="flash flash-sukses">{{ session('flash_sukses') }}</div>
      @endif
      @if(session('flash_gagal'))
        <div class="flash flash-gagal">{{ session('flash_gagal') }}</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
<script>
(function () {
  var t = document.getElementById('tombol-menu'), s = document.getElementById('sidebar'),
      r = document.getElementById('tirai');
  function tutup(){ s.classList.remove('terbuka'); r.classList.remove('terbuka'); }
  if (t) t.addEventListener('click', function(){ s.classList.toggle('terbuka'); r.classList.toggle('terbuka'); });
  if (r) r.addEventListener('click', tutup);
})();
</script>
@yield('skrip')
</body>
</html>
