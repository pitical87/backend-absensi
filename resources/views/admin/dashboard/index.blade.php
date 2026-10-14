@extends('layouts.admin')

@section('content')

{{-- Data dashboard diisi menyusul lewat AJAX (admin.dashboard.data) supaya
     halaman tampil cepat tanpa menunggu rekap yang berat. --}}
<div id="dashboard-isi" aria-busy="true" aria-live="polite">

  <div class="stat-admin">
    @for($i = 0; $i < 5; $i++)
      <div class="stat">
        <span class="block"><span class="skel inline-block h-3 w-24 align-middle"></span></span>
        <span class="skel block h-8 w-14 mt-1"></span>
      </div>
    @endfor
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    @for($k = 0; $k < 2; $k++)
      <section class="kartu mb-0">
        <div class="kartu-kepala">
          <div class="skel h-4 w-56"></div>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-5">
          <div class="skel shrink-0 w-[150px] h-[150px] sm:w-[160px] sm:h-[160px] rounded-full"></div>
          <ul class="space-y-3 w-full sm:flex-1 m-0 p-0 list-none">
            @for($i = 0; $i < 4; $i++)
              <li class="skel h-4 w-full max-w-md"></li>
            @endfor
          </ul>
        </div>
      </section>
    @endfor
  </div>

  <section class="kartu">
    <div class="kartu-kepala">
      <div class="skel h-4 w-60"></div>
    </div>
    <div class="flex flex-col sm:flex-row items-center gap-6">
      <div class="skel shrink-0 w-[180px] h-[180px] rounded-full"></div>
      <ul class="space-y-3.5 w-full sm:flex-1 m-0 p-0 list-none">
        @for($i = 0; $i < 4; $i++)
          <li class="skel h-4 w-full max-w-md"></li>
        @endfor
      </ul>
    </div>
  </section>

  <section class="kartu">
    <div class="kartu-kepala">
      <div class="skel h-4 w-72"></div>
    </div>
    <div class="h-[250px] skel rounded-xl"></div>
  </section>

</div>

@endsection

@section('script')
<script>
(function () {
  'use strict';

  var wadah = document.getElementById('dashboard-isi');
  if (!wadah) return;

  var URL_DATA = @json(route('admin.dashboard.data'));

  // Script yang disisipkan lewat innerHTML tidak dieksekusi otomatis, jadi
  // kita buat ulang tiap tag <script> agar grafik ikut dijalankan.
  function jalankanSkrip(akar) {
    akar.querySelectorAll('script').forEach(function (lama) {
      var baru = document.createElement('script');
      for (var i = 0; i < lama.attributes.length; i++) {
        baru.setAttribute(lama.attributes[i].name, lama.attributes[i].value);
      }
      baru.textContent = lama.textContent;
      document.body.appendChild(baru);
      baru.remove();
    });
  }

  function gagal() {
    wadah.removeAttribute('aria-busy');
    wadah.innerHTML = '<section class="kartu"><p class="teks-redup tengah m-0">' +
      'Gagal memuat data dashboard. <a class="tautan" href="">Coba lagi</a>.</p></section>';
  }

  function muat() {
    fetch(URL_DATA, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        // Pengguna mungkin sudah pindah halaman; jangan sentuh DOM yang lepas.
        if (!document.body.contains(wadah)) return;
        wadah.innerHTML = html;
        wadah.removeAttribute('aria-busy');
        jalankanSkrip(wadah);
        // Pastikan Chart.js resize setelah DOM siap (utk AJAX load)
        setTimeout(function () {
          if (window.Chart && window.Chart.instances) {
            Object.values(window.Chart.instances).forEach(function (c) { c.resize(); });
          }
        }, 0);
      })
      .catch(gagal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', muat);
  } else {
    muat();
  }
})();
</script>
@endsection
