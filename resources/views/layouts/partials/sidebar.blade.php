@php
$menuAktif = $menuAktif ?? '';
$badgeIzin = $badgeIzin ?? 0;

// Grup menu dengan accordion dropdown
$grupMenu = [
  [
    'id'    => 'grp-kepegawaian',
    'label' => 'Kepegawaian',
    'ikon'  => 'pegawai',
    'items' => [
      'pegawai'  => ['admin/pegawai',  'pegawai',  'Data Pegawai'],
      'unit'     => ['admin/unit',     'gedung',   'Data Unit Kerja'],
      'struktur' => ['admin/struktur', 'struktur', 'Struktur Organisasi'],
    ],
  ],
  [
    'id'    => 'grp-shift',
    'label' => 'Shift & Jadwal',
    'ikon'  => 'jam',
    'items' => [
      'shift'  => ['admin/shift',  'jam',      'Pengaturan Shift'],
      'jadwal' => ['admin/jadwal', 'kalender', 'Jadwal Shift'],
    ],
  ],
  [
    'id'    => 'grp-kehadiran',
    'label' => 'Kehadiran & Izin',
    'ikon'  => 'peta',
    'items' => [
      'kehadiran' => ['admin/kehadiran', 'peta',     'Data Kehadiran'],
      'izin'      => ['admin/izin',      'surat',    'Persetujuan Izin'],
      'libur'     => ['admin/libur',     'kalender', 'Hari Libur'],
      'rekap'     => ['admin/rekap',     'grafik',   'Rekap Bulanan'],
    ],
  ],
  [
    'id'    => 'grp-simrs',
    'label' => 'Integrasi SIMRS',
    'ikon'  => 'integrasi',
    'items' => [
      'cek_simrs'      => ['admin/simrs',          'centang', 'Cek Koneksi'],
      'mapping_simrs'  => ['admin/mapping_simrs',  'log',     'Mapping Akun'],
    ],
  ],
  [
    'id'    => 'grp-sistem',
    'label' => 'Sistem',
    'ikon'  => 'atur',
    'items' => [
      'aktivitas'  => ['admin/aktivitas',  'log',  'Log Aktivitas'],
      'pengaturan' => ['admin/pengaturan', 'atur', 'Pengaturan'],
    ],
  ],
];
@endphp

