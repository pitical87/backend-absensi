@php
    $pengguna = \App\Models\User::find(session('uid'));
    $inisial  = strtoupper(mb_substr($pengguna->nama_lengkap ?? session('nama') ?? 'P', 0, 1));
    $namaDepan = isset($pengguna->nama_lengkap) && trim($pengguna->nama_lengkap) !== ''
        ? explode(' ', trim($pengguna->nama_lengkap))[0]
        : 'Akun';
@endphp

<nav class="flex items-center gap-2">
      @if(session('posisi') && session('posisi') !== 'Staf')
        <a class="inline-flex items-center gap-1.5 py-2 px-3 text-xs font-semibold text-white bg-white/15 hover:bg-white/25 border border-white/25 rounded-xl backdrop-blur-md shadow-sm no-underline transition-all active:scale-95" href="{{ route('persetujuan') }}">
          {!! ikon('centang', 14) !!} <span class="hidden sm:inline">Persetujuan</span>
        </a>
      @endif
      
      <a class="inline-flex items-center gap-1.5 py-2 px-3 text-xs font-semibold text-white bg-white/15 hover:bg-white/25 border border-white/25 rounded-xl backdrop-blur-md shadow-sm no-underline transition-all active:scale-95" href="{{ route('izin') }}">
        {!! ikon('surat', 14) !!} <span class="hidden sm:inline">Izin / Cuti</span>
      </a>

      <a class="inline-flex items-center gap-1.5 py-2 px-3 text-xs font-semibold text-white bg-white/15 hover:bg-white/25 border border-white/25 rounded-xl backdrop-blur-md shadow-sm no-underline transition-all active:scale-95" href="{{ route('logbook') }}">
        {!! ikon('log', 14) !!} <span class="hidden sm:inline">Logbook</span>
      </a>
      
      {{-- DROPDOWN AKUN --}}
      <div class="relative" id="menu-akun-grup">
        <button type="button" id="btn-akun" title="Menu akun"
                class="inline-flex items-center gap-1.5 py-2 px-3 text-xs font-semibold text-white bg-white/15 hover:bg-white/25 border border-white/25 rounded-xl backdrop-blur-md shadow-sm no-underline transition-all active:scale-95 cursor-pointer">
          <span class="w-5 h-5 rounded-full bg-gradient-to-tr from-[#0062d1] to-[#007afc] text-white font-bold text-[0.6rem] flex items-center justify-center shrink-0 border border-white/60">{{ $inisial }}</span>
          <span class="hidden sm:inline max-w-[90px] truncate text-blue-500">{{ $namaDepan }}</span>
          <svg class="w-3 h-3 opacity-70 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        {{-- PANEL DROPDOWN AKUN --}}
        <div id="panel-akun" class="hidden absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
          {{-- Header Info Akun --}}
          <div class="p-4 bg-gradient-to-br from-slate-50 to-blue-50/40 border-b border-slate-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-[#0062d1] to-[#007afc] text-white font-bold text-sm flex items-center justify-center shadow-md border-2 border-white shrink-0">
                {{ $inisial }}
              </div>
              <div class="min-w-0 flex-1">
                <strong class="block text-xs font-bold text-navy truncate">{{ $pengguna->nama_lengkap ?? session('nama') ?? 'Pegawai' }}</strong>
                <span class="block text-[0.7rem] text-slate-500 truncate">{{ $pengguna->email ?? session('email') }}</span>
                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[0.62rem] font-bold bg-blue-100 text-blue-800">{{ session('posisi') ?? 'Staf' }}</span>
              </div>
            </div>
          </div>

          {{-- Menu Links --}}
          <div class="p-1.5 space-y-0.5">
            <button type="button" id="btn-buka-password" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:text-navy hover:bg-slate-100 rounded-xl transition-colors text-left cursor-pointer border-0 bg-transparent">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
              </svg>
              <span>Ubah Password</span>
            </button>

            <a href="{{ route('pegawai.update-data') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-700 hover:text-navy hover:bg-slate-100 rounded-xl transition-colors no-underline">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              <span>Update Data</span>
            </a>
          </div>

          {{-- Logout Section --}}
          <div class="p-1.5 border-t border-slate-100 bg-slate-50/50">
            <a href="{{ route('logout') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-xl transition-colors no-underline">
              <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>Keluar (Logout)</span>
            </a>
          </div>
        </div>
      </div>
    </nav>

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
      <form action="{{ route('pegawai.ubah-password') }}" method="POST" class="space-y-3.5">
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

        <p class="text-[0.75rem] text-slate-500 mb-0">Pastikan menggunakan kombinasi password yang kuat untuk menjaga keamanan akun Anda.</p>

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
  const btnAkun = document.getElementById('btn-akun');
  const panelAkun = document.getElementById('panel-akun');

  const modalPassword = document.getElementById('modal-ubah-password');
  const btnBukaPassword = document.getElementById('btn-buka-password');

  // Toggle Account Panel
  if (btnAkun && panelAkun) {
    btnAkun.addEventListener('click', function(e) {
      e.stopPropagation();
      panelAkun.classList.toggle('hidden');
    });
  }

  // Close Dropdown on Click Outside
  document.addEventListener('click', function(e) {
    if (panelAkun && !panelAkun.contains(e.target) && !btnAkun.contains(e.target)) {
      panelAkun.classList.add('hidden');
    }
  });

  // Modal handlers
  function bukaModal(modal) {
    if (panelAkun) panelAkun.classList.add('hidden');
    if (modal) modal.classList.add('terbuka');
  }

  function tutupModal() {
    if (modalPassword) modalPassword.classList.remove('terbuka');
  }

  if (btnBukaPassword) {
    btnBukaPassword.addEventListener('click', function() {
      bukaModal(modalPassword);
    });
  }

  document.querySelectorAll('.btn-tutup-modal').forEach(function(btn) {
    btn.addEventListener('click', tutupModal);
  });

  if (modalPassword) {
    modalPassword.addEventListener('click', function(e) {
      if (e.target === modalPassword) tutupModal();
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      tutupModal();
      if (panelAkun) panelAkun.classList.add('hidden');
    }
  });
})();
</script>
