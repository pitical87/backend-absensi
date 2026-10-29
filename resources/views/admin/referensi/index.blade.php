@extends('layouts.admin')

@section('content')
<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.referensi.data', 'kategori') }}"
         data-url-aksi="{{ route('admin.referensi.aksi', 'kategori') }}">
  <div class="kartu-kepala flex items-center justify-between flex-wrap gap-2">
    <h2>{!! ikon('atur') !!} Kategori Jabatan</h2>
    <div class="flex items-center gap-2 flex-wrap">
      <input type="text" data-cari name="q" value="{{ $q }}" autocomplete="off"
             placeholder="Cari kategori…" class="min-w-[170px]">
      <button type="button" class="btn btn-primer btn-kecil" data-buka-kategori>
        {!! ikon('tambah', 15) !!} Tambah
      </button>
    </div>
  </div>

  <div data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.referensi.tabel_kategori', ['daftar' => $daftarKategori, 'q' => $q])
  </div>

  <p class="petunjuk">Kategori dipakai pada form Data Pegawai, Master Jabatan, Struktur
    Organisasi, filter rekap, dan import Excel. Aktifkan <strong>Struktur</strong> agar
    kategori muncul pada pilihan Master Jabatan / Struktur Organisasi.</p>

  <div class="modal-tirai" id="modal-kategori">
    <div class="modal-kamera">
      <header class="flex items-center justify-between">
        <span class="flex items-center text-black gap-2">{!! ikon('atur', 19) !!}
          <span id="kat-judul">Tambah Kategori Jabatan</span></span>
        <button type="button" id="modal-kategori-tutup" class="btn btn-garis btn-kecil">&times;</button>
      </header>
      <div class="isi">
        <form method="post" action="{{ route('admin.referensi.aksi', 'kategori') }}"
              data-aksi-form data-reset data-tutup="#modal-kategori-tutup">
          @csrf
          <input type="hidden" name="aksi" id="kat-aksi" value="tambah">
          <input type="hidden" name="id" id="kat-id" value="">
          <div class="form-grup">
            <label class="wajib">Nama Kategori</label>
            <input type="text" name="nama" id="kat-nama" maxlength="100" required
                   placeholder="cth. Kepala Bidang Pelayanan">
          </div>
          <div class="form-grup">
            <label class="flex items-center gap-2">
              <input type="checkbox" name="untuk_struktur" id="kat-struktur" value="1">
              <span>Tampilkan pada pilihan Struktur Organisasi / Master Jabatan</span>
            </label>
            <div class="petunjuk">Kategori umum (cth. Staf/Pelaksana) tanpa jabatan
              struktural cukup dicentang mati.</div>
          </div>
          <div class="form-grup">
            <label>Urutan Tampil</label>
            <input type="number" name="urutan" id="kat-urutan" min="0" max="9999" value="0">
          </div>
          <div class="aksi-baris justify-end">
            <button type="button" class="btn btn-garis btn-kecil" id="modal-kategori-batal">Batal</button>
            <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="kartu" data-ajax-tabel
         data-url-data="{{ route('admin.referensi.data', 'posisi') }}"
         data-url-aksi="{{ route('admin.referensi.aksi', 'posisi') }}">
  <div class="kartu-kepala flex items-center justify-between flex-wrap gap-2">
    <h2>{!! ikon('struktur') !!} Posisi Jabatan</h2>
    <div class="flex items-center gap-2 flex-wrap">
      <input type="text" data-cari name="q" value="{{ $q }}" autocomplete="off"
             placeholder="Cari posisi…" class="min-w-[170px]">
      <button type="button" class="btn btn-primer btn-kecil" data-buka-posisi>
        {!! ikon('tambah', 15) !!} Tambah
      </button>
    </div>
  </div>

  <div data-pesan class="hidden rounded-md px-3 py-2 teks-kecil mb-3"></div>

  <div data-tabel>
    @include('admin.referensi.tabel_posisi', ['daftar' => $daftarPosisi, 'q' => $q])
  </div>

  <p class="petunjuk"><strong>Tingkat</strong> menentukan alur persetujuan izin pemohon:
    0 = mulai tahap 1, 1 = mulai tahap 2, dan seterusnya; 4 ke atas berarti pengajuan
    langsung disetujui tanpa tahap lanjutan. Penyetuju suatu tahap adalah pemegang posisi
    dengan tingkat tahap tersebut.</p>

  <div class="modal-tirai" id="modal-posisi">
    <div class="modal-kamera">
      <header class="flex items-center justify-between">
        <span class="flex items-center text-black gap-2">{!! ikon('struktur', 19) !!}
          <span id="pos-judul">Tambah Posisi Jabatan</span></span>
        <button type="button" id="modal-posisi-tutup" class="btn btn-garis btn-kecil">&times;</button>
      </header>
      <div class="isi">
        <form method="post" action="{{ route('admin.referensi.aksi', 'posisi') }}"
              data-aksi-form data-reset data-tutup="#modal-posisi-tutup">
          @csrf
          <input type="hidden" name="aksi" id="pos-aksi" value="tambah">
          <input type="hidden" name="id" id="pos-id" value="">
          <div class="form-grup">
            <label class="wajib">Nama Posisi</label>
            <input type="text" name="nama" id="pos-nama" maxlength="50" required
                   placeholder="cth. Kepala Sub Bagian Umum">
          </div>
          <div class="form-grup">
            <label>Tingkat Persetujuan Izin</label>
            <input type="number" name="tingkat" id="pos-tingkat" min="0" max="9" value="0">
            <div class="petunjuk">0 = pengaju biasa (mulai tahap 1); 1–4 = mulai tahap
              berikutnya sekaligus menyetujui tahap setingkatnya; ≥4 tanpa tahap lanjutan.</div>
          </div>
          <div class="form-grup">
            <label>Urutan Tampil</label>
            <input type="number" name="urutan" id="pos-urutan" min="0" max="9999" value="0">
          </div>
          <div class="aksi-baris justify-end">
            <button type="button" class="btn btn-garis btn-kecil" id="modal-posisi-batal">Batal</button>
            <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection

@section('script')
<script>
(function () {
  function pasang(cfg) {
    var modal  = document.getElementById(cfg.modal);
    var tutup  = document.getElementById(cfg.tutup);
    var batal  = document.getElementById(cfg.batal);
    var aksi   = document.getElementById(cfg.aksi);
    var id     = document.getElementById(cfg.id);
    var nama   = document.getElementById(cfg.nama);
    var urutan = document.getElementById(cfg.urutan);
    var judul  = document.getElementById(cfg.judul);

    function buka(mode, data) {
      aksi.value = mode;
      id.value = data && data.id ? data.id : '';
      nama.value = data && data.nama ? data.nama : '';
      urutan.value = data && data.urutan !== undefined ? data.urutan : 0;
      cfg.isiTambahan(mode, data);
      judul.textContent = mode === 'ubah' ? cfg.labelUbah : cfg.labelTambah;
      modal.classList.add('terbuka');
      nama.focus();
    }

    function tutupModal() { modal.classList.remove('terbuka'); }

    document.addEventListener('click', function (e) {
      if (e.target.closest(cfg.buka)) { buka('tambah', null); return; }
      var btn = e.target.closest(cfg.ubah);
      if (btn) buka('ubah', {
        id: btn.dataset.id,
        nama: btn.dataset.nama,
        urutan: btn.dataset.urutan !== undefined ? parseInt(btn.dataset.urutan, 10) : 0,
        struktur: btn.dataset.struktur === '1',
        tingkat: btn.dataset.tingkat !== undefined ? parseInt(btn.dataset.tingkat, 10) : 0
      });
    });

    tutup.addEventListener('click', tutupModal);
    batal.addEventListener('click', tutupModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('terbuka')) tutupModal();
    });
  }

  pasang({
    modal: 'modal-kategori', tutup: 'modal-kategori-tutup', batal: 'modal-kategori-batal',
    aksi: 'kat-aksi', id: 'kat-id', nama: 'kat-nama', urutan: 'kat-urutan', judul: 'kat-judul',
    buka: '[data-buka-kategori]', ubah: '.btn-ubah-kategori',
    labelTambah: 'Tambah Kategori Jabatan', labelUbah: 'Ubah Kategori Jabatan',
    isiTambahan: function (mode, data) {
      document.getElementById('kat-struktur').checked =
        mode === 'tambah' ? true : !!(data && data.struktur);
    }
  });

  pasang({
    modal: 'modal-posisi', tutup: 'modal-posisi-tutup', batal: 'modal-posisi-batal',
    aksi: 'pos-aksi', id: 'pos-id', nama: 'pos-nama', urutan: 'pos-urutan', judul: 'pos-judul',
    buka: '[data-buka-posisi]', ubah: '.btn-ubah-posisi',
    labelTambah: 'Tambah Posisi Jabatan', labelUbah: 'Ubah Posisi Jabatan',
    isiTambahan: function (mode, data) {
      document.getElementById('pos-tingkat').value =
        mode === 'ubah' && data && data.tingkat !== undefined ? data.tingkat : 0;
    }
  });
})();
</script>
@endsection