{{-- SIDEBAR ADMIN DENGAN TEMA ROYAL NAVY / BLUE --}}
<aside class="fixed inset-y-0 left-0 z-50 w-[245px] shrink-0 bg-gradient-to-b from-[#091E3A] via-[#0D2A52] to-[#0A203F] text-white flex flex-col h-screen -translate-x-full lg:translate-x-0 lg:static lg:sticky lg:top-0 transition-transform duration-300 ease-in-out shadow-2xl lg:shadow-xl lg:shadow-slate-900/10" id="sidebar">
  
  {{-- Header Sidebar --}}
  <div class="flex items-center gap-3 py-4 px-4 pb-3 border-b border-white/10">
    <div class="w-10 h-10 rounded-xl backdrop-blur-md p-1 flex items-center justify-center shrink-0 shadow-sm">
      <img class="w-8 h-8 object-contain" src="{{ asset('assets/img/logo.svg') }}" alt="Logo">
    </div>
    <div>
      <strong class="block text-sm font-bold text-white leading-tight tracking-tight">RSUD Merauke</strong>
      <span class="text-[0.62rem] font-medium tracking-[0.1em] uppercase text-blue-200/80 block mt-0.5">Administrator</span>
    </div>
  </div>

  {{-- Search Menu --}}
  <div class="px-3 pt-3 pb-1">
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/40 pointer-events-none">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
        </svg>
      </span>
      <input
        type="text"
        id="sidebar-search"
        placeholder="Cari menu…"
        autocomplete="off"
        class="w-full bg-white/8 border border-white/12 text-white text-xs placeholder-white/35
               rounded-xl py-2 pl-8 pr-7 focus:outline-none focus:border-[#007afc] focus:bg-white/12
               transition-all duration-150 shadow-none ring-0"
      >
      <button type="button" id="sidebar-search-clear"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 transition-colors hidden bg-transparent border-0 cursor-pointer p-0 leading-none text-base">
        &times;
      </button>
    </div>
  </div>

  {{-- Nav Menu Items --}}
  <nav class="flex-1 py-2 px-3 overflow-y-auto" id="sidebar-nav">

    {{-- Dashboard (standalone) --}}
    <a class="nav-item {{ $menuAktif === 'dashboard' ? 'aktif' : '' }} mb-1"
       href="{{ url('admin') }}"
       data-label="dashboard">
      {!! ikon('beranda', 16) !!}
      <span class="flex-1 truncate">Dashboard</span>
    </a>

    {{-- Accordion Groups --}}
    @foreach($grupMenu as $grup)
      @php
        // Cek apakah salah satu item dalam grup ini sedang aktif
        $grupAktif = array_key_exists($menuAktif, $grup['items']);
        $grupId    = $grup['id'];

        // Kumpulkan semua label item untuk atribut data-label di tiap grup
        $allLabels = array_merge(
          [strtolower($grup['label'])],
          array_map(fn($i) => strtolower($i[2]), array_values($grup['items']))
        );
      @endphp

      <div class="mb-0.5 group-accordion" data-labels="{{ implode('|', $allLabels) }}">
        {{-- Tombol trigger grup --}}
        <button type="button"
                class="accordion-trigger w-full flex items-center gap-2.5 py-2 px-3 rounded-xl text-sm font-medium transition-all duration-150 text-left cursor-pointer border-0
                       {{ $grupAktif
                          ? 'bg-white/12 text-white'
                          : 'text-slate-300 hover:bg-white/8 hover:text-white' }}
                       bg-transparent"
                data-target="{{ $grupId }}">
          <span class="w-4 h-4 shrink-0 opacity-80 text-current">{!! ikon($grup['ikon'], 16) !!}</span>
          <span class="flex-1 truncate text-[0.84rem]">{{ $grup['label'] }}</span>
          {{-- Chevron icon --}}
          <svg class="accordion-chevron w-3.5 h-3.5 text-white/40 shrink-0 transition-transform duration-200 {{ $grupAktif ? 'rotate-180' : '' }}"
               fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        {{-- Item-item dalam grup (bisa collapse/expand) --}}
        <div id="{{ $grupId }}"
             class="accordion-panel overflow-hidden transition-all duration-200 ease-in-out {{ $grupAktif ? '' : 'max-h-0' }}"
             style="{{ $grupAktif ? '' : 'max-height:0' }}">
          <div class="mt-0.5 ml-3 pl-3 border-l border-white/10 space-y-0.5 pb-1">
            @foreach($grup['items'] as $kunci => [$jalur, $namaIkon, $label])
              <a class="nav-item text-[0.82rem] py-1.5 {{ $menuAktif === $kunci ? 'aktif' : '' }}"
                 href="{{ url($jalur) }}"
                 data-label="{{ strtolower($label) }}">
                {!! ikon($namaIkon, 14) !!}
                <span class="flex-1 truncate">{{ $label }}</span>
                @if($kunci === 'izin' && $badgeIzin > 0)
                  <span class="ml-auto px-2 py-0.5 text-[0.65rem] font-bold rounded-full bg-amber-400 text-slate-900 shadow-sm">{{ $badgeIzin }}</span>
                @endif
              </a>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach

    {{-- Empty state saat search tidak ada hasil --}}
    <div id="sidebar-empty" class="hidden px-3 py-6 text-center">
      <svg class="w-8 h-8 mx-auto text-white/20 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="text-[0.72rem] text-white/35 font-medium">Menu tidak ditemukan</p>
    </div>
  </nav>

  {{-- Footer Sidebar Actions --}}
  <div class="p-3 border-t border-white/10 space-y-1">
    <a class="nav-item text-xs hover:bg-white/10" href="{{ route('dashboard') }}">
      {!! ikon('pegawai', 17) !!}<span>Tampilan Pegawai</span>
    </a>
  </div>
</aside>

{{-- Mobile Backdrop --}}
<div class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300" id="tirai"></div>

<script>
(function () {
  // ── Accordion ─────────────────────────────────────
  document.querySelectorAll('.accordion-trigger').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var targetId = btn.dataset.target;
      var panel    = document.getElementById(targetId);
      var chevron  = btn.querySelector('.accordion-chevron');
      var isOpen   = panel.style.maxHeight && panel.style.maxHeight !== '0px';

      if (isOpen) {
        panel.style.maxHeight = '0';
        btn.classList.remove('bg-white/12', 'text-white');
        btn.classList.add('text-slate-300');
        chevron.classList.remove('rotate-180');
      } else {
        panel.style.maxHeight = panel.scrollHeight + 'px';
        btn.classList.add('bg-white/12', 'text-white');
        btn.classList.remove('text-slate-300');
        chevron.classList.add('rotate-180');
      }
    });
  });

  // Inisialisasi tinggi panel yang aktif
  document.querySelectorAll('.accordion-panel').forEach(function(panel) {
    if (!panel.style.maxHeight || panel.style.maxHeight === '0px') {
      return; // sudah di-collapse
    }
    // Jika aktif (tidak punya max-h-0), set tinggi konten sesungguhnya
    panel.style.maxHeight = panel.scrollHeight + 'px';
  });

  // ── Search ────────────────────────────────────────
  var input  = document.getElementById('sidebar-search');
  var clear  = document.getElementById('sidebar-search-clear');
  var nav    = document.getElementById('sidebar-nav');
  var empty  = document.getElementById('sidebar-empty');

  if (!input || !nav) return;

  function filterMenu() {
    var q = input.value.trim().toLowerCase();
    var items   = nav.querySelectorAll('a[data-label]');
    var grps    = nav.querySelectorAll('.group-accordion');
    var visible = 0;

    if (!q) {
      // Reset: kembalikan ke kondisi semula (hanya grup aktif yg terbuka)
      items.forEach(function(a) { a.style.display = ''; });
      grps.forEach(function(grp) {
        var panel   = grp.querySelector('.accordion-panel');
        var trigger = grp.querySelector('.accordion-trigger');
        grp.style.display = '';
        // Jika bukan grup yang aktif, collapse kembali
        if (panel && !panel.contains(document.querySelector('.nav-item.aktif'))) {
          panel.style.maxHeight = '0';
        }
      });
      empty.classList.add('hidden');
      clear.classList.add('hidden');
      return;
    }

    clear.classList.remove('hidden');

    // Saat ada query: tampilkan item yang cocok + buka semua grup yang memiliki match
    grps.forEach(function(grp) {
      var labels  = (grp.dataset.labels || '').split('|');
      var grupCocok = labels.some(function(l) { return l.includes(q); });
      var panel   = grp.querySelector('.accordion-panel');
      var anak    = grp.querySelectorAll('a[data-label]');
      var adaAnak = false;

      anak.forEach(function(a) {
        var match = a.dataset.label.includes(q);
        a.style.display = match ? '' : 'none';
        if (match) { adaAnak = true; visible++; }
      });

      // Tampilkan/sembunyikan grup
      var tampil = adaAnak || gruCocok;
      grp.style.display = adaAnak ? '' : 'none';

      // Buka panel jika ada anak yang cocok
      if (panel && adaAnak) {
        panel.style.maxHeight = panel.scrollHeight + 500 + 'px';
      }
    });

    // Dashboard standalone
    var dashboard = nav.querySelector('a[data-label="dashboard"]');
    if (dashboard) {
      var match = 'dashboard'.includes(q);
      dashboard.style.display = match ? '' : 'none';
      if (match) visible++;
    }

    empty.classList.toggle('hidden', visible > 0);
  }

  input.addEventListener('input', filterMenu);

  clear.addEventListener('click', function() {
    input.value = '';
    filterMenu();
    input.focus();
  });
})();
</script>
