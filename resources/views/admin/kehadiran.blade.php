@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Kehadiran — {{ tgl_id($tanggal) }}</h2>
    <span class="badge badge-biru">{{ count($rows) }} catatan</span>
  </div>

  <form method="get" action="{{ url('admin/kehadiran') }}" class="bilah-alat">
    <input type="date" name="tanggal" value="{{ $tanggal }}">
    <select name="unit">
      <option value="">Semua Unit</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}</option>
      @endforeach
    </select>
    <label class="teks-kecil" style="display:flex;align-items:center;gap:6px;white-space:nowrap">
      <input type="checkbox" name="anomali" value="1" style="width:auto" {{ $hanyaAnomali ? 'checked' : '' }}>
      Hanya anomali GPS
    </label>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Pegawai</th><th>Foto</th><th>Shift</th>
          <th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th>
          <th>Jarak</th><th>Koordinat Masuk</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $r)
        <tr {{ $r->flag_anomali ? 'class="anomali-baris"' : '' }}>
          <td>
            <strong>{{ $r->nama_lengkap }}</strong>
            <br><span class="teks-kecil teks-redup">{{ $r->unit_nama ?? '—' }}@if(
              $r->sub_nama) — {{ $r->sub_nama }}@endif</span>
            @if($r->flag_anomali)
              <div class="catatan-anomali">{!! ikon('peringatan', 12) !!}
                {{ $r->catatan_anomali ?? 'Terindikasi anomali GPS' }}</div>
            @endif
          </td>
          <td>
            <div class="foto-mini">
              @if($r->foto_masuk)
                <a href="{{ url('foto/' . (int) $r->id . '/datang') }}" target="_blank"
                   rel="noopener" title="Selfie datang">
                  <img src="{{ url('foto/' . (int) $r->id . '/datang') }}" alt="Datang" loading="lazy"></a>
              @endif
              @if($r->foto_pulang)
                <a href="{{ url('foto/' . (int) $r->id . '/pulang') }}" target="_blank"
                   rel="noopener" title="Selfie pulang">
                  <img src="{{ url('foto/' . (int) $r->id . '/pulang') }}" alt="Pulang" loading="lazy"></a>
              @endif
              @if(! $r->foto_masuk && ! $r->foto_pulang)
                <span class="teks-redup teks-kecil">—</span>
              @endif
            </div>
          </td>
          <td>{{ label_shift(['kategori' => $r->shift_kategori,
                'jam_masuk' => $r->shift_masuk, 'jam_pulang' => $r->shift_pulang]) }}</td>
          <td class="angka">{{ jam_id($r->waktu_masuk) }}</td>
          <td class="angka">{{ jam_id($r->waktu_pulang) }}</td>
          <td>{!! badge_status(! $r->waktu_pulang ? 'Belum Pulang'
                 : ($r->status_masuk === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu'),
                 (int) $r->menit_terlambat) !!}</td>
          <td class="angka">{{ $r->jarak_datang !== null
                ? number_format((float) $r->jarak_datang, 0, ',', '.') . ' m' : '—' }}</td>
          <td class="angka teks-kecil">
            @if($r->lat_masuk !== null)
              <a href="https://www.google.com/maps?q={{ $r->lat_masuk }},{{ $r->lng_masuk }}"
                 target="_blank" rel="noopener">
                {{ number_format((float) $r->lat_masuk, 5) }}, {{ number_format((float) $r->lng_masuk, 5) }}
              </a>
            @else —@endif
          </td>
        </tr>
        @endforeach
        @if(! $rows)
        <tr><td colspan="8" class="tengah teks-redup">Tidak ada catatan kehadiran pada tanggal ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@if($ditolak)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('peringatan') !!} Percobaan Absen Ditolak (di luar radius)</h2>
    <span class="badge badge-merah">{{ count($ditolak) }}</span>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Nama</th><th>Aksi</th><th>Jarak dari RSUD</th><th>Koordinat</th></tr></thead>
      <tbody>
        @foreach($ditolak as $d)
        <tr>
          <td class="angka">{{ jam_id($d->waktu) }}</td>
          <td>{{ $d->nama_lengkap }}</td>
          <td>Absen {{ $d->tipe }}</td>
          <td class="angka">{{ number_format((float) $d->jarak_meter, 0, ',', '.') }} m</td>
          <td class="angka teks-kecil">
            <a href="https://www.google.com/maps?q={{ $d->latitude }},{{ $d->longitude }}"
               target="_blank" rel="noopener">
              {{ number_format((float) $d->latitude, 5) }}, {{ number_format((float) $d->longitude, 5) }}</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('peta') !!} Peta Posisi Absensi</h2>
    <span class="teks-redup teks-kecil">lingkaran = radius {{ number_format($radius, 0, ',', '.') }} m ·
      hijau = datang · navy = pulang · merah = anomali</span>
  </div>
  <div id="peta"></div>
  <div class="peta-kosong" id="peta-kosong" hidden>
    Peta tidak dapat dimuat (memerlukan koneksi internet untuk pustaka peta &amp; ubin peta).
    Gunakan tautan koordinat pada tabel di atas untuk membuka lokasi di Google Maps.
  </div>
</section>

@endsection

@section('skrip')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  var el     = document.getElementById('peta');
  var kosong = document.getElementById('peta-kosong');
  if (typeof window.L === 'undefined') {
    el.hidden = true;
    kosong.hidden = false;
    return;
  }
  var rs     = [@json($rsLat), @json($rsLng)];
  var radius = @json($radius);
  var titik  = @json($titik);

  var peta = L.map(el).setView(rs, 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap'
  }).addTo(peta);

  L.circle(rs, { radius: radius, color: '#1568B8', fillColor: '#1568B8', fillOpacity: .08, weight: 1.5 }).addTo(peta);
  L.marker(rs).addTo(peta).bindPopup('<strong>Titik RSUD Merauke</strong>');

  function esk(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  var batas = [rs];
  titik.forEach(function (t) {
    var warna = t.anomali ? '#B3312D' : (t.tipe === 'Datang' ? '#178A50' : '#0B3B66');
    L.circleMarker([t.lat, t.lng], {
      radius: 7, color: warna, fillColor: warna, fillOpacity: .85, weight: 1.5
    }).addTo(peta).bindPopup(
      '<strong>' + esk(t.nama) + '</strong><br>Absen ' + esk(t.tipe) + ' · pukul ' + esk(t.jam)
      + (t.anomali ? '<br><em>⚠ terindikasi anomali GPS</em>' : '')
    );
    batas.push([t.lat, t.lng]);
  });
  if (batas.length > 1) {
    peta.fitBounds(batas, { padding: [30, 30], maxZoom: 17 });
  }
})();
</script>
@endsection
