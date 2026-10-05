@php
$menuAktif = $menuAktif ?? '';
$badgeIzin = $badgeIzin ?? 0;
$jumlahAncaman = $jumlahAncaman ?? 0;

// Grup menu dengan accordion dropdown
$grupMenu = [
  [
    'id'    => 'grp-kepegawaian',
    'label' => 'Kepegawaian',
    'ikon'  => 'pegawai',
    'items' => [
      'pegawai'  => ['admin/pegawai',  'pegawai',  'Data Pegawai'],
      'unit'     => ['admin/unit',     'gedung',   'Data Unit Kerja'],
      'atasan_langsung' => ['admin/atasan_langsung', 'struktur', 'Atasan Langsung'],
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
      'jadwal_pengajuan' => ['admin/jadwal_pengajuan', 'kalender', 'Pengajuan Jadwal'],
      'lembur'    => ['admin/lembur', 'jam', 'Pengajuan Lembur'],
      'libur'     => ['admin/libur',     'kalender', 'Hari Libur'],
      
    ],
  ],
  [
    'id'    => 'grp-kinerja',
    'label' => 'Kinerja',
    'ikon'  => 'log',
    'items' => [
      'logbook'      => ['admin/logbook', 'surat', 'Buat Logbook'],
      'logbook_data' => ['admin/logbook-data', 'log', 'Data Logbook'],
    ],
  ],
  [
    'id'    => 'grp-integrasi',
    'label' => 'Integrasi',
    'ikon'  => 'koneksi',
    'items' => [
      'simrs'  => ['admin/simrs',  'koneksi', 'Modul SIMRS'],
      'finger' => ['admin/finger', 'jari',    'Modul FingerSpot'],
    ],
  ],
  [
    'id'    => 'grp-rekap',
    'label' => 'Laporan',
    'ikon'  => 'grafik',
    'items' => [
      'rekap'         => ['admin/rekap',         'grafik',   'Rekap Absen'],
      'rekap_keterlambatan' => ['admin/rekap_keterlambatan', 'jam', 'Rekap Keterlambatan'],
      'pegawai_teladan'     => ['admin/pegawai_teladan', 'bintang', 'Pegawai Teladan'],
      'rekap_logbook' => ['admin/rekap_logbook', 'log',      'Rekap Logbook'],
      'rekap_lembur'  => ['admin/rekap_lembur',  'jam',      'Rekap Lembur'],
      'eksekutif'     => ['admin/eksekutif',     'grafik',   'Dashboard Eksekutif'],
    ],
  ],
  [
    'id'    => 'grp-sistem',
    'label' => 'Sistem',
    'ikon'  => 'atur',
    'items' => [
      'aktivitas'  => ['admin/aktivitas',  'log',  'Log Aktivitas'],
      'user_login' => ['admin/user-login', 'kunci', 'User Login'],
      'login_gagal' => ['admin/login-gagal', 'peringatan', 'Login Gagal'],
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
      <strong class="block text-sm font-bold text-white leading-tight tracking-tight">{{ App\Models\Pengaturan::where('kunci', 'nama_instansi')->value('nilai') ?? env('APP_NAME') }}</strong>
      <span class="text-[0.62rem] font-medium tracking-[0.1em] uppercase text-blue-200/80 block mt-0.5">Administrator</span>
    </div>
  </div>

  {{-- Search Menu --}}
  <div class="px-3 pt-3 pb-1">
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
        </svg>
      </span>
      <input
        type="text"
        id="sidebar-search"
        placeholder="Cari menu…"
        autocomplete="off"
        class="w-full bg-blue-500 border border-white/12 text-white text-xs placeholder-white/35
               rounded-xl py-2 pl-2 pr-3 focus:outline-none focus:border-[#007afc] focus:bg-white/12
               transition-all duration-150 shadow-none ring-0 text-center placeholder:text-center" 
      >
      <button type="button" id="sidebar-search-clear"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-red-500 hover:text-red-300 transition-colors hidden bg-transparent border-0 cursor-pointer p-0 leading-none text-base">
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
                data-target="{{ $grupId }}"
                aria-controls="{{ $grupId }}"
                aria-expanded="{{ $grupAktif ? 'true' : 'false' }}">
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
             style="max-height:{{ $grupAktif ? 'none' : '0' }}">
          <div class="mt-0.5 ml-3 pl-3 border-l border-white/10 space-y-0.5 pb-1">
            @foreach($grup['items'] as $kunci => [$jalur, $namaIkon, $label])
              <a class="nav-item text-[0.82rem] py-1.5 {{ $menuAktif === $kunci ? 'aktif' : '' }}"
                 href="{{ url($jalur) }}"
                 data-label="{{ strtolower($label) }}">
                {!! ikon($namaIkon, 14) !!}
                <span class="flex-1 truncate">{{ $label }}</span>
                @if($kunci === 'izin' && $badgeIzin > 0)
                  <span class="ml-auto px-2 py-0.5 text-[0.65rem] font-bold rounded-full bg-amber-400 text-slate-900 shadow-sm">{{ $badgeIzin }}</span>
                @elseif($kunci === 'login_gagal' && $jumlahAncaman > 0)
                  <span class="ml-auto px-2 py-0.5 text-[0.65rem] font-bold rounded-full bg-red-500 text-white shadow-sm">{{ $jumlahAncaman }}</span>
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
    <a class="nav-item text-xs hover:bg-white/10 {{ $menuAktif === 'dokumentasi' ? 'aktif' : '' }}" href="{{ route('admin.documentation.index') }}">
      {!! ikon('surat', 17) !!}<span>Dokumentasi API</span>
    </a>
   
  </div>
</aside>

{{-- Mobile Backdrop --}}
<div class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 lg:hidden transition-opacity duration-300" id="tirai"></div>

<script>
(function () {
  // Buka/tutup panel sekaligus menyamakan gaya tombol trigger dan chevronnya,
  // supaya tidak meleset saat panel dibuka lewat pencarian menu.
  // tanpaBatas: pakai max-height:none supaya isi grup yang panjang tidak
  // terpotong kalau jendela di-resize selagi pencarian aktif.
  function setPanelTerbuka(panel, terbuka, tanpaBatas) {
    if (!panel) return;

    panel.style.maxHeight = terbuka
      ? (tanpaBatas ? 'none' : panel.scrollHeight + 'px')
      : '0';
    // Kelas max-h-0 ikut diseuaikan agar tidak bentrok bila inline style
    // suatu saat dihapus.
    panel.classList.toggle('max-h-0', ! terbuka);

    var grup = panel.closest('.group-accordion');
    var trigger = grup ? grup.querySelector('.accordion-trigger') : null;
    var chevron = trigger ? trigger.querySelector('.accordion-chevron') : null;
    if (!trigger || !chevron) return;

    trigger.setAttribute('aria-expanded', terbuka ? 'true' : 'false');

    if (terbuka) {
      trigger.classList.add('bg-white/12', 'text-white');
      trigger.classList.remove('text-slate-300');
      chevron.classList.add('rotate-180');
    } else {
      trigger.classList.remove('bg-white/12', 'text-white');
      trigger.classList.add('text-slate-300');
      chevron.classList.remove('rotate-180');
    }
  }

  // Panel grup aktif dirender server tanpa max-height inline (cuma tanpa kelas
  // max-h-0), jadi inline style kosong harus dibaca sebagai "terbuka".
  function panelTerbuka(panel) {
    if (!panel) return false;

    var gaya = panel.style.maxHeight;
    if (gaya) return gaya !== '0' && gaya !== '0px';

    return !panel.classList.contains('max-h-0');
  }

  // ── Accordion ─────────────────────────────────────
  document.querySelectorAll('.accordion-trigger').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var panel = document.getElementById(btn.dataset.target);
      var terbuka = !panelTerbuka(panel);
      setPanelTerbuka(panel, terbuka);
      catatStateAwal(panel, terbuka);
    });
  });

  // Samakan tinggi panel yang terbuka dari server agar bisa diklik tanpa
  // perlu klik dua kali (sebelumnya panel aktif belum punya max-height).
  document.querySelectorAll('.accordion-panel').forEach(function(panel) {
    if (!panelTerbuka(panel)) return;
    setPanelTerbuka(panel, true);
  });

  // ── Search ────────────────────────────────────────
  var input  = document.getElementById('sidebar-search');
  var clear  = document.getElementById('sidebar-search-clear');
  var nav    = document.getElementById('sidebar-nav');
  var empty  = document.getElementById('sidebar-empty');

  if (!input || !nav) return;

  var semuaItem  = Array.prototype.slice.call(nav.querySelectorAll('a[data-label]'));
  var standalone  = semuaItem.filter(function (a) { return a.parentElement === nav; });
  var stateAwal   = Array.prototype.slice.call(nav.querySelectorAll('.group-accordion')).map(function (grp) {
    var panel = grp.querySelector('.accordion-panel');
    return { grp: grp, panel: panel, terbuka: panelTerbuka(panel) };
  });

  // Opening/closing manual ikut dicatat, jadi-grup yang lagi aktif pun tetap
  // kembali seperti semula setelah pencarian dibersihkan.
  function catatStateAwal(panel, terbuka) {
    if (!stateAwal) return; // sidebar tanpa kolom pencarian
    stateAwal.forEach(function (s) {
      if (s.panel === panel) s.terbuka = terbuka;
    });
  }

  function hasilPencarian() {
    var q = input.value.trim().toLowerCase();

    // Kosong: pulihkan kondisi awal, termasuk grup yang tadi dibuka manual.
    if (!q) {
      semuaItem.forEach(function (a) { a.style.display = ''; });
      stateAwal.forEach(function (s) {
        s.grp.style.display = '';
        setPanelTerbuka(s.panel, s.terbuka);
      });
      if (empty) empty.classList.add('hidden');
      if (clear) clear.classList.add('hidden');
      return [];
    }

    if (clear) clear.classList.remove('hidden');

    var terlihat = [];

    stateAwal.forEach(function (s) {
      var anak   = Array.prototype.slice.call(s.grp.querySelectorAll('a[data-label]'));
      var cocok  = anak.filter(function (a) { return (a.dataset.label || '').indexOf(q) !== -1; });
      var grupCocok = (s.grp.dataset.labels || '').toLowerCase().split('|')
        .some(function (l) { return l.indexOf(q) !== -1; });

      // Grup yang namanya cocok tetap tampil walau tak ada item yang cocok;
      // dalam kasus itu seluruh itemnya ditampilkan agar tidak terlihat kosong.
      if (!cocok.length && grupCocok) cocok = anak;

      anak.forEach(function (a) {
        a.style.display = cocok.indexOf(a) !== -1 ? '' : 'none';
      });
      terlihat = terlihat.concat(cocok);

      s.grp.style.display = cocok.length ? '' : 'none';
      setPanelTerbuka(s.panel, cocok.length > 0, true);
    });

    standalone.forEach(function (a) {
      var cocok = (a.dataset.label || '').indexOf(q) !== -1;
      a.style.display = cocok ? '' : 'none';
      if (cocok) terlihat.push(a);
    });

    if (empty) empty.classList.toggle('hidden', terlihat.length > 0);

    return terlihat;
  }

  var hasilTerakhir = hasilPencarian();

  input.addEventListener('input', function () {
    hasilTerakhir = hasilPencarian();
  });

  function bersihkan() {
    input.value = '';
    hasilTerakhir = hasilPencarian();
    input.focus();
  }

  if (clear) clear.addEventListener('click', bersihkan);

  input.addEventListener('keydown', function (e) {
    // Escape: kosongkan pencarian bila ada isinya.
    if (e.key === 'Escape' && input.value !== '') {
      e.stopPropagation();
      bersihkan();
      return;
    }
    // Enter: langsung buka hasil pertama.
    if (e.key === 'Enter' && hasilTerakhir.length) {
      e.preventDefault();
      window.location.href = hasilTerakhir[0].getAttribute('href');
    }
  });

  // Shortboard: "/" atau Cmd/Ctrl+K untuk fokus ke kolom cari menu.
  document.addEventListener('keydown', function (e) {
    var isi = document.activeElement;
    var mengetik = isi && (isi.tagName === 'INPUT' || isi.tagName === 'TEXTAREA' || isi.tagName === 'SELECT' || isi.isContentEditable);
    if (mengetik) return;

    if (e.key === '/' || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
})();
</script>
