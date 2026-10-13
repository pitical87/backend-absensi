<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Kesalahan') | RSUD Merauke — Rumah Sakit Rujukan Terdepan di Papua Selatan</title>

    <meta name="description" content="Sistem Presensi & Kepegawaian RSUD Merauke. Halaman kesalahan sistem.">
    <meta name="robots" content="noindex, nofollow">

    <meta name="theme-color" content="#007afc">
    <meta name="msapplication-TileColor" content="#007afc">

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/logo.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(1rem);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse-slow {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.85;
                transform: scale(0.995);
            }
        }

        .animate-fade-in-up {
            animation: fade-in-up 0.6s ease-out both;
        }

        .animate-pulse-slow {
            animation: pulse-slow 2.4s ease-in-out infinite;
        }
    </style>
</head>
<body class="antialiased bg-white text-slate-800 selection:bg-[#007afc] selection:text-white min-h-screen flex items-center justify-center p-6">
    <main class="w-full max-w-lg text-center animate-fade-in-up">
        <div class="flex flex-col items-center gap-4">
            <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo RSUD Merauke" class="h-16 w-auto animate-pulse-slow">
            <div class="flex items-center gap-4 divide-x divide-slate-300">
                <h1 class="text-3xl font-semibold text-slate-900">@yield('code')</h1>
                <p class="pl-4 text-base text-slate-600">@yield('message')</p>
            </div>
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('login') }}" class="mt-2 inline-flex items-center gap-2 rounded-xl bg-[#007afc] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#006ae6] focus:outline-none focus:ring-2 focus:ring-[#007afc]/40" style="color: white !important;">
                Kembali ke Halaman Sebelumnya
            </a>
        </div>
    </main>
</body>
</html>
