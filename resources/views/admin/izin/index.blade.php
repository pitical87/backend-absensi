@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('surat') !!} Pengajuan Izin / Sakit / Cuti / Dinas Luar</h2>
  </div>

  <div id="pesan-izin" class="hidden mb-4 px-4 py-3 rounded-xl text-sm"></div>

  {!! $tabs !!}

  <div id="isi-izin">{!! $isi !!}</div>

  {{-- <p class="petunjuk">Izin/Sakit/Cuti yang <strong>disetujui</strong> otomatis tidak dihitung sebagai
    Alpa dan tidak menurunkan persentase kehadiran; <strong>Dinas Luar</strong> dihitung sebagai hadir.
    <strong>Izin</strong> dan <strong>Cuti</strong> berjalan melalui alur berjenjang (Koordinator → Kepala
    Seksi/Sub Bagian → Kepala Bidang/Bagian → HRD) yang diputus pejabat terkait di menu Persetujuan mereka;
    kolom "Ambil Alih" di sini hanya untuk keadaan darurat.</p> --}}
</section>

@endsection

@section('script')
<script>
(function () {
  var tabBar = document.getElementById('tab-izin');
  var isiTab = document.getElementById('isi-izin');
  var pesan  = document.getElementById('pesan-izin');
  var csrf   = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var urlData = '{{ route('admin.izin.data') }}';

  var status = @json($status);
  var sedangMuat = false;

  function tampilPesan(teks, berhasil) {
    if (! teks) {
      pesan.classList.add('hidden');
      pesan.textContent = '';
      return;
    }
    pesan.className = 'mb-4 px-4 py-3 rounded-xl text-sm ' + (berhasil
      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
      : 'bg-rose-50 text-rose-700 border border-rose-200');
    pesan.textContent = teks;
  }

  function tabAktif() {
    var aktif = tabBar.querySelector('.chip.aktif[data-status]');
    return aktif ? aktif.getAttribute('data-status') : status;
  }

  function terapkan(h) {
    if (h.tabs) tabBar.innerHTML = h.tabs;
    if (h.isi !== undefined) isiTab.innerHTML = h.isi;
    if (h.status) status = h.status;
  }

  function muatStatus(kunci) {
    if (sedangMuat) return;
    sedangMuat = true;
    tampilPesan('', true);

    return fetch(urlData + '?' + new URLSearchParams({ status: kunci }).toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { tampilPesan('Gagal memuat data.', false); return; }
        terapkan(h);
      })
      .catch(function () { tampilPesan('Terjadi kesalahan jaringan.', false); })
      .then(function () { sedangMuat = false; });
  }

  function kirim(url, formData) {
    tampilPesan('Menyimpan…', true);

    return fetch(url, {
      method: 'POST',
      body: formData,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().then(function (h) { return { ok: r.ok, h: h }; }); })
      .then(function (b) {
        var h = b.h;
        terapkan(h);
        tampilPesan(h.pesan, !! h.sukses);
      })
      .catch(function () { tampilPesan('Terjadi kesalahan jaringan.', false); });
  }

  // Delegasi: tab status dan tombol Setujui/Tolak, termasuk pada baris hasil fetch.
  document.addEventListener('click', function (e) {
    var tab = e.target.closest('[data-status]');
    if (tab) {
      e.preventDefault();
      muatStatus(tab.getAttribute('data-status'));
      return;
    }

    var tombol = e.target.closest('button[name="putusan"]');
    if (! tombol) return;
    e.preventDefault();

    var konfirmasi = tombol.getAttribute('data-konfirmasi');
    if (konfirmasi && ! confirm(konfirmasi)) return;

    var form = tombol.closest('form');
    if (! form) return;

    var data = new FormData(form);
    data.set('putusan', tombol.value);
    kirim(form.getAttribute('action'), data);
  });

  // Enter pada kolom catatan tetap submit AJAX; karena tidak ada tombol yang
  // ditekan, server membalas pesan untuk memilih Setujui atau Tolak.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (! form.dataset || ! form.dataset.izin) return;
    e.preventDefault();
    kirim(form.getAttribute('action'), new FormData(form));
  });
})();
</script>
@endsection