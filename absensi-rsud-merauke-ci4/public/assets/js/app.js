/* ============================================================
   app.js v2 — dasbor pegawai (CodeIgniter 4)
   Alur absen: [kamera selfie] → GPS → kirim ke server
   ============================================================ */
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var btnDatang = document.getElementById('btn-datang');
  var btnPulang = document.getElementById('btn-pulang');
  var hasil     = document.getElementById('hasil-absen');
  var statusGps = document.getElementById('status-gps');
  var teksGps   = document.getElementById('teks-gps');

  // ---- elemen modal kamera ----
  var modal   = document.getElementById('modal-kamera');
  var video   = document.getElementById('kamera-video');
  var hasilIm = document.getElementById('kamera-hasil');
  var ketKam  = document.getElementById('kamera-ket');
  var bAmbil  = document.getElementById('kamera-ambil');
  var bUlang  = document.getElementById('kamera-ulang');
  var bKirim  = document.getElementById('kamera-kirim');
  var bBatal  = document.getElementById('kamera-batal');

  var streamAktif = null;
  var fotoData    = null;
  var tipeAktif   = null;

  function esk(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  var IKON = {
    sukses: '<svg class="ikon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/></svg>',
    gagal:  '<svg class="ikon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
    telat:  '<svg class="ikon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4M12 17.2v.3"/></svg>'
  };

  function tampilPesan(jenis, pesan, sub) {
    if (!hasil) return;
    var kelas = { sukses: 'pesan-sukses', telat: 'pesan-telat', gagal: 'pesan-gagal', info: 'pesan-info' }[jenis] || 'pesan-info';
    var ik = jenis === 'telat' ? IKON.telat : (jenis === 'gagal' ? IKON.gagal : IKON.sukses);
    hasil.innerHTML = '<div class="pesan-hasil ' + kelas + '">' + ik +
      '<span>' + esk(pesan) + (sub ? '<small>' + esk(sub) + '</small>' : '') + '</span></div>';
    hasil.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function setSibuk(tombol, sibuk, teks) {
    if (!tombol) return;
    tombol.classList.toggle('mencari', sibuk);
    if (sibuk) tombol.disabled = true;
    var ket = tombol.querySelector('small');
    if (ket && teks) ket.textContent = teks;
  }

  function cekDukungan() {
    if (!statusGps) return;
    if (!('geolocation' in navigator)) {
      statusGps.classList.add('mati');
      teksGps.textContent = 'Perangkat/peramban ini tidak mendukung GPS. Absensi tidak dapat dilakukan.';
      return;
    }
    if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(location.hostname)) {
      statusGps.classList.add('mati');
      teksGps.textContent = 'GPS & kamera diblokir peramban karena aplikasi diakses tanpa HTTPS. ' +
        'Hubungi admin agar server diakses melalui alamat https:// (lihat panduan pemasangan).';
    }
  }

  // ============================================================
  //  MODAL KAMERA
  // ============================================================
  function bukaKamera(tipe) {
    tipeAktif = tipe;
    fotoData  = null;
    hasilIm.hidden = true;
    video.hidden   = false;
    bAmbil.hidden  = false;
    bUlang.hidden  = true;
    bKirim.hidden  = true;
    ketKam.textContent = 'Posisikan wajah Anda di dalam bingkai, lalu ambil foto.';
    modal.classList.add('terbuka');

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
      audio: false
    }).then(function (stream) {
      streamAktif = stream;
      video.srcObject = stream;
    }).catch(function () {
      tutupKamera();
      if (ABSEN_CFG.wajibSelfie) {
        tampilPesan('gagal', 'Kamera tidak dapat diakses, padahal foto selfie diwajibkan.',
          'Izinkan akses kamera pada pengaturan peramban lalu coba lagi. Bila diakses tanpa HTTPS, peramban memblokir kamera.');
        aktifkanTombol(tipeAktif);
      } else {
        // selfie opsional → lanjut absen tanpa foto
        lanjutGps(tipeAktif, null);
      }
    });
  }

  function tutupKamera() {
    modal.classList.remove('terbuka');
    if (streamAktif) {
      streamAktif.getTracks().forEach(function (t) { t.stop(); });
      streamAktif = null;
    }
  }

  function aktifkanTombol(tipe) {
    var t = tipe === 'datang' ? btnDatang : btnPulang;
    if (t) { t.disabled = false; t.classList.remove('mencari'); }
  }

  bAmbil.addEventListener('click', function () {
    if (!streamAktif) return;
    var kanvas = document.createElement('canvas');
    var lebar  = Math.min(640, video.videoWidth || 640);
    var skala  = lebar / (video.videoWidth || lebar);
    kanvas.width  = lebar;
    kanvas.height = Math.round((video.videoHeight || 480) * skala);
    kanvas.getContext('2d').drawImage(video, 0, 0, kanvas.width, kanvas.height);
    fotoData = kanvas.toDataURL('image/jpeg', 0.8);

    hasilIm.src    = fotoData;
    hasilIm.hidden = false;
    video.hidden   = true;
    bAmbil.hidden  = true;
    bUlang.hidden  = false;
    bKirim.hidden  = false;
    ketKam.textContent = 'Periksa foto Anda. Bila sudah jelas, kirim absen.';
  });

  bUlang.addEventListener('click', function () {
    fotoData = null;
    hasilIm.hidden = true;
    video.hidden   = false;
    bAmbil.hidden  = false;
    bUlang.hidden  = true;
    bKirim.hidden  = true;
    ketKam.textContent = 'Posisikan wajah Anda di dalam bingkai, lalu ambil foto.';
  });

  bKirim.addEventListener('click', function () {
    var foto = fotoData;
    tutupKamera();
    lanjutGps(tipeAktif, foto);
  });

  bBatal.addEventListener('click', function () {
    tutupKamera();
    aktifkanTombol(tipeAktif);
    tampilPesan('info', 'Absensi dibatalkan.');
  });

  // ============================================================
  //  ALUR ABSEN
  // ============================================================
  function mulaiAbsen(tipe) {
    var tombol = tipe === 'datang' ? btnDatang : btnPulang;
    if (!('geolocation' in navigator)) {
      tampilPesan('gagal', 'Perangkat ini tidak mendukung GPS.');
      return;
    }
    setSibuk(tombol, true, 'Menyiapkan…');

    if (ABSEN_CFG.wajibSelfie && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      bukaKamera(tipe);
    } else if (ABSEN_CFG.wajibSelfie) {
      setSibuk(tombol, false);
      tombol.disabled = false;
      tampilPesan('gagal', 'Peramban ini tidak mendukung kamera, padahal foto selfie diwajibkan.',
        'Gunakan peramban modern (Chrome/Firefox) atau hubungi admin.');
    } else {
      lanjutGps(tipe, null);
    }
  }

  function lanjutGps(tipe, foto) {
    var tombol = tipe === 'datang' ? btnDatang : btnPulang;
    setSibuk(tombol, true, 'Mencari lokasi GPS…');
    tampilPesan('info', 'Mengambil lokasi GPS Anda…', 'Pastikan izin lokasi diberikan.');

    navigator.geolocation.getCurrentPosition(
      function (pos) { kirim(tipe, tombol, pos.coords, foto); },
      function (err) {
        setSibuk(tombol, false);
        tombol.disabled = false;
        var pesan = 'Gagal mengambil lokasi GPS.';
        if (err.code === err.PERMISSION_DENIED) {
          pesan = window.isSecureContext
            ? 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini pada pengaturan peramban, lalu coba lagi.'
            : 'Peramban memblokir GPS karena aplikasi diakses tanpa HTTPS. Hubungi admin sistem.';
        } else if (err.code === err.POSITION_UNAVAILABLE) {
          pesan = 'Sinyal GPS tidak tersedia. Pindah ke area terbuka lalu coba lagi.';
        } else if (err.code === err.TIMEOUT) {
          pesan = 'Waktu pencarian GPS habis. Coba lagi.';
        }
        tampilPesan('gagal', pesan);
      },
      { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
    );
  }

  function kirim(tipe, tombol, koordinat, foto) {
    setSibuk(tombol, true, 'Mengirim data absensi…');
    fetch(ABSEN_CFG.urlAbsen, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'fetch'
      },
      body: JSON.stringify({
        tipe: tipe,
        lat: koordinat.latitude,
        lng: koordinat.longitude,
        akurasi: koordinat.accuracy,
        foto: foto
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setSibuk(tombol, false);
        if (d.sukses) {
          tampilPesan(d.jenis || 'sukses', d.pesan, d.keterangan || '');
          var jam = document.getElementById(tipe === 'datang' ? 'jam-masuk' : 'jam-pulang');
          if (jam && d.jam) jam.textContent = d.jam;
          if (tipe === 'datang') {
            btnDatang.disabled = true;
            if (btnPulang) {
              btnPulang.disabled = false;
              var kp = document.getElementById('ket-pulang');
              if (kp) kp.textContent = 'Tekan saat mengakhiri tugas hari ini';
            }
            var kd = document.getElementById('ket-datang');
            if (kd && d.jam) kd.textContent = 'Tercatat pukul ' + d.jam;
          } else {
            btnPulang.disabled = true;
            var kk = document.getElementById('ket-pulang');
            if (kk && d.jam) kk.textContent = 'Tercatat pukul ' + d.jam;
          }
          setTimeout(function () { location.reload(); }, 2800);
        } else {
          tombol.disabled = false;
          tampilPesan('gagal', d.pesan || 'Absensi ditolak.', d.keterangan || '');
        }
      })
      .catch(function () {
        setSibuk(tombol, false);
        tombol.disabled = false;
        tampilPesan('gagal', 'Tidak dapat terhubung ke server. Periksa jaringan Anda lalu coba lagi.');
      });
  }

  if (btnDatang) btnDatang.addEventListener('click', function () { mulaiAbsen('datang'); });
  if (btnPulang) btnPulang.addEventListener('click', function () { mulaiAbsen('pulang'); });

  // ============================================================
  //  PEMILIHAN SHIFT
  // ============================================================
  var pilihShift = document.getElementById('pilih-shift');
  if (pilihShift) {
    pilihShift.addEventListener('change', function () {
      if (!this.value) return;
      var sel = this;
      sel.disabled = true;
      fetch(ABSEN_CFG.urlShift, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'fetch'
        },
        body: JSON.stringify({ shift_id: parseInt(sel.value, 10) })
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          sel.disabled = false;
          if (d.sukses) {
            tampilPesan('sukses', 'Shift kerja diperbarui.', d.label || '');
            setTimeout(function () { location.reload(); }, 1400);
          } else {
            tampilPesan('gagal', d.pesan || 'Shift gagal diperbarui.');
          }
        })
        .catch(function () {
          sel.disabled = false;
          tampilPesan('gagal', 'Tidak dapat terhubung ke server.');
        });
    });
  }

  cekDukungan();
})();
