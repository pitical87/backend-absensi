@extends('layouts.pegawai')

@section('content')

<section class="kartu">
  <div class="kartu-kepala flex justify-between items-center ">
    <h2>Logbook Saya</h2>
    <div class="flex gap-1 rounded-xl bg-slate-100 p-1 mt-3">
      <button type="button" id="tab-data" class="rounded-lg px-3.5 py-1.5 text-xs font-semibold bg-white text-slate-900 shadow-sm transition-colors cursor-pointer">Data</button>
      <button type="button" id="tab-input" class="rounded-lg px-3.5 py-1.5 text-xs font-semibold text-slate-500 transition-colors cursor-pointer">Input</button>
    </div>
  </div>

  {{-- PANEL: DATA --}}
  <div id="panel-data" class="p-2">
    <div class="flex flex-wrap items-end gap-2 px-2 pb-2">
      <input type="text" id="cari-data" placeholder="Cari isi aktivitas…" class="min-w-56">
      <label class="teks-redup teks-kecil">Bulan
        <select id="filter-bulan">
          <option value="">Semua</option>
          @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nmBulan)
            <option value="{{ $i + 1 }}">{{ $nmBulan }}</option>
          @endforeach
        </select>
      </label>
      <label class="teks-redup teks-kecil">Tahun
        <select id="filter-tahun">
          <option value="">Semua</option>
        </select>
      </label>
      <button type="button" id="cetak-logbook" class="btn btn-navy btn-kecil ms-auto">{!! ikon('cetak', 13) !!} Cetak</button>
      <button type="button" id="hapus-terpilih" class="btn btn-bahaya btn-kecil" disabled>Hapus Terpilih</button>
    </div>

    <p id="data-info" class="teks-redup teks-kecil px-2 pb-1">Memuat data logbook&hellip;</p>
    <div class="overflow-y-auto max-h-[60vh]">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left teks-redup teks-kecil border-b border-slate-200">
            <th class="px-2 py-2 w-8"><input type="checkbox" id="pilih-semua" title="Pilih semua di halaman ini"></th>
            <th class="px-2 py-2 w-28">Tanggal</th>
            <th class="px-2 py-2 w-20">Jam</th>
            <th class="px-2 py-2">Isi</th>
            <th class="px-2 py-2 w-48">Status</th>
            <th class="px-2 py-2 w-16 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody id="data-logbook"></tbody>
      </table>
    </div>
    <div id="paginasi-data" class="flex items-center justify-center gap-3 pt-3"></div>
  </div>

  {{-- PANEL: INPUT --}}
  <div id="panel-input" class="hidden">
    <div class="flex flex-wrap gap-2 px-2 pt-2">
      <button type="button" id="tambah-baris" class="btn btn-garis btn-kecil">+ Tambah Baris</button>
      <button type="submit" id="simpan-logbook" form="form-logbook" class="btn btn-navy btn-kecil">Simpan</button>
      <button type="button" id="reset-logbook" class="btn btn-bahaya btn-kecil">Reset</button>
      <button type="button" id="tombol-template" class="btn btn-garis btn-kecil">{!! ikon('log', 14) !!} Template</button>
      <button type="button" id="tombol-ambil-simrs" class="btn btn-garis btn-kecil">{!! ikon('unduh', 14) !!} Ambil dari SIMRS</button>
    </div>

    <form id="form-logbook" method="post" action="{{ route('logbook.simpan') }}" class="p-2">
      @csrf

      <div class="overflow-y-scroll h-[60vh]">
        <table class="w-full">
          <tbody id="baris-logbook"></tbody>
        </table>
      </div>
      <p id="pesan-form" class="teks-redup teks-kecil mt-2"></p>
    </form>
  </div>
</section>

{{-- Modal Ambil dari SIMRS --}}
<div id="modal-simrs" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-xl max-h-[85vh] overflow-auto">
    <div class="kartu-kepala">
      <h2>Ambil dari SIMRS <span class="teks-redup teks-kecil">(Tindakan + Lab)</span></h2>
      <button type="button" id="modal-tutup" class="btn btn-garis btn-kecil">&times; Tutup</button>
    </div>

    <div class="p-4 text-sm">
      <div class="flex flex-wrap items-end gap-2 mb-2">
        <label class="teks-redup teks-kecil">Dari
          <input type="date" id="modal-dari">
        </label>
        <label class="teks-redup teks-kecil">Sampai
          <input type="date" id="modal-sampai">
        </label>
        <button type="button" id="tombol-preview" class="btn btn-navy btn-kecil">Tampilkan Preview</button>
      </div>

      <p class="teks-redup teks-kecil mb-2 mt-0">Data diambil dari akun SIMRS milik Anda sesuai mapping.</p>

      <div id="modal-info" class="text-sm"></div>
      <div class="overflow-auto h-64 p-2 rounded-xl bg-slate-100">
        <ol id="modal-preview" class="list-decimal pl-6 text-sm space-y-1 m-0 my-2"></ol>
      </div>
    </div>

    <div class="flex justify-end gap-2 px-4 pb-4">
      <button type="button" id="modal-terapkan" class="btn btn-navy btn-kecil" disabled>Masukkan ke Form</button>
    </div>
  </div>
