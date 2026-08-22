@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('atur') !!} Pengaturan Radius GPS &amp; Aplikasi</h2></div>

  <form method="post" action="{{ url('admin/pengaturan') }}" id="form-atur">
    @csrf

    <div class="form-grup">
      <label>Nama Instansi</label>
      <input type="text" name="nama_instansi" value="{{ $nama }}">
    </div>

    <h3>Titik Lokasi RSUD Merauke</h3>
    <p class="petunjuk">Absensi hanya diterima bila posisi GPS pegawai berada dalam radius dari titik ini.
      Cara termudah: berdiri di tengah area RSUD lalu tekan tombol di bawah, atau klik langsung pada peta.</p>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Latitude</label>
        <input type="text" name="lokasi_lat" id="lokasi_lat" required value="{{ $lat }}">
      </div>
      <div class="form-grup">
        <label class="wajib">Longitude</label>
        <input type="text" name="lokasi_lng" id="lokasi_lng" required value="{{ $lng }}">
      </div>
    </div>

    <div class="aksi-baris mb-3.5">
      <button type="button" class="btn btn-navy btn-kecil" id="btn-lokasi-saya">
        {!! ikon('peta', 15) !!} Gunakan Lokasi Perangkat Ini
      </button>
      <span class="teks-kecil teks-redup" id="ket-lokasi"></span>
    </div>

    <div id="peta-atur"></div>
    <div class="peta-kosong" id="peta-kosong" hidden>
      Peta tidak dapat dimuat (memerlukan internet). Masukkan koordinat secara manual —
      dapat disalin dari Google Maps (klik kanan titik RSUD &rarr; salin koordinat).
    </div>

    <div class="form-baris mt-4">
      <div class="form-grup">
        <label class="wajib">Radius Absensi (meter)</label>
        <input type="number" name="radius_meter" min="10" max="5000" required value="{{ $rad }}">
        <div class="petunjuk">Jarak maksimal pegawai dari titik RSUD saat absen. Bawaan: 100 m.</div>
      </div>
      <div class="form-grup">
        <label class="wajib">Toleransi Keterlambatan (menit)</label>
        <input type="number" name="toleransi_menit" min="0" max="120" required value="{{ $tol }}">
        <div class="petunjuk">Datang dalam rentang ini setelah jam masuk masih dihitung Tepat Waktu.</div>
      </div>
    </div>

    <div class="form-grup">
      <label class="teks-kecil flex items-center gap-2">
        <input type="checkbox" name="wajib_selfie" value="1" class="w-auto" {{ $selfie ? 'checked' : '' }}>
        Wajibkan foto selfie pada setiap absensi (mencegah titip absen)
      </label>
    </div>
    <div class="form-grup">
      <label class="teks-kecil flex items-center gap-2">
        <input type="checkbox" name="izinkan_pilih_shift" value="1" class="w-auto" {{ $izin ? 'checked' : '' }}>
        Izinkan pegawai memilih/mengubah shiftnya sendiri melalui dasbor
      </label>
    </div>

    <div class="aksi-baris">
      <button type="submit" class="btn btn-primer">{!! ikon('centang', 17) !!} Simpan Pengaturan</button>
    </div>
  </form>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('perisai') !!} Backup Database</h2></div>
  <p class="teks-kecil teks-redup mb-3">
    Mengunduh seluruh isi database sebagai berkas <code>.sql</code> yang dapat dipulihkan lewat
    phpMyAdmin. Lakukan secara berkala (mis. setiap akhir pekan) dan simpan di komputer/flashdisk
    terpisah dari server.
  </p>
  <a class="btn btn-navy" href="{{ url('admin/pengaturan/backup') }}">
    {!! ikon('unduh', 16) !!} Unduh Backup Database Sekarang</a>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('kunci') !!} Integrasi SIMRS — Kunci API</h2></div>
  <p class="teks-kecil teks-redup mb-3">
    SIMRS dapat menarik data pegawai, absensi, rekap, dan izin melalui API dengan menyertakan
    header <code>X-API-KEY</code> berikut. Rincian endpoint ada pada README aplikasi.
  </p>
  <div class="kotak-kunci">
    <code id="api-key">{{ $apiKey }}</code>
    <button type="button" class="btn btn-garis btn-kecil" id="salin-kunci">Salin</button>
  </div>
  <div class="aksi-baris mt-3">
    <form method="post" action="{{ url('admin/pengaturan/api-key') }}"
          onsubmit="return confirm('Buat kunci API baru? Kunci lama langsung tidak berlaku dan konfigurasi di SIMRS harus diperbarui.');">
      @csrf
      <button type="submit" class="btn btn-bahaya btn-kecil">Buat Kunci Baru</button>
    </form>
    <span class="teks-kecil teks-redup">Contoh uji:
      <code>curl -H "X-API-KEY: …" {{ url('api/v1/ping') }}</code></span>
  </div>
