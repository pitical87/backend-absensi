@extends('layouts.pegawai')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Pengajuan Lembur</h2>
    <span class="badge badge-abu">batas pengajuan {{ $batasJam }} jam sebelum mulai · maks {{ $maksJam }} jam/hari</span>
  </div>
  <p class="petunjuk">Ajukan lembur untuk tanggal tertentu dengan rentang jam yang diinginkan (maks {{
      $hariKeDepan }} hari ke depan). Setelah disetujui atasan langsung, Anda dapat melakukan absen masuk &
      pulang lembur terpisah dari absen reguler.</p>

  <form method="post" action="{{ url('lembur') }}" id="form-lembur" class="space-y-3">
    @csrf
    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Tanggal Lembur</label>
        <input type="date" name="tanggal" required
               min="{{ now()->toDateString() }}"
               max="{{ now()->copy()->addDays($hariKeDepan)->toDateString() }}"
               value="{{ old('tanggal', now()->toDateString()) }}">
      </div>
      <div class="form-grup">
        <label class="wajib">Jam Mulai</label>
        <input type="time" name="jam_mulai" required value="{{ old('jam_mulai') }}">
      </div>
      <div class="form-grup">
        <label class="wajib">Jam Selesai</label>
        <input type="time" name="jam_selesai" required value="{{ old('jam_selesai') }}">
      </div>
    </div>
    <div class="form-grup">
      <label class="wajib">Keterangan / Keperluan Lembur</label>
      <textarea name="keterangan" required maxlength="1000" rows="2"
                placeholder="Alasan / keperluan lembur…">{{ old('keterangan') }}</textarea>
    </div>
    <div class="aksi-baris mb-0">
      <button type="submit" class="btn btn-primer">{!! ikon('surat', 17) !!} Ajukan Lembur</button>
    </div>
  </form>
</section>