</div>

{{-- Modal Template Logbook --}}
<div id="modal-template" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-lg max-h-[85vh] overflow-auto">
    <div class="kartu-kepala">
      <h2>Template Logbook</h2>
      <button type="button" id="template-tutup" class="btn btn-garis btn-kecil">&times; Tutup</button>
    </div>

    <div class="p-4 text-sm">
      @if(count($templates))
        <ul id="daftar-template" class="space-y-2 mb-3">
          @foreach($templates as $t)
            <li>
              <label class="flex items-start gap-2 rounded-xl border border-slate-200 px-3 py-2 hover:bg-slate-50 cursor-pointer">
                <input type="radio" name="pilih-template" value="{{ $t['id'] }}"
                       data-isi="{{ $t['isi'] }}" data-milik="{{ $t['milik_saya'] ? '1' : '0' }}" class="mt-1 shrink-0">
                <span class="flex-1">
                  <span class="block">{{ $t['isi'] }}</span>
                  <span class="teks-redup teks-kecil">
                    {{ $t['type'] === 'all' ? 'Semua pengguna' : 'Hanya saya' }}
                    &middot; {{ $t['milik_saya'] ? 'Milik saya' : $t['pembuat'] }}
                  </span>
                </span>
              </label>
            </li>
          @endforeach
        </ul>
      @else
        <p id="daftar-template-kosong" class="teks-redup mb-3">Belum ada template. Buat lewat tombol &quot;+ Tambah&quot;.</p>
      @endif

      <form method="post" action="{{ route('logbook.template.simpan') }}"
            id="form-tambah-template" class="hidden rounded-xl border border-slate-200 p-3 mb-1 space-y-2">
        @csrf
        <textarea name="isi" rows="3" required placeholder="Isi template aktivitas" class="w-full"></textarea>
        <input type="hidden" name="type" value="user">
        <div class="flex justify-end gap-2">
          <button type="button" id="template-tambah-batal" class="btn btn-garis btn-kecil">Batal</button>
          <button type="submit" class="btn btn-navy btn-kecil">Simpan Template</button>
        </div>
      </form>
    </div>

    <div class="flex justify-end gap-2 px-4 pb-4">
      <button type="button" id="template-buka-tambah" class="btn btn-garis btn-kecil">+ Tambah</button>
      <form method="post" action="{{ route('logbook.template.hapus') }}" id="form-hapus-template" class="contents">
        @csrf
        <input type="hidden" name="template_id" id="hapus-template-id">
        <button type="submit" id="template-hapus" class="btn btn-bahaya btn-kecil" disabled>Hapus</button>
      </form>
      <button type="button" id="template-terapkan" class="btn btn-navy btn-kecil" disabled>Terapkan ke Form</button>
    </div>
  </div>
</div>

