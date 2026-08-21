<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PRIMARY TITLE --}}
    <title>@yield('title', 'Masuk') | RSUD Merauke — Rumah Sakit Rujukan Terdepan di Papua Selatan</title>

    {{-- PRIMARY SEO META TAGS --}}
    <meta name="title" content="@yield('title', 'Masuk') | RSUD Merauke — Rumah Sakit Rujukan Terdepan di Papua Selatan">
    <meta name="description" content="Portal Presensi & Kepegawaian RSUD Merauke. Rumah Sakit Umum Daerah rujukan utama di Provinsi Papua Selatan, berkomitmen menghadirkan layanan kesehatan paripurna, profesional, dan berintegritas.">
    <meta name="keywords" content="RSUD Merauke, Rumah Sakit Rujukan Papua Selatan, Rumah Sakit Merauke, Absensi RSUD Merauke, Layanan Kesehatan Papua Selatan, Presensi Pegawai RSUD, Pelayanan Medis Merauke">
    <meta name="author" content="Tim IT RSUD Merauke — Pemerintah Provinsi Papua Selatan">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- THEME & BRANDING COLOR --}}
    <meta name="theme-color" content="#007afc">
    <meta name="msapplication-TileColor" content="#007afc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RSUD Merauke">

    {{-- OPEN GRAPH / FACEBOOK / WHATSAPP META --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="RSUD Merauke — Rumah Sakit Rujukan Terdepan Papua Selatan">
    <meta property="og:description" content="Pusat layanan kesehatan unggulan dan rumah sakit rujukan utama di Provinsi Papua Selatan. Melayani dengan hati, profesional, dan berintegritas.">
    <meta property="og:image" content="{{ asset('assets/img/logo.svg') }}">
    <meta property="og:site_name" content="Sistem Informasi RSUD Merauke">
    <meta property="og:locale" content="id_ID">

    {{-- TWITTER CARD META --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="RSUD Merauke — Rumah Sakit Rujukan Papua Selatan">
    <meta name="twitter:description" content="Portal resmi RSUD Merauke, Rumah Sakit Rujukan Terdepan di Papua Selatan.">
    <meta name="twitter:image" content="{{ asset('assets/img/logo.svg') }}">

    {{-- FAVICON --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/logo.svg') }}">

    {{-- STRUCTURED DATA (Schema.org JSON-LD for Hospital / Healthcare Facility) --}}
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "Hospital",
      "name": "RSUD Merauke",
      "alternateName": "Rumah Sakit Umum Daerah Merauke",
      "description": "Rumah Sakit Umum Daerah rujukan utama di Provinsi Papua Selatan, menyediakan layanan kesehatan medis komprehensif, unggul, dan terpercaya.",
      "url": "{{ url('/') }}",
      "logo": "{{ asset('assets/img/logo.svg') }}",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Merauke",
        "addressRegion": "Papua Selatan",
        "addressCountry": "ID"
      },
      "areaServed": "Provinsi Papua Selatan",
      "slogan": "Rumah Sakit Rujukan Terdepan Papua Selatan — Melayani dengan Hati, Profesional, dan Berintegritas"
    }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-white text-slate-800 selection:bg-[#007afc] selection:text-white">

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>