</section>

@endsection

@section('script')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  var latEl = document.getElementById('lokasi_lat');
  var lngEl = document.getElementById('lokasi_lng');
  var ket   = document.getElementById('ket-lokasi');

  var salin = document.getElementById('salin-kunci');
  if (salin) salin.addEventListener('click', function () {
    var teks = document.getElementById('api-key').textContent.trim();
    (navigator.clipboard ? navigator.clipboard.writeText(teks) : Promise.reject())
      .then(function () { salin.textContent = 'Tersalin!'; setTimeout(function(){ salin.textContent='Salin'; }, 1500); })
      .catch(function () { window.prompt('Salin kunci API:', teks); });
  });

  document.getElementById('btn-lokasi-saya').addEventListener('click', function () {
    if (!('geolocation' in navigator)) { ket.textContent = 'Perangkat tidak mendukung GPS.'; return; }
    ket.textContent = 'Mencari lokasi…';
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        latEl.value = pos.coords.latitude.toFixed(7);
        lngEl.value = pos.coords.longitude.toFixed(7);
        ket.textContent = 'Koordinat terisi (akurasi ±' + Math.round(pos.coords.accuracy) + ' m). Jangan lupa Simpan.';
        if (window._setPeta) window._setPeta(pos.coords.latitude, pos.coords.longitude);
      },
      function (err) {
        ket.textContent = err.code === err.PERMISSION_DENIED
          ? 'Izin lokasi ditolak. Bila diakses tanpa HTTPS, peramban memblokir GPS.'
          : 'Gagal mengambil lokasi (' + err.message + ').';
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  });

  var el = document.getElementById('peta-atur');
  if (typeof window.L === 'undefined') {
    el.hidden = true;
    document.getElementById('peta-kosong').hidden = false;
    return;
  }
  var awal = [parseFloat(latEl.value) || -8.499112, parseFloat(lngEl.value) || 140.404984];
  var peta = L.map(el).setView(awal, 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap'
  }).addTo(peta);

  var radius  = parseInt(@json($rad), 10) || 100;
  var marker  = L.marker(awal, { draggable: true }).addTo(peta);
  var lingkar = L.circle(awal, { radius: radius, color: '#1568B8', fillOpacity: .08 }).addTo(peta);

  function set(lat, lng, pindah) {
    latEl.value = lat.toFixed(7);
    lngEl.value = lng.toFixed(7);
    marker.setLatLng([lat, lng]);
    lingkar.setLatLng([lat, lng]);
    if (pindah) peta.setView([lat, lng], 16);
  }
  window._setPeta = function (lat, lng) { set(lat, lng, true); };

  peta.on('click', function (ev) { set(ev.latlng.lat, ev.latlng.lng, false); });
  marker.on('dragend', function () { var p = marker.getLatLng(); set(p.lat, p.lng, false); });

  document.querySelector('input[name="radius_meter"]').addEventListener('input', function () {
    lingkar.setRadius(parseInt(this.value, 10) || 100);
  });
})();
</script>
@endsection