{{-- Modal Edit Logbook --}}
<div id="modal-edit" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-md">
    <div class="kartu-kepala">
      <h2>Edit Logbook</h2>
      <button type="button" id="edit-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <form id="form-edit" action="{{ route('logbook.ubah') }}" method="post">
      @csrf
      <div class="grid grid-cols-[1fr_120px] gap-2 px-4 pt-3">
        <label>Tanggal
          <input type="date" id="edit-tanggal" required>
        </label>
        <label>Jam
          <input type="time" id="edit-jam" required>
        </label>
      </div>
      <div class="px-4 pt-2">
        <label>Isi Aktivitas
          <textarea id="edit-isi" rows="5" required maxlength="1000"></textarea>
        </label>
      </div>
      <p id="edit-pesan" class="teks-redup teks-kecil px-4 pt-2"></p>
      <div class="flex justify-end gap-2 px-4 py-3">
        <button type="button" id="edit-batal" class="btn btn-garis btn-kecil">Batal</button>
        <button type="submit" class="btn btn-navy btn-kecil">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  // ── Form logbook dinamis ──────────────────────────
  const wadah   = document.getElementById('baris-logbook');
  const tambah  = document.getElementById('tambah-baris');
  const formLb  = document.getElementById('form-logbook');
  const pesanF  = document.getElementById('pesan-form');

  function barisBaru(tanggal, jam, isi) {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td >' +
        '<div class="flex flex-col gap-2">' +
          '<input type="date" name="tanggal[]" class="shrink-0" required>' +
          '<input type="time" name="jam[]" class="shrink-0" required>' +
        '</div>' +
      '</td>' +
      '<td class="px-2"><textarea name="isi[]" rows="4" placeholder="Isi aktivitas" class="mt-2 w-full" required></textarea></td>' +
      '<td >' +
        '<button type="button" class="btn btn-bahaya btn-besar" aria-label="Hapus baris">{!! ikon('hapus', 14) !!}</button>' +
      '</td>';
    const tgl = tr.querySelector('input[name="tanggal[]"]');
    const jamI = tr.querySelector('input[name="jam[]"]');
    const isiI = tr.querySelector('textarea[name="isi[]"]');
    if (tanggal) tgl.value = tanggal;
    if (jam) jamI.value = jam;
    if (isi) isiI.value = isi;
    tr.querySelector('button').addEventListener('click', function () { tr.remove(); });
    return tr;
  }

  wadah.appendChild(barisBaru());
  tambah.addEventListener('click', function () {
    wadah.appendChild(barisBaru());
    wadah.lastElementChild.querySelector('textarea[name="isi[]"]').focus();
  });

  // ── Tab Data / Input ─────────────────────────────
  const panelData = document.getElementById('panel-data');
  const panelInput = document.getElementById('panel-input');
  const tabData = document.getElementById('tab-data');
  const tabInput = document.getElementById('tab-input');
  const infoD = document.getElementById('data-info');
  const tbodyD = document.getElementById('data-logbook');
  const cariData = document.getElementById('cari-data');
  const fBulan = document.getElementById('filter-bulan');
  const fTahun = document.getElementById('filter-tahun');
  const pagD = document.getElementById('paginasi-data');

  let dHal = 1;
  let dTotalHal = 1;
  let barisData = {};

  (function isiTahun() {
    const kini = new Date().getFullYear();
    for (let y = kini + 1; y >= kini - 5; y--) {
      const o = document.createElement('option');
      o.value = String(y);
      o.textContent = String(y);
      fTahun.appendChild(o);
    }
    fTahun.value = String(kini);
    fBulan.value = String(new Date().getMonth() + 1);
  })();

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function setTab(nama) {
    const dataAktif = nama === 'data';
    panelData.classList.toggle('hidden', !dataAktif);
    panelInput.classList.toggle('hidden', dataAktif);
    tabData.classList.toggle('bg-white', dataAktif);
    tabData.classList.toggle('text-slate-900', dataAktif);
    tabData.classList.toggle('shadow-sm', dataAktif);
    tabData.classList.toggle('text-slate-500', !dataAktif);
    tabInput.classList.toggle('bg-white', !dataAktif);
    tabInput.classList.toggle('text-slate-900', !dataAktif);
    tabInput.classList.toggle('shadow-sm', !dataAktif);
    tabInput.classList.toggle('text-slate-500', dataAktif);
    if (dataAktif) muatData();
  }

  tabData.addEventListener('click', function () { setTab('data'); });
  tabInput.addEventListener('click', function () { setTab('input'); });

  function muatData() {
    infoD.textContent = 'Memuat data logbook…';
    tbodyD.innerHTML = '';
    pagD.innerHTML = '';
    barisData = {};
    pilihSemua.checked = false;
    perbaruiHapusTerpilih();

    const params = new URLSearchParams();
    if (cariData.value.trim()) params.set('q', cariData.value.trim());
    if (fBulan.value) params.set('bulan', fBulan.value);
    if (fTahun.value) params.set('tahun', fTahun.value);
    params.set('hal', String(dHal));

    fetch('{{ route("logbook.data") }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          infoD.textContent = h.pesan || 'Gagal memuat data.';
          return;
        }
        dTotalHal = h.totalHal || 1;
        infoD.innerHTML = '<strong>' + h.total + '</strong> entri logbook'
                        + ' · hal. ' + h.halaman + ' / ' + dTotalHal;
        if (! h.total) {
          tbodyD.innerHTML = '<tr><td colspan="4" class="px-2 py-6 text-center teks-redup">Belum ada data logbook.</td></tr>';
          return;
        }
        h.data.forEach(function (r) {
          barisData[r.id] = r;
          const tr = document.createElement('tr');
          tr.className = 'border-b border-slate-100';
          tr.innerHTML =
            '<td class="px-2 py-2 align-top"><input type="checkbox" class="pilih-logbook" data-id="' + r.id + '"' + (r.is_verified ? ' disabled title="Sudah diverifikasi"' : '') + '></td>' +
            '<td class="px-2 py-2 align-top whitespace-nowrap">' + esc(r.tanggal) + '</td>' +
            '<td class="px-2 py-2 align-top">' + esc(r.jam) + '</td>' +
            '<td class="px-2 py-2">' + esc(r.isi).replace(/\n/g, '<br>') + '</td>' +
            '<td class="px-2 py-2 align-top">' +
              (r.is_verified
                ? '<span class="badge badge-hijau">Terverifikasi</span>'
                : '<span class="badge badge-amber">Belum diverifikasi</span>') +
              (r.is_verified && r.verified_at
                ? '<br><span class="teks-redup teks-kecil">' + esc(r.verified_at) + '</span>' : '') +
            '</td>' +
            '<td class="px-2 py-2 text-center align-top">' +
              (! r.is_verified
                ? '<button type="button" class="btn btn-garis btn-kecil tombol-edit-logbook" data-id="' + r.id + '" title="Edit entri ini">Edit</button>'
                : '') +
            '</td>';
          tbodyD.appendChild(tr);
        });
        renderPaginasi();
      })
      .catch(function () {
        infoD.textContent = 'Terjadi kesalahan jaringan.';
      });
  }

  function renderPaginasi() {
    if (dTotalHal <= 1) return;
    const sebelum = document.createElement('button');
    sebelum.type = 'button';
    sebelum.className = 'btn btn-garis btn-kecil';
    sebelum.textContent = '« Sebelumnya';
    sebelum.disabled = dHal <= 1;
    sebelum.addEventListener('click', function () { dHal--; muatData(); });

    const sesudah = document.createElement('button');
    sesudah.type = 'button';
    sesudah.className = 'btn btn-garis btn-kecil';
    sesudah.textContent = 'Berikutnya »';
    sesudah.disabled = dHal >= dTotalHal;
    sesudah.addEventListener('click', function () { dHal++; muatData(); });

    pagD.appendChild(sebelum);
    pagD.appendChild(sesudah);
  }

  function terapkanFilterData() { dHal = 1; muatData(); }
  cariData.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') terapkanFilterData();
  });
  fBulan.addEventListener('change', terapkanFilterData);
  fTahun.addEventListener('change', terapkanFilterData);

  // ── Pilih banyak + hapus massal ──────────────────
  const pilihSemua = document.getElementById('pilih-semua');
  const hapusTerpilihB = document.getElementById('hapus-terpilih');

  function perbaruiHapusTerpilih() {
    const jumlah = tbodyD.querySelectorAll('.pilih-logbook:checked').length;
    hapusTerpilihB.disabled = jumlah === 0;
    hapusTerpilihB.textContent = jumlah ? 'Hapus Terpilih (' + jumlah + ')' : 'Hapus Terpilih';
  }

  pilihSemua.addEventListener('change', function () {
    tbodyD.querySelectorAll('.pilih-logbook:not(:disabled)').forEach(function (c) {
      c.checked = pilihSemua.checked;
    });
    perbaruiHapusTerpilih();
  });

  tbodyD.addEventListener('change', function (e) {
    if (e.target.classList.contains('pilih-logbook')) perbaruiHapusTerpilih();
  });

  function bacaJson(r) {
    return r.json().catch(function () {
      const e = new Error('respons-bukan-json');
      e.status = r.status;
      throw e;
    });
  }
  function pesanKesalahan(err, bawaan) {
    if (err && err.errors) {
      const kunciPertama = Object.keys(err.errors)[0];
      if (kunciPertama && err.errors[kunciPertama][0]) return err.errors[kunciPertama][0];
    }
    if (err && err.pesan) return err.pesan;
    if (err && (err.status === 419 || err.status === 401)) return 'Sesi berakhir. Muat ulang halaman lalu masuk kembali.';
    if (err && err.status === 422) return 'Data tidak valid. Periksa kembali isian.';
    if (err && err.status) return 'Terjadi kesalahan server (HTTP ' + err.status + ').';
    return bawaan;
  }

  hapusTerpilihB.addEventListener('click', function () {
    const ids = Array.from(tbodyD.querySelectorAll('.pilih-logbook:checked')).map(function (c) { return c.dataset.id; });
    if (! ids.length) return;
    if (! confirm('Hapus ' + ids.length + ' entri logbook yang dipilih?')) return;

    const isi = new URLSearchParams();
    ids.forEach(function (id) { isi.append('ids[]', id); });

    hapusTerpilihB.disabled = true;
    fetch('{{ route("logbook.hapus") }}', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf"]').content,
      },
      body: isi,
    })
      .then(async function (r) {
        const h = await bacaJson(r);
        if (! r.ok) { h.status = r.status; throw h; }
        return h;
      })
      .then(function (h) {
        muatData();
      })
      .catch(function (err) {
        alert(pesanKesalahan(err, 'Gagal menghapus entri.'));
        perbaruiHapusTerpilih();
      });
  });

  // ── Edit entri (belum terverifikasi) ─────────────
  const modalEdit = document.getElementById('modal-edit');
  const formEdit = document.getElementById('form-edit');
  const editTutup = document.getElementById('edit-tutup');
  const editBatal = document.getElementById('edit-batal');
  const editPesan = document.getElementById('edit-pesan');
  const eTanggal = document.getElementById('edit-tanggal');
  const eJam = document.getElementById('edit-jam');
  const eIsi = document.getElementById('edit-isi');
  let editId = null;

  function bukaEdit(id) {
    const r = barisData[id];
    if (! r || r.is_verified) return;
    editId = id;
    eTanggal.value = r.tanggal;
    eJam.value = r.jam;
    eIsi.value = r.isi;
    editPesan.textContent = '';
    modalEdit.classList.remove('hidden');
    modalEdit.classList.add('flex');
    setTimeout(function () { eIsi.focus(); }, 50);
  }

  function tutupEdit() {
    modalEdit.classList.add('hidden');
    modalEdit.classList.remove('flex');
    editId = null;
  }

  tbodyD.addEventListener('click', function (e) {
    const tombol = e.target.closest('.tombol-edit-logbook');
    if (tombol) bukaEdit(Number(tombol.dataset.id));
  });

  editTutup.addEventListener('click', tutupEdit);
  editBatal.addEventListener('click', tutupEdit);
  modalEdit.addEventListener('click', function (e) {
    if (e.target === modalEdit) tutupEdit();
  });

  formEdit.addEventListener('submit', function (e) {
    e.preventDefault();
    if (editId == null) return;

    if (! eTanggal.value || ! eJam.value || ! eIsi.value.trim()) {
      editPesan.textContent = 'Lengkapi tanggal, jam, dan isi.';
      editPesan.classList.add('text-red-600');
      return;
    }

    const kirim = formEdit.querySelector('[type="submit"]');
    kirim.disabled = true;
    editPesan.classList.remove('text-red-600');
    editPesan.textContent = 'Menyimpan…';

    fetch(formEdit.action, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf"]').content,
      },
      body: new URLSearchParams({
        id: String(editId),
        tanggal: eTanggal.value,
        jam: eJam.value,
        isi: eIsi.value,
      }),
    })
      .then(async function (r) {
        const h = await bacaJson(r);
        if (! r.ok) { h.status = r.status; throw h; }
        return h;
      })
      .then(function (h) {
        tutupEdit();
        muatData();
      })
      .catch(function (err) {
        editPesan.textContent = pesanKesalahan(err, 'Gagal menyimpan perubahan.');
        editPesan.classList.add('text-red-600');
      })
      .finally(function () {
        kirim.disabled = false;
      });
  });

  // ── Simpan (async) ───────────────────────────────
  const simpanB = document.getElementById('simpan-logbook');

  formLb.addEventListener('submit', function (e) {
    e.preventDefault();

    const adaKosong = Array.from(wadah.querySelectorAll('input[name="tanggal[]"], input[name="jam[]"], textarea[name="isi[]"]'))
      .some(function (el) { return el.required && el.value.trim() === ''; });
    if (adaKosong) {
      pesanF.textContent = 'Lengkapi tanggal, jam, dan isi pada setiap baris.';
      pesanF.classList.add('text-red-600');
      return;
    }

    simpanB.disabled = true;
    pesanF.classList.remove('text-red-600');
    pesanF.textContent = 'Menyimpan…';

    fetch(formLb.action, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf"]').content,
      },
      body: new FormData(formLb),
    })
      .then(async function (r) {
        const h = await bacaJson(r);
        if (! r.ok) { h.status = r.status; throw h; }
        return h;
      })
      .then(function (h) {
        pesanF.textContent = '';
        wadah.innerHTML = '';
        wadah.appendChild(barisBaru());
        setTab('data');
      })
      .catch(function (err) {
        pesanF.textContent = pesanKesalahan(err, 'Gagal menyimpan logbook.');
        pesanF.classList.add('text-red-600');
      })
      .finally(function () {
        simpanB.disabled = false;
      });
  });

  // ── Cetak logbook (kop surat, seperti admin/rekap_logbook) ──
  const cetakB = document.getElementById('cetak-logbook');
  const bulanNamaCetak = ['Januari','Februari','Maret','April','Mei','Juni',
                          'Juli','Agustus','September','Oktober','November','Desember'];

  function tanggalIndoCetak(tgl) {
    const p = tgl.split('-');
    return p[2] + ' ' + bulanNamaCetak[parseInt(p[1], 10) - 1] + ' ' + p[0];
  }

  function cetakHalaman(d, bulan, tahun) {
    let baris = '';
    Object.keys(d.data).forEach(function (tgl) {
      baris += '<tr class="baris-tanggal"><td colspan="2">' + tanggalIndoCetak(tgl) + '</td></tr>';
      d.data[tgl].forEach(function (e) {
        baris += '<tr>'
               + '<td class="kolom-jam">' + esc(e.jam) + '</td>'
               + '<td>' + esc(e.isi).replace(/\n/g, '<br>') + '</td>'
               + '</tr>';
      });
    });

    const periode = bulanNamaCetak[bulan - 1] + ' ' + tahun;
    const kini = new Date();
    const dicetak = ('0' + kini.getDate()).slice(-2) + ' ' + bulanNamaCetak[kini.getMonth()] + ' ' + kini.getFullYear();

    const html = '<!doctype html><html><head><meta charset="utf-8"><title>Logbook Pegawai — '
      + esc(d.nama) + '</title><style>'
      + 'body{font-family:Georgia,"Times New Roman",serif;font-size:12pt;color:#000;margin:0}'
      + '.kop{display:flex;align-items:center;gap:14px;border-bottom:3px double #000;padding-bottom:10px}'
      + '.kop img{height:64px;width:64px;object-fit:contain}'
      + '.kop .teks{flex:1;text-align:center;line-height:1.35}'
      + '.kop .l1{font-size:13pt;font-weight:bold;text-transform:uppercase}'
      + '.kop .l2{font-size:16pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px}'
      + '.kop .l3{font-size:9pt}'
      + '.judul{text-align:center;font-weight:bold;text-transform:uppercase;text-decoration:underline;margin:18px 0 2px;font-size:13pt}'
      + '.subjudul{text-align:center;margin-bottom:14px;font-size:10pt}'
      + '.identitas{width:auto;margin:0 auto 14px;font-size:11pt;border-collapse:collapse}'
      + '.identitas td{padding:1px 6px 1px 0;vertical-align:top}'
      + 'table.data{width:100%;border-collapse:collapse;font-size:11pt}'
      + 'table.data th,table.data td{border:1px solid #000;padding:5px 8px;text-align:left;vertical-align:top}'
      + 'table.data th{background:#e8eef4;text-transform:uppercase;font-size:10pt}'
      + 'tr.baris-tanggal td{background:#f2f2f2;font-weight:bold}'
      + 'td.kolom-jam{white-space:nowrap;width:60px}'
      + '.footer{margin-top:16px;font-size:9pt;color:#444;display:flex;justify-content:space-between}'
      + '@page{size:A4;margin:15mm}'
      + '</style></head><body>'
      + '<div class="kop"><img src="{{ asset("assets/img/logo.svg") }}">'
      + '<div class="teks">'
      + '<div class="l1">Pemerintah Kabupaten Merauke</div>'
      + '<div class="l2">{{ strtoupper($namaInstansi) }}</div>'
      + '<div class="l3">Jalan Raya Merauke–Kurik, Kabupaten Merauke, Papua Selatan</div>'
      + '</div><div style="width:64px"></div></div>'
      + '<div class="judul">Logbook Pegawai</div>'
      + '<div class="subjudul">Periode ' + periode + '</div>'
      + '<table class="identitas">'
      + '<tr><td>Nama Pegawai</td><td>:</td><td><strong>' + esc(d.nama) + '</strong></td></tr>'
      + '<tr><td>Unit / Bidang</td><td>:</td><td>' + esc(d.unit) + '</td></tr>'
      + '<tr><td>Jumlah Hari Kerja</td><td>:</td><td>' + d.total_hari + ' hari</td></tr>'
      + '<tr><td>Jumlah Entri</td><td>:</td><td>' + d.total_entri + ' entri</td></tr>'
      + '</table>'
      + '<table class="data"><thead><tr><th style="width:60px">Jam</th><th>Isi Aktivitas</th></tr></thead>'
      + '<tbody>' + baris + '</tbody></table>'
      + '<div class="footer"><span>Dicetak pada ' + dicetak + '</span>'
      + '<span>Sistem Absensi {{ strtolower($namaInstansi) }}</span></div>'
      + '</body></html>';

    const f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(f);
    const doc = f.contentDocument;
    doc.open();
    doc.write(html);
    doc.close();
    setTimeout(function () {
      f.contentWindow.focus();
      f.contentWindow.print();
      setTimeout(function () { f.remove(); }, 1000);
    }, 300);
  }

  cetakB.addEventListener('click', function () {
    const bulan = fBulan.value || String(new Date().getMonth() + 1);
    const tahun = fTahun.value || String(new Date().getFullYear());

    cetakB.disabled = true;
    fetch('{{ route("logbook.cetak-data") }}?bulan=' + encodeURIComponent(bulan) + '&tahun=' + encodeURIComponent(tahun), {
      headers: { 'Accept': 'application/json' },
    })
      .then(async function (r) {
        const h = await bacaJson(r);
        if (! r.ok) { h.status = r.status; throw h; }
        return h;
      })
      .then(function (h) {
        cetakB.disabled = false;
        if (! h.sukses) { alert(h.pesan || 'Gagal memuat data cetak.'); return; }
        if (! h.total_entri) { alert('Tidak ada entri logbook pada periode ini.'); return; }
        cetakHalaman(h, parseInt(bulan, 10), tahun);
      })
      .catch(function (err) {
        cetakB.disabled = false;
        alert(pesanKesalahan(err, 'Gagal memuat data cetak.'));
      });
  });

  setTab('data');

  // ── Reset form logbook ───────────────────────────
  document.getElementById('reset-logbook').addEventListener('click', function () {
    const adaIsi = Array.from(wadah.querySelectorAll('input[name="tanggal[]"], input[name="jam[]"], textarea[name="isi[]"]'))
      .some(function (el) { return el.value.trim() !== ''; });
    if (adaIsi && ! confirm('Kosongkan semua baris logbook? Data yang sudah diisi akan hilang.')) return;
    wadah.innerHTML = '';
    wadah.appendChild(barisBaru());
    pesanF.textContent = '';
  });

  // ── Modal Ambil dari SIMRS (akun sendiri) ────────
  const modal     = document.getElementById('modal-simrs');
  const dari      = document.getElementById('modal-dari');
  const sampai    = document.getElementById('modal-sampai');
  const previewB  = document.getElementById('tombol-preview');
  const info      = document.getElementById('modal-info');
  const preview   = document.getElementById('modal-preview');
  const terapkan  = document.getElementById('modal-terapkan');

  let dataSimrs = [];

  const awalBulan = new Date();
  awalBulan.setDate(1);
  dari.value   = awalBulan.toISOString().slice(0, 10);
  sampai.value = new Date().toISOString().slice(0, 10);

  function buka() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function tutupModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  document.getElementById('tombol-ambil-simrs').addEventListener('click', buka);
  document.getElementById('modal-tutup').addEventListener('click', tutupModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });

  function resetPreview() {
    dataSimrs = [];
    preview.innerHTML = '';
    info.innerHTML = '';
    terapkan.disabled = true;
  }

  previewB.addEventListener('click', function () {
    if (! dari.value || ! sampai.value) {
      info.innerHTML = '<span class="text-red-600">Tanggal awal dan akhir wajib diisi.</span>';
      return;
    }
    if (sampai.value < dari.value) {
      info.innerHTML = '<span class="text-red-600">Tanggal akhir tidak boleh sebelum tanggal awal.</span>';
      return;
    }

    previewB.disabled = true;
    resetPreview();
    info.innerHTML = '<span class="teks-redup">Mengambil data tindakan &amp; lab dari SIMRS&hellip;</span>';

    const params = new URLSearchParams();
    params.set('dari', dari.value);
    params.set('sampai', sampai.value);

    fetch('{{ route("logbook.simrs") }}?' + params.toString(), { headers: { 'Accept': 'application/json' } })
      .then(async function (r) {
        const h = await r.json();
        if (! r.ok) throw h;
        return h;
      })
      .then(function (h) {
        if (! h.sukses) {
          info.innerHTML = '<span class="text-red-600">' + esc(h.pesan || 'Gagal mengambil data.') + '</span>';
          return;
        }

        dataSimrs = h.data || [];
        let teks = '<strong>' + (h.total_tindakan || 0) + '</strong> tindakan + <strong>'
                 + (h.total_lab || 0) + '</strong> pemeriksaan lab ditemukan';
        (h.peringatan || []).forEach(function (p) {
          teks += '<br><span class="text-amber-600">' + esc(p) + '</span>';
        });
        info.innerHTML = teks;

        dataSimrs.forEach(function (r) {
          const li = document.createElement('li');
          li.textContent = r.tanggal + ' - ' + r.jam + ' — ' + r.isi;
          preview.appendChild(li);
        });
        if (dataSimrs.length) terapkan.disabled = false;
        else info.innerHTML += '<br><span class="teks-redup">Tidak ada data untuk diterapkan.</span>';
      })
      .catch(function (err) {
        info.innerHTML = '<span class="text-red-600">'
          + esc((err && err.pesan) || 'Terjadi kesalahan jaringan.') + '</span>';
      })
      .finally(function () {
        previewB.disabled = false;
      });
  });

  terapkan.addEventListener('click', function () {
    if (! wadah.children.length) wadah.appendChild(barisBaru());
    const kosongPertama = wadah.children[0];
    const tglKosong = ! kosongPertama.querySelector('input[name="tanggal[]"]').value
                   && ! kosongPertama.querySelector('input[name="jam[]"]').value
                    && ! kosongPertama.querySelector('textarea[name="isi[]"]').value;

    dataSimrs.forEach(function (r, i) {
      if (i === 0 && tglKosong) {
        // gunakan baris kosong pertama
        kosongPertama.querySelector('input[name="tanggal[]"]').value = r.tanggal;
        kosongPertama.querySelector('input[name="jam[]"]').value = r.jam;
        kosongPertama.querySelector('textarea[name="isi[]"]').value = r.isi;
      } else {
        wadah.appendChild(barisBaru(r.tanggal, r.jam, r.isi));
      }
    });

    pesanF.textContent = '';
    tutupModal();
  });

  // ── Modal Template Logbook ───────────────────────
  const modalT    = document.getElementById('modal-template');
  const terapkanT = document.getElementById('template-terapkan');
  const hapusT    = document.getElementById('template-hapus');
  const hapusId   = document.getElementById('hapus-template-id');
  const bukaTambah= document.getElementById('template-buka-tambah');
  const formTambah= document.getElementById('form-tambah-template');

  function pilihanTemplate() {
    return document.querySelector('input[name="pilih-template"]:checked');
  }

  function perbaruiTombolTemplate() {
    const p = pilihanTemplate();
    terapkanT.disabled = !p;
    hapusT.disabled = !p || p.dataset.milik !== '1';
  }

  function tutupModalT() {
    modalT.classList.add('hidden');
    modalT.classList.remove('flex');
    formTambah.classList.add('hidden');
  }

  document.getElementById('tombol-template').addEventListener('click', function () {
    perbaruiTombolTemplate();
    modalT.classList.remove('hidden');
    modalT.classList.add('flex');
  });
  document.getElementById('template-tutup').addEventListener('click', tutupModalT);
  modalT.addEventListener('click', function (e) { if (e.target === modalT) tutupModalT(); });

  document.querySelectorAll('input[name="pilih-template"]').forEach(function (r) {
    r.addEventListener('change', perbaruiTombolTemplate);
  });

  bukaTambah.addEventListener('click', function () {
    formTambah.classList.toggle('hidden');
    if (! formTambah.classList.contains('hidden')) formTambah.querySelector('textarea').focus();
  });
  document.getElementById('template-tambah-batal').addEventListener('click', function () {
    formTambah.classList.add('hidden');
  });

  hapusT.closest('form').addEventListener('submit', function (e) {
    const p = pilihanTemplate();
    if (! p || p.dataset.milik !== '1') { e.preventDefault(); return; }
    if (! confirm('Hapus template ini?')) { e.preventDefault(); return; }
    hapusId.value = p.value;
  });

  terapkanT.addEventListener('click', function () {
    const p = pilihanTemplate();
    if (! p) return;
    const hariIni = new Date().toISOString().slice(0, 10);
    wadah.appendChild(barisBaru(hariIni, '', p.dataset.isi));
    wadah.lastElementChild.querySelector('input[name="jam[]"]').focus();
    pesanF.textContent = '';
    tutupModalT();
  });
})();
</script>
@endsection
