@extends('layouts.auth')

@section('title', 'Lupa Password')

@section('content')
<div class="min-h-screen bg-white relative overflow-hidden flex flex-col justify-between font-sans selection:bg-blue-500 selection:text-white">

  <div class="absolute top-0 left-0 right-0 w-full h-[52vh] min-h-[460px] bg-[#007afc] z-0 transition-all"></div>

  <div class="relative z-10 w-full max-w-[1280px] mx-auto px-6 sm:px-10 lg:px-12 pt-8 pb-12 min-h-screen flex flex-col justify-between">

    <div class="w-full flex items-center justify-between mb-4 lg:mb-6">
      <a href="{{ url('/') }}" class="flex items-center gap-3 no-underline hover:opacity-90 transition">
        <img src="{{ asset('assets/img/logo.svg') }}" alt="{{ config('app.name') }}" class="w-10 h-10 sm:w-12 sm:h-12 object-contain drop-shadow-sm">
        <span class="text-white font-bold text-lg sm:text-xl tracking-tight">
          {{ config('app.name') }}
        </span>
      </a>
    </div>

    <div class="flex items-center justify-center flex-1 my-auto">
      <div class="w-full max-w-[460px] bg-white rounded-[32px] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.14)] border border-slate-100 p-7 sm:p-9 lg:p-10">

        <div class="flex items-start justify-between gap-4 mb-2">
          <p class="text-xs sm:text-sm text-slate-700 font-medium tracking-tight">
            <span class="font-bold text-[#007afc] tracking-wider">{{ config('app.name') }}</span>
          </p>
        </div>

        <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mt-3 mb-1">
          Lupa Password
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed">
          Masukkan alamat email akun Anda. Tautan untuk membuat password baru akan dikirim ke email tersebut.
        </p>

        @if(session('galat'))
          <div class="mb-5 rounded-xl bg-red-50 border border-red-200/80 text-red-600 text-xs sm:text-sm px-4 py-3 flex items-start gap-2.5">
            <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ session('galat') }}</span>
          </div>
        @endif

        @if(session('sukses'))
          <div class="mb-5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs sm:text-sm px-4 py-3 flex items-start gap-2.5">
            <svg class="w-4 h-4 shrink-0 mt-0.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('sukses') }}</span>
          </div>
        @endif

        <form method="POST" action="{{ route('lupa-password.kirim') }}" class="space-y-4">
          @csrf

          <div>
            <label for="email" class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
              Alamat Email
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value="{{ old('email') }}"
              required
              autofocus
              autocomplete="email"
              placeholder="nama@rsudmerauke.id"
              class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#007afc] focus:ring-4 focus:ring-blue-100 transition-all shadow-sm"
            >
          </div>

          <div class="pt-2">
            <button
              type="submit"
              class="w-full py-3.5 px-6 rounded-xl bg-[#007afc] hover:bg-[#006ee6] active:scale-[0.99] text-white font-medium text-sm sm:text-base shadow-lg shadow-blue-500/25 transition-all cursor-pointer"
            >
              Kirim Tautan Reset
            </button>
          </div>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-100 text-center">
          <a href="{{ route('login') }}" class="text-xs sm:text-sm text-[#007afc] hover:underline font-medium">
            &larr; Kembali ke halaman masuk
          </a>
        </div>

      </div>
    </div>

    <div class="w-full pt-8 text-center text-xs text-slate-400">
      &copy; {{ date('Y') }} PIT RSUD Merauke. All rights reserved.
    </div>

  </div>

</div>
@endsection