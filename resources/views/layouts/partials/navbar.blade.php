{{-- TOP NAVBAR --}}
<header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 py-3 px-4 sm:px-6 flex items-center justify-between gap-3 sticky top-0 z-30 shadow-sm dark:bg-[#0D1830]/95 dark:border-slate-800 dark:shadow-none">
  <div class="flex items-center gap-3">
    <button type="button" class="lg:hidden inline-flex p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 cursor-pointer border-0 transition-colors" id="tombol-menu" aria-label="Buka menu">
      {!! ikon('menu', 20) !!}
    </button>
    <h1 class="m-0 text-base sm:text-lg font-bold text-navy flex-1 truncate">{{ $judulHalaman ?? 'Dashboard' }}</h1>
  </div>
  
  <div class="flex items-center gap-2.5 sm:gap-3.5">
    {{-- DATE BADGE --}}
    <span class="hidden sm:inline-flex items-center gap-1.5 py-1.5 px-3 bg-slate-100/90 text-slate-600 rounded-xl text-xs font-medium border border-slate-200/60 dark:bg-slate-800/70 dark:text-slate-300 dark:border-slate-700">
      <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      {{ tgl_id(date('Y-m-d')) }}
    </span>

    {{-- TOMBOL MODE TERANG / GELAP --}}
    <button type="button" id="tombol-mode" class="p-2 rounded-xl text-slate-600 hover:text-navy hover:bg-slate-100 transition-colors focus:outline-none cursor-pointer dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Ganti mode terang/gelap" title="Mode terang / gelap">
      <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
      </svg>
      <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
      </svg>
    </button>

    {{-- DROPDOWN NOTIFIKASI --}}
    <div class="relative" id="menu-notifikasi-grup">
      <button type="button" id="btn-notifikasi" class="relative p-2 rounded-xl text-slate-600 hover:text-navy hover:bg-slate-100 transition-colors focus:outline-none" aria-label="Notifikasi">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if(($badgeIzin ?? 0) > 0)
          <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
          </span>
        @endif
      </button>

      {{-- PANEL DROPDOWN NOTIFIKASI --}}
      <div id="panel-notifikasi" class="hidden absolute right-0 mt-2 w-80 sm:w-88 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150 dark:bg-[#121E33] dark:border-slate-800">
        <div class="p-3.5 px-4 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <strong class="text-xs font-bold text-navy uppercase tracking-wider">Notifikasi</strong>
            @if(($badgeIzin ?? 0) > 0)
              <span class="px-2 py-0.5 text-[0.65rem] font-bold rounded-full bg-amber-100 text-amber-800">{{ $badgeIzin }} baru</span>
            @endif
          </div>
          <span class="text-[0.7rem] text-slate-400">RSUD Merauke</span>
        </div>

        <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
          @if(($badgeIzin ?? 0) > 0)
            <a href="{{ url('admin/izin') }}" class="p-3 px-4 flex items-start gap-3 hover:bg-amber-50/50 transition-colors no-underline group">
              <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                {!! ikon('surat', 15) !!}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-800 group-hover:text-blue-600 mb-0.5">Pengajuan Izin / Cuti</p>
                <p class="text-[0.75rem] text-slate-500 leading-snug">Ada <strong>{{ $badgeIzin }}</strong> pengajuan izin pegawai yang menunggu verifikasi Anda.</p>
              </div>
            </a>
          @else
            <div class="p-3 px-4 flex items-start gap-3 hover:bg-slate-50 transition-colors">
              <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                {!! ikon('centang', 15) !!}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-800 mb-0.5">Semua Beres</p>
                <p class="text-[0.75rem] text-slate-500 leading-snug">Tidak ada pengajuan izin yang tertunda saat ini.</p>
              </div>
            </div>
          @endif

          <a href="{{ url('admin/kehadiran') }}" class="p-3 px-4 flex items-start gap-3 hover:bg-blue-50/50 transition-colors no-underline group">
            <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
              {!! ikon('peta', 15) !!}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-800 group-hover:text-blue-600 mb-0.5">Presensi Kehadiran</p>
              <p class="text-[0.75rem] text-slate-500 leading-snug">Pantau log absensi, keterlambatan, dan anomali GPS hari ini.</p>
            </div>
          </a>
        </div>

        <div class="p-2.5 bg-slate-50 text-center border-t border-slate-100">
          <a href="{{ url('admin/aktivitas') }}" class="text-[0.75rem] font-semibold text-biru hover:text-blue-700 no-underline">Lihat Log Aktivitas Sistem &rarr;</a>
        </div>
      </div>
    </div>

    {{-- DROPDOWN AKUN ADMIN --}}
    <div class="relative pl-1 sm:pl-2 sm:border-l border-slate-200" id="menu-akun-grup">
      <button type="button" id="btn-akun" class="flex items-center gap-2 p-1 sm:px-2 sm:py-1 rounded-xl hover:bg-slate-100 transition-colors focus:outline-none cursor-pointer">
        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-[#0062d1] to-[#007afc] text-white font-bold text-xs flex items-center justify-center shadow-sm border border-white shrink-0">
          {{ strtoupper(substr(session('nama') ?? 'A', 0, 1)) }}
        </div>
        <div class="text-left hidden md:block max-w-[130px]">
          <span class="text-xs font-semibold text-slate-800 block truncate leading-tight">
            {{ session('nama') ?? 'Administrator' }}
          </span>
          <span class="text-[0.65rem] text-slate-400 block leading-tight font-medium">Admin</span>
        </div>
        <svg class="w-3.5 h-3.5 text-slate-400 hidden sm:block ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {{-- PANEL DROPDOWN AKUN --}}
      <div id="panel-akun" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150 dark:bg-[#121E33] dark:border-slate-800">
        {{-- Header Info Akun --}}
        <div class="p-4 bg-gradient-to-br from-slate-50 to-blue-50/40 border-b border-slate-100 dark:from-slate-800/60 dark:to-[#14263E]/60 dark:border-slate-800">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-[#0062d1] to-[#007afc] text-white font-bold text-sm flex items-center justify-center shadow-md border-2 border-white shrink-0">
              {{ strtoupper(substr(session('nama') ?? 'A', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
              <strong class="block text-xs font-bold text-navy truncate">{{ session('nama') ?? 'Administrator' }}</strong>
              <span class="block text-[0.7rem] text-slate-500 truncate">{{ session('email') ?? 'admin@rsudmerauke.go.id' }}</span>
              <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[0.62rem] font-bold bg-blue-100 text-blue-800">Administrator</span>
            </div>
          </div>
        </div>

        {{-- Menu Links --}}
        <div class="p-1.5 space-y-0.5">
          <button type="button" id="btn-buka-profil" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:text-navy hover:bg-slate-100 rounded-xl transition-colors text-left cursor-pointer border-0 bg-transparent">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Profil Akun</span>
          </button>

          <button type="button" id="btn-buka-password" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:text-navy hover:bg-slate-100 rounded-xl transition-colors text-left cursor-pointer border-0 bg-transparent">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            <span>Ubah Password</span>
          </button>

          <a href="{{ url('admin/pengaturan') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:text-navy hover:bg-slate-100 rounded-xl transition-colors no-underline">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Pengaturan GPS & Sistem</span>
          </a>
        </div>

        {{-- Logout Section --}}
        <div class="p-1.5 border-t border-slate-100 bg-slate-50/50">
          <a href="{{ route('logout') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-xl transition-colors no-underline">
            <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>Keluar (Logout)</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

{{-- ================= MODAL PROFIL PENGGUNA ================= --}}
<div id="modal-profil" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center gap-2">
        {!! ikon('pegawai', 18) !!} Profil Pengguna
      </span>
      <button type="button" class="btn-tutup-modal text-white/80 hover:text-white bg-transparent border-0 cursor-pointer text-lg leading-none">&times;</button>
    </header>
    <div class="isi space-y-4">
      <div class="flex items-center gap-3.5 pb-3 border-b border-slate-100">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#0062d1] to-[#007afc] text-white font-bold text-xl flex items-center justify-center shadow-md">
          {{ strtoupper(substr(session('nama') ?? 'A', 0, 1)) }}
        </div>
        <div>
          <h3 class="text-base font-bold text-navy m-0">{{ session('nama') ?? 'Administrator' }}</h3>
          <p class="text-xs text-slate-500 m-0">{{ session('email') ?? 'admin@rsudmerauke.go.id' }}</p>
          <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[0.68rem] font-bold bg-blue-100 text-blue-800">Administrator Sistem</span>
        </div>
      </div>

      <div class="space-y-2 text-xs">
        <div class="flex justify-between py-1.5 border-b border-slate-100">
          <span class="text-slate-500">Nama Lengkap</span>
          <span class="font-semibold text-slate-800">{{ session('nama') ?? 'Administrator' }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-slate-100">
          <span class="text-slate-500">Email Login</span>
          <span class="font-semibold text-slate-800">{{ session('email') ?? 'admin@rsudmerauke.go.id' }}</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-slate-100">
          <span class="text-slate-500">Instansi</span>
          <span class="font-semibold text-slate-800">RSUD Merauke — Papua Selatan</span>
        </div>
        <div class="flex justify-between py-1.5 border-b border-slate-100">
          <span class="text-slate-500">Hak Akses</span>
          <span class="font-semibold text-emerald-700">Akses Penuh Administrator</span>
        </div>
      </div>

      <div class="pt-2 flex justify-end">
        <button type="button" class="btn btn-garis btn-kecil btn-tutup-modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- ================= MODAL UBAH PASSWORD ================= --}}
<div id="modal-ubah-password" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
        </svg> Ubah Password Akun
      </span>
      <button type="button" class="btn-tutup-modal text-white/80 hover:text-white bg-transparent border-0 cursor-pointer text-lg leading-none">&times;</button>
    </header>
    <div class="isi">
      <form action="{{ route('admin.ubah-password') }}" method="POST" class="space-y-3.5">
        @csrf
        <div class="form-grup mb-0">
          <label class="wajib">Password Lama</label>
          <input type="password" name="password_lama" required placeholder="Masukkan password saat ini">
        </div>

        <div class="form-grup mb-0">
          <label class="wajib">Password Baru</label>
          <input type="password" name="password_baru" required minlength="6" placeholder="Minimal 6 karakter">
        </div>

        <div class="form-grup mb-0">
          <label class="wajib">Konfirmasi Password Baru</label>
          <input type="password" name="password_konfirmasi" required minlength="6" placeholder="Ulangi password baru">
        </div>

        <p class="text-[0.75rem] text-slate-500 mb-0">Pastikan menggunakan kombinasi password yang kuat untuk menjaga keamanan akun admin Anda.</p>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" class="btn btn-garis btn-kecil btn-tutup-modal">Batal</button>
          <button type="submit" class="btn btn-primer btn-kecil">Simpan Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- SCRIPT INTERAKSI DROPDOWN & MODAL --}}
<script>
(function() {
  const btnNotif = document.getElementById('btn-notifikasi');
  const panelNotif = document.getElementById('panel-notifikasi');
  const btnAkun = document.getElementById('btn-akun');
  const panelAkun = document.getElementById('panel-akun');

  const modalProfil = document.getElementById('modal-profil');
  const modalPassword = document.getElementById('modal-ubah-password');
  const btnBukaProfil = document.getElementById('btn-buka-profil');
  const btnBukaPassword = document.getElementById('btn-buka-password');

  // Toggle Notification Panel
  if (btnNotif && panelNotif) {
    btnNotif.addEventListener('click', function(e) {
      e.stopPropagation();
      panelNotif.classList.toggle('hidden');
      if (panelAkun) panelAkun.classList.add('hidden');
    });
  }

  // Toggle Account Panel
  if (btnAkun && panelAkun) {
    btnAkun.addEventListener('click', function(e) {
      e.stopPropagation();
      panelAkun.classList.toggle('hidden');
      if (panelNotif) panelNotif.classList.add('hidden');
    });
  }

  // Toggle Mode Terang / Gelap (default: terang)
  var tombolMode = document.getElementById('tombol-mode');
  if (tombolMode) {
    tombolMode.addEventListener('click', function() {
      var gelap = document.documentElement.classList.toggle('dark');
      try {
        localStorage.setItem('tema-admin', gelap ? 'gelap' : 'terang');
      } catch (e) {}
    });
  }

  // Close Dropdowns on Click Outside
  document.addEventListener('click', function(e) {
    if (panelNotif && !panelNotif.contains(e.target) && !btnNotif.contains(e.target)) {
      panelNotif.classList.add('hidden');
    }
    if (panelAkun && !panelAkun.contains(e.target) && !btnAkun.contains(e.target)) {
      panelAkun.classList.add('hidden');
    }
  });

  // Modal handlers
  function bukaModal(modal) {
    if (panelAkun) panelAkun.classList.add('hidden');
    if (modal) modal.classList.add('terbuka');
  }

  function tutupSemuaModal() {
    if (modalProfil) modalProfil.classList.remove('terbuka');
    if (modalPassword) modalPassword.classList.remove('terbuka');
  }

  if (btnBukaProfil) {
    btnBukaProfil.addEventListener('click', function() {
      bukaModal(modalProfil);
    });
  }

  if (btnBukaPassword) {
    btnBukaPassword.addEventListener('click', function() {
      bukaModal(modalPassword);
    });
  }

  document.querySelectorAll('.btn-tutup-modal').forEach(function(btn) {
    btn.addEventListener('click', tutupSemuaModal);
  });

  [modalProfil, modalPassword].forEach(function(m) {
    if (m) {
      m.addEventListener('click', function(e) {
        if (e.target === m) tutupSemuaModal();
      });
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      tutupSemuaModal();
      if (panelNotif) panelNotif.classList.add('hidden');
      if (panelAkun) panelAkun.classList.add('hidden');
    }
  });
})();
</script>