@if($disetujui)
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('masuk') !!} Lembur Disetujui</h2>
    <span class="badge badge-hijau">{{ count($disetujui) }} rencana</span>
  </div>
  <p class="petunjuk">Tekan <strong>Mulai Lembur</strong> saat hendak memulai (berada dalam area RSUD), lalu
    <strong>Selesai Lembur</strong> setelah selesai. Selfie akan diambil untuk memastikan kehadiran Anda.</p>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Tanggal</th><th>Rentang Waktu</th><th>Durasi</th><th>Keterangan</th><th>Status / Aksi</th></tr>
      </thead>
      <tbody>
        @foreach($disetujui as $r)
        <tr>
          <td class="angka">{{ tgl_id($r->tanggal->format('Y-m-d'), false) }}</td>
          <td class="angka">{{ jam_id($r->jam_mulai) }} — {{ jam_id($r->jam_selesai) }}</td>
          <td class="angka">{{ (float) $r->durasi_jam }} jam</td>
          <td class="teks-kecil">{{ $r->keterangan }}</td>
          <td>
            @php $absen = $r->absenLembur; @endphp
            @if($absen && $absen->waktu_masuk && $absen->waktu_pulang)
              <span class="badge badge-hijau">Selesai</span>
              <span class="teks-kecil teks-redup">masuk {{ jam_id($absen->waktu_masuk) }} · pulang {{ jam_id($absen->waktu_pulang) }}</span>
            @elseif($absen && $absen->waktu_masuk)
              <button type="button" class="btn btn-navy btn-kecil btn-absen-lembur" data-tipe="pulang"
                      data-tanggal="{{ $r->tanggal->format('Y-m-d') }}">{!! ikon('pulang', 14) !!} Selesai Lembur</button>
            @else
              <button type="button" class="btn btn-primer btn-kecil btn-absen-lembur" data-tipe="masuk"
                      data-tanggal="{{ $r->tanggal->format('Y-m-d') }}">{!! ikon('masuk', 14) !!} Mulai Lembur</button>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('log') !!} Riwayat Pengajuan Saya</h2></div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Diajukan</th><th>Tanggal</th><th>Rentang</th><th>Durasi</th><th>Status</th><th>Catatan</th></tr></thead>
      <tbody>
        @foreach($riwayat as $r)
        <tr>
          <td class="angka teks-kecil">{{ tgl_id($r->created_at, false) }} · {{ jam_id($r->created_at) }}</td>
          <td class="angka">{{ tgl_id($r->tanggal->format('Y-m-d'), false) }}</td>
          <td class="angka">{{ jam_id($r->jam_mulai) }} — {{ jam_id($r->jam_selesai) }}</td>
          <td class="angka">{{ (float) $r->durasi_jam }} jam</td>
          <td>{!! badge_tahap($r->status === 'Ditolak' && str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon') ? 'Menunggu' : $r->status) !!}
            @if($r->status === 'Ditolak' && str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon'))
              <span class="badge badge-abu">Dibatalkan</span>
            @endif
            @if($r->status === 'Menunggu')
              <form method="post" action="{{ url('lembur/batal/' . (int) $r->id) }}" class="inline"
                    onsubmit="return confirm('Batalkan pengajuan lembur ini?');">
                @csrf
                <button type="submit" class="btn btn-bahaya btn-kecil">Batal</button>
              </form>
            @endif
          </td>
          <td class="teks-kecil">
            {{ $r->catatan_keputusan ?? '—' }}
            @if($r->diprosesOlehUser && ! str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon'))
              — {{ $r->diprosesOlehUser->nama_lengkap }}
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $riwayat)
        <tr><td colspan="6" class="tengah teks-redup">Belum ada pengajuan lembur.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

<!-- MODAL SELFIE ABSEN LEMBUR -->
<div class="modal-tirai" id="modal-kamera-lembur">
  <div class="modal-kamera">
    <header>{!! ikon('kamera', 19) !!} Foto Selfie Absen Lembur</header>
    <div class="isi">
      <div class="bingkai-kamera">
        <video id="kamera-video-lembur" autoplay playsinline muted></video>
        <img id="kamera-hasil-lembur" alt="" hidden>
        <div class="garis"></div>
      </div>
      <p class="ket" id="kamera-ket-lembur">Posisikan wajah Anda di dalam bingkai, lalu ambil foto.</p>
      <div class="aksi-baris">
        <button type="button" class="btn btn-garis btn-kecil" id="kamera-batal-lembur">Batal</button>
        <button type="button" class="btn btn-garis btn-kecil" id="kamera-ulang-lembur" hidden>Ulangi</button>
        <button type="button" class="btn btn-navy btn-kecil" id="kamera-ambil-lembur">{!! ikon('kamera', 15) !!} Ambil Foto</button>
        <button type="button" class="btn btn-primer btn-kecil" id="kamera-kirim-lembur" hidden>{!! ikon('centang', 15) !!} Kirim Absen</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  var urlAbsen = @json(url('absen-lembur'));
  var wajibSelfie = {{ pengaturan('wajib_selfie', '1') === '1' ? 'true' : 'false' }};
  var target = null; // { tipe, tanggal }

  var modal   = document.getElementById('modal-kamera-lembur');
  var video   = document.getElementById('kamera-video-lembur');
  var hasil   = document.getElementById('kamera-hasil-lembur');
  var ket     = document.getElementById('kamera-ket-lembur');
  var ambil   = document.getElementById('kamera-ambil-lembur');
  var ulang   = document.getElementById('kamera-ulang-lembur');
  var kirim   = document.getElementById('kamera-kirim-lembur');
  var capture = null;
  var stream  = null;

  function bukaModal() {
    modal.classList.add('terbuka');
    if (capture) {
      kirim.hidden = false;
    } else if (wajibSelfie) {
      mulaiKamera();
    } else {
      kirim.hidden = true;
      ket.textContent = 'Absen lembur memerlukan lokasi Anda dekat area RSUD. Tekan Kirim Absen.';
    }
  }

  function tutupModal() {
    modal.classList.remove('terbuka');
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
  }

  function mulaiKamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then(function (s) {
        stream = s;
        video.srcObject = s;
        video.hidden = false;
        hasil.hidden = true;
        ambil.hidden = false;
      })
      .catch(function () {
        ket.textContent = 'Kamera tidak tersedia. Anda masih dapat melanjutkan tanpa selfie.';
        ambil.hidden = true;
      });
  }

  function ambilFoto() {
    if (!stream) { return; }
    var c = document.createElement('canvas');
    c.width = video.videoWidth || 640;
    c.height = video.videoHeight || 480;
    c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
    capture = c.toDataURL('image/jpeg');
    video.hidden = true;
    hasil.src = capture;
    hasil.hidden = false;
    ambil.hidden = true;
    ulang.hidden = false;
    kirim.hidden = false;
    ket.textContent = 'Foto siap. Tekan Kirim Absen untuk menyelesaikan.';
  }

  document.querySelectorAll('.btn-absen-lembur').forEach(function (btn) {
    btn.addEventListener('click', function () {
      target = { tipe: btn.dataset.tipe, tanggal: btn.dataset.tanggal };
      if (target.tipe === 'pulang' || !wajibSelfie) {
        kirimAbsen(target.tipe, target.tanggal, null);
      } else {
        capture = null;
        bukaModal();
      }
    });
  });

  function kirimAbsen(tipe, tanggal, foto) {
    // ambil lokasi
    if (!('geolocation' in navigator)) {
      alert('Perangkat tidak mendukung GPS.');
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      var body = new FormData();
      body.append('tipe', tipe);
      body.append('tanggal', tanggal);
      body.append('lat', pos.coords.latitude);
      body.append('lng', pos.coords.longitude);
      if (pos.coords.accuracy) body.append('akurasi', pos.coords.accuracy);
      if (foto) body.append('foto', foto);
      fetch(urlAbsen, { method: 'POST', body: body, headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf"]') || {}).content || '' } })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (res.ok) { alert(res.d.pesan); window.location.reload(); }
          else alert(res.d.pesan || 'Absen gagal.');
        })
        .catch(function (e) { alert('Gagal mengirim absen.'); });
    }, function (err) {
      alert('Tidak dapat mengambil lokasi. Pastikan GPS aktif dan izin lokasi diberikan.');
    }, { enableHighAccuracy: true, timeout: 15000 });
  }

  ambil.addEventListener('click', ambilFoto);
  ulang.addEventListener('click', function () { capture = null; hasil.hidden = true; mulaiKamera(); });
  document.getElementById('kamera-batal-lembur').addEventListener('click', tutupModal);
  kirim.addEventListener('click', function () {
    if (!target) return;
    kirimAbsen(target.tipe, target.tanggal, capture);
    tutupModal();
  });
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });
})();
</script>
@endsection
