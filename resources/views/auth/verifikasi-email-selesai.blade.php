@extends('layouts.auth')

@section('title', $hasil === 'sukses' ? 'Email Terverifikasi' : 'Verifikasi Gagal')

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

        <div class="flex flex-col items-center text-center mb-6">
          @if ($hasil === 'sukses')
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-50 border border-emerald-200 mb-4">
              <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </span>
          @else
            <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-50 border border-red-200 mb-4">
              <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
          @endif

          <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight mb-1">
            {{ $hasil === 'sukses' ? 'Email Berhasil Diverifikasi' : 'Verifikasi Gagal' }}
          </h2>
          <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
            @if ($hasil === 'sukses')
              Status email akun Anda kini sudah terverifikasi.
            @else
              {{ $alasan }}
            @endif
          </p>
        </div>

        @if ($hasil === 'sukses')
          <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-xs sm:text-sm text-slate-700 space-y-2">
            <div class="flex justify-between gap-4">
              <span class="text-slate-500 shrink-0">Nama</span>
              <span class="font-semibold text-slate-800 text-right truncate">{{ $nama }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-500 shrink-0">Email</span>
              <span class="font-semibold text-slate-800 text-right break-all">{{ $email }}</span>
            </div>
            <div class="flex justify-between gap-4">
              <span class="text-slate-500 shrink-0">Waktu</span>
              <span class="font-semibold text-slate-800 text-right">
                {{ $waktu ? tgl_id(\Illuminate\Support\Carbon::parse($waktu)) : '—' }}
              </span>
            </div>
          </div>
        @endif

        <div class="pt-5 mt-5 border-t border-slate-100 space-y-3">
          <a
            href="{{ $tombolMasuk }}" style="color: white !important; text-decoration: none !important;"
            class="block w-full py-3.5 px-6 rounded-xl bg-[#007afc] hover:bg-[#006ee6] active:scale-[0.99] text-white font-medium text-sm sm:text-base shadow-lg shadow-blue-500/25 transition-all text-center no-underline"
          >
            {{ $sudahMasuk ? 'Kembali ke Dashboard' : 'Masuk ke Aplikasi' }}
          </a>

          @if (! $sudahMasuk)
            <p class="text-center text-xs text-slate-400">
              {{ $hasil === 'sukses'
                  ? 'Anda dapat menutup halaman ini.'
                  : 'Masuk lalu buka menu Verifikasi Email untuk meminta tautan baru.' }}
            </p>
          @endif
        </div>

      </div>
    </div>

    <div class="w-full pt-8 text-center text-xs text-slate-400">
      &copy; {{ date('Y') }} PIT RSUD Merauke. All rights reserved.
    </div>

  </div>

</div>
@endsection
