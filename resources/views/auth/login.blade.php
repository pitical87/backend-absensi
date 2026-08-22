@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<div class="min-h-screen bg-white relative overflow-hidden flex flex-col justify-between font-sans selection:bg-blue-500 selection:text-white">

  {{-- TOP BLUE BACKGROUND SPLIT (Full Width Top Half) --}}
  <div class="absolute top-0 left-0 right-0 w-full h-[52vh] min-h-[460px] bg-[#007afc] z-0 transition-all"></div>

  {{-- MAIN CONTENT WRAPPER --}}
  <div class="relative z-10 w-full max-w-[1280px] mx-auto px-6 sm:px-10 lg:px-12 pt-8 pb-12 min-h-screen flex flex-col justify-between">

    {{-- TOP ROW / HEADER --}}
    <div class="w-full flex items-center justify-between mb-4 lg:mb-6">
      <a href="{{ url('/') }}" class="flex items-center gap-3 no-underline hover:opacity-90 transition">
        <img src="{{ asset('assets/img/logo.svg') }}" alt="{{ config('app.name') }}" class="w-10 h-10 sm:w-12 sm:h-12 object-contain drop-shadow-sm">
        <span class="text-white font-bold text-lg sm:text-xl tracking-tight">
          {{ config('app.name') }}
        </span>
      </a>
    </div>

    {{-- MAIN GRID: LEFT (Hero & Illustration + Login As) & RIGHT (Floating Card) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center flex-1 my-auto">

      {{-- LEFT COLUMN --}}
      <div class="lg:col-span-7 flex flex-col justify-between h-full py-2">

        {{-- HERO TEXT & ROCKET ILLUSTRATION (Inside Blue Area) --}}
        <div class="relative min-h-[280px] sm:min-h-[320px] lg:min-h-[350px] flex items-center">
          
          {{-- Hero Heading & Subtitle --}}
          <div class="relative z-10 max-w-md sm:max-w-lg pr-4">
            <h1 class="text-3xl sm:text-4xl lg:text-[46px] font-bold text-white leading-[1.18] tracking-tight mb-4">
              Sign in to<br>
              <span class="font-normal text-white/95">Sistem Absensi RSUD Merauke</span>
            </h1>
            <p class="text-blue-100 text-xs sm:text-sm leading-relaxed max-w-sm sm:max-w-md font-normal opacity-90">
              Sistem presensi dan absensi digital terintegrasi untuk seluruh pegawai dan tenaga medis RSUD Merauke — Kabupaten Merauke, Papua Selatan.
            </p>
          </div>

          {{-- 3D Rocket Character Illustration --}}
          <div class="hidden sm:block absolute right-[-10px] md:right-2 lg:right-0 top-1/2 -translate-y-1/2 w-56 sm:w-72 lg:w-[350px] pointer-events-none select-none z-0">
            <img 
              src="{{ asset('assets/img/rocket-character.svg') }}" 
              alt="Rocket Character" 
              class="w-full h-auto drop-shadow-[0_20px_35px_rgba(0,30,80,0.3)] transform hover:scale-105 transition-transform duration-500 ease-out"
            >
          </div>
        </div>

        {{-- BOTTOM LOGIN AS SECTION (Dynamically Loaded from Encrypted localStorage) --}}
        <div id="login-as-section" class="hidden pt-4 lg:pt-4 mt-4 transition-all duration-300">
          <div class="flex items-center justify-between mb-3 max-w-md">
            <h3 class="text-sm font-semibold text-slate-800 tracking-wide">Last login</h3>
            
          </div>
          
          <div id="login-as-list" class="flex flex-wrap gap-4 items-center">
            {{-- Injected dynamically by JavaScript --}}
          </div>
        </div>

      </div>

      {{-- RIGHT COLUMN: FLOATING SIGN IN CARD --}}
      <div class="lg:col-span-5 flex justify-center lg:justify-end relative z-20">
        
        <div class="w-full max-w-[460px] bg-white rounded-[32px] sm:rounded-[36px] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.14)] border border-slate-100 p-7 sm:p-9 lg:p-10 transition-all">

          {{-- CARD HEADER --}}
          <div class="flex items-start justify-between gap-4 mb-2">
            <div>
              <p class="text-xs sm:text-sm text-slate-700 font-medium tracking-tight">
                Welcome to <span class="font-bold text-[#007afc] tracking-wider">{{config('app.name')}}</span>
              </p>
            </div>
            
          </div>

          {{-- MAIN TITLE --}}
          <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mt-3 mb-6">
            Sign in
          </h2>

          {{-- SOCIAL LOGINS ROW --}}
          <div class="flex items-center gap-2.5 sm:gap-3 mb-7">
            
            {{-- Google Login Button --}}
            <button 
              type="button" 
              onclick="showSocialToast('Google')"
              class="flex-1 bg-[#f0f4f9] hover:bg-[#e2ebf6] active:scale-[0.98] border border-transparent rounded-xl py-2.5 px-3.5 flex items-center justify-center gap-2.5 transition-all cursor-pointer group"
            >
              {{-- Google G Logo SVG --}}
              <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
              </svg>
              <span class="text-xs sm:text-sm font-medium text-[#4a6b8c] group-hover:text-slate-800 transition">
                Sign in with Google
              </span>
            </button>
          </div>

          {{-- FLASH / ERROR MESSAGES --}}
          @if(! empty($galat))
            <div class="mb-5 rounded-xl bg-red-50 border border-red-200/80 text-red-600 text-xs sm:text-sm px-4 py-3 flex items-start gap-2.5">
              <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>{{ $galat }}</span>
            </div>
          @endif

          @if(session('success'))
            <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs sm:text-sm px-4 py-3 flex items-start gap-2.5">
              <svg class="w-4 h-4 shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span>{{ session('success') }}</span>
            </div>
          @endif

          {{-- LOGIN FORM --}}
          <form method="POST" action="{{ route('login') }}" id="login-form" class="space-y-4">
            @csrf

            {{-- Username / Email Field --}}
            <div>
              <label for="email" class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                Enter your username or email address
              </label>
              <input
                type="text"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                placeholder="Username or email address"
                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#007afc] focus:ring-4 focus:ring-blue-100 transition-all shadow-sm"
              >
            </div>

            {{-- Password Field --}}
            <div>
              <label for="password" class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                Enter your Password
              </label>
              <div class="relative">
                <input
                  type="password"
                  id="password"
                  name="password"
                  required
                  placeholder="Password"
                  class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 pr-11 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#007afc] focus:ring-4 focus:ring-blue-100 transition-all shadow-sm"
                >
                <button
                  type="button"
                  id="toggle-password"
                  class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none p-1 cursor-pointer"
                  title="Toggle Password Visibility"
                >
                  <svg id="eye-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg id="eye-off-icon" class="w-4 h-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                  </svg>
                </button>
              </div>

              {{-- Forgot Password Link --}}
              <div class="flex justify-end mt-1.5">
                <a href="#" class="text-xs text-blue-500 hover:text-blue-600 hover:underline transition font-normal">
                  Forgot Password
                </a>
              </div>
            </div>

            {{-- Submit Button --}}
            <div class="pt-2">
              <button
                type="submit"
                class="w-full py-3.5 px-6 rounded-xl bg-[#007afc] hover:bg-[#006ee6] active:scale-[0.99] text-white font-medium text-sm sm:text-base shadow-lg shadow-blue-500/25 transition-all cursor-pointer flex items-center justify-center gap-2"
              >
                Sign in
              </button>
            </div>

          </form>

        </div>

      </div>

    </div>

    {{-- FOOTER / BOTTOM NOTE --}}
    <div class="w-full pt-8 text-center text-xs text-slate-400">
      &copy; {{ date('Y') }} PIT RSUD Merauke. All rights reserved.
    </div>

  </div>

</div>

@push('scripts')
<script>
(function () {
  // --- ENCRYPTED LOCALSTORAGE ENGINE ---
  const CryptoStorage = {
    KEY: 'RSUD_MERAUKE_ABSENSI_SECURE_V1_2026',
    SALT: 0x7e,
    STORAGE_KEY: 'rsud_merauke_login_as_enc',
    LEGACY_KEY: 'absensi_login_as_list',
    MAX_USERS: 5,

    encrypt: function (plainText) {
      try {
        const utf8 = encodeURIComponent(plainText);
        let res = '';
        for (let i = 0; i < utf8.length; i++) {
          const c = utf8.charCodeAt(i);
          const k = this.KEY.charCodeAt(i % this.KEY.length);
          const e = (c ^ k ^ this.SALT) & 0xff;
          res += e.toString(16).padStart(2, '0');
        }
        return btoa(res);
      } catch (e) {
        return '';
      }
    },

    decrypt: function (cipherText) {
      if (!cipherText) return null;
      try {
        const hex = atob(cipherText);
        let utf8 = '';
        for (let i = 0; i < hex.length; i += 2) {
          const e = parseInt(hex.substr(i, 2), 16);
          const k = this.KEY.charCodeAt((i / 2) % this.KEY.length);
          const c = (e ^ k ^ this.SALT) & 0xff;
          utf8 += String.fromCharCode(c);
        }
        return decodeURIComponent(utf8);
      } catch (e) {
        return null;
      }
    },

    getUsers: function () {
      try {
        const encData = localStorage.getItem(this.STORAGE_KEY);
        if (encData) {
          const decrypted = this.decrypt(encData);
          if (decrypted) {
            const parsed = JSON.parse(decrypted);
            if (Array.isArray(parsed)) return parsed;
          }
        }
        // Migration from old unencrypted key if existing
        const oldData = localStorage.getItem(this.LEGACY_KEY);
        if (oldData) {
          try {
            const parsedOld = JSON.parse(oldData);
            if (Array.isArray(parsedOld) && parsedOld.length) {
              this.saveUsers(parsedOld);
              localStorage.removeItem(this.LEGACY_KEY);
              return parsedOld;
            }
          } catch (e) {}
        }
        return [];
      } catch (e) {
        return [];
      }
    },

    saveUsers: function (users) {
      try {
        const sliced = users.slice(0, this.MAX_USERS);
        const encrypted = this.encrypt(JSON.stringify(sliced));
        if (encrypted) {
          localStorage.setItem(this.STORAGE_KEY, encrypted);
        }
      } catch (e) {
        console.error('Error saving encrypted login users:', e);
      }
    },

    addUser: function (email, name, role) {
      if (!email || !email.trim()) return;
      email = email.trim();
      name = (name && name.trim()) ? name.trim() : email.split('@')[0];
      
      let users = this.getUsers().filter(u => u.email.toLowerCase() !== email.toLowerCase());
      users.unshift({
        email: email,
        name: name,
        initial: name.charAt(0).toUpperCase(),
        role: role || 'Pegawai',
        lastLogin: Date.now()
      });
      this.saveUsers(users);
    },

    removeUser: function (email) {
      let users = this.getUsers().filter(u => u.email.toLowerCase() !== email.toLowerCase());
      this.saveUsers(users);
    },

    clearAll: function () {
      localStorage.removeItem(this.STORAGE_KEY);
      localStorage.removeItem(this.LEGACY_KEY);
    }
  };

  // Expose globally for session/login tracking
  window.saveEncryptedLoginUser = function (email, name, role) {
    CryptoStorage.addUser(email, name, role);
  };

  // --- PASSWORD VISIBILITY TOGGLE ---
  const toggleBtn = document.getElementById('toggle-password');
  const passwordInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eye-icon');
  const eyeOffIcon = document.getElementById('eye-off-icon');

  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function () {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      eyeIcon.classList.toggle('hidden', isPassword);
      eyeOffIcon.classList.toggle('hidden', !isPassword);
    });
  }

  // --- RENDER LOGIN AS CARDS FROM ENCRYPTED STORAGE ---
  const avatarGradients = [
    'from-blue-600 to-indigo-600',
    'from-emerald-500 to-teal-700',
    'from-purple-600 to-pink-600',
    'from-amber-500 to-orange-600',
    'from-sky-500 to-blue-700'
  ];

  function renderLoginAs() {
    const users = CryptoStorage.getUsers();
    const section = document.getElementById('login-as-section');
    const list = document.getElementById('login-as-list');
    
    if (!section || !list) return;

    if (!users.length) {
      section.classList.add('hidden');
      list.innerHTML = '';
      return;
    }

    section.classList.remove('hidden');
    list.innerHTML = '';

    users.forEach(function (user, idx) {
      const card = document.createElement('div');
      card.className = 'group relative w-32 sm:w-36 bg-[#f0f4f9] hover:bg-[#e6edf7] border border-slate-200/70 hover:border-blue-300 rounded-2xl p-3 flex flex-col items-center text-center cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md';
      
      const grad = avatarGradients[idx % avatarGradients.length];

      card.innerHTML = `
        <button 
          type="button" 
          class="remove-user-btn absolute top-2 right-2.5 w-4 h-4 rounded-full bg-white border border-slate-300 hover:border-red-400 hover:bg-red-50 flex items-center justify-center text-slate-400 hover:text-red-500 transition z-10 p-0"
          title="Hapus akun dari riwayat"
          data-email="${user.email}"
        >
          <svg class="w-2.5 h-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div class="w-12 h-12 rounded-full bg-gradient-to-tr ${grad} text-white flex items-center justify-center text-base font-bold shadow-sm mt-1 mb-2 border-2 border-white">
          ${user.initial || 'U'}
        </div>

        <span class="font-semibold text-slate-800 text-xs truncate max-w-full block" title="${user.name}">
          ${user.name}
        </span>
        <span class="text-[11px] text-slate-400 mt-0.5 block">
          ${timeAgo(user.lastLogin)}
        </span>
      `;

      // Click card to autofill email & focus password
      card.addEventListener('click', function (e) {
        if (e.target.closest('.remove-user-btn')) return;
        const emailInput = document.getElementById('email');
        const passInput = document.getElementById('password');
        if (emailInput) {
          emailInput.value = user.email;
          emailInput.classList.add('ring-4', 'ring-blue-200');
          setTimeout(() => emailInput.classList.remove('ring-4', 'ring-blue-200'), 1200);
          if (passInput) passInput.focus();
        }
      });

      // Remove button listener
      const removeBtn = card.querySelector('.remove-user-btn');
      if (removeBtn) {
        removeBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          CryptoStorage.removeUser(user.email);
          renderLoginAs();
        });
      }

      list.appendChild(card);
    });
  }

  function timeAgo(timestamp) {
    if (!timestamp) return 'Aktif hari ini';
    const diffMs = Date.now() - timestamp;
    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    if (days <= 0) return 'Aktif hari ini';
    if (days === 1) return 'Aktif 1 hari lalu';
    return `Aktif ${days} hari lalu`;
  }

  // Clear all button listener
  const clearBtn = document.getElementById('clear-saved-users-btn');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (confirm('Hapus semua riwayat akun yang tersimpan di perangkat ini?')) {
        CryptoStorage.clearAll();
        renderLoginAs();
      }
    });
  }

  // On form submit, automatically save the entered email into encrypted storage
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', function () {
      const emailInput = document.getElementById('email');
      if (emailInput && emailInput.value.trim()) {
        CryptoStorage.addUser(emailInput.value.trim());
      }
    });
  }

  window.showSocialToast = function(provider) {
    const existing = document.getElementById('social-toast');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.id = 'social-toast';
    toast.className = 'fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xl text-xs flex items-center gap-2 animate-bounce';
    toast.innerHTML = `<span>Sign in with ${provider} is coming soon.</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  };

  // Initial render
  renderLoginAs();
})();
</script>
@endpush
@endsection