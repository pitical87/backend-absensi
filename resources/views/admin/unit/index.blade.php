@extends('layouts.admin')

@section('content')


{{-- ===== TAB ===== --}}
<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="tab-bar">
  {!! $tabs !!}
</div>

{{-- ===== PESAN ===== --}}
<div id="pesan-unit" class="hidden mb-4 px-4 py-3 rounded-xl text-sm"></div>

{{-- ===== ISI TAB ===== --}}
<div id="isi-tab">
  {!! $isi !!}
</div>

{{-- ===== MODAL TAMBAH UNIT ===== --}}
<div id="modal-tambah" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this) window.unitTutup('modal-tambah')">
  <div class="kartu w-full max-w-md">
    <div class="flex items-start justify-between gap-3 mb-3">
      <h3 class="text-base font-bold text-navy">Tambah Unit Kerja</h3>
      <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal="modal-tambah">×</button>
    </div>

    <form method="post" action="{{ url('admin/unit/aksi') }}" data-ajax="tambah_unit">
      @csrf
      <input type="hidden" name="aksi" value="tambah_unit">
      <label class="teks-kecil mb-1 block">Nama unit kerja</label>
      <input type="text" name="nama" placeholder="Contoh: Rawat Inap" required>
      <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap mt-3">
        <input type="checkbox" name="punya_sub" value="1" class="w-auto"> Memiliki sub unit
      </label>
      <div class="flex justify-end gap-2 mt-5">
        <button type="button" class="btn btn-garis" data-tutup-modal="modal-tambah">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan Unit</button>
      </div>
    </form>
  </div>
</div>

{{-- ===== MODAL UBAH UNIT (isi diambil dari data tombol) ===== --}}
<div id="modal-ubah" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this) window.unitTutup('modal-ubah')">
  <div class="kartu w-full max-w-md">
    <div class="flex items-start justify-between gap-3 mb-3">
      <h3 class="text-base font-bold text-navy">Ubah Unit Kerja</h3>
      <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal="modal-ubah">×</button>
    </div>

    <form method="post" action="{{ url('admin/unit/aksi') }}" data-ajax="ubah_unit">
      @csrf
      <input type="hidden" name="aksi" value="ubah_unit">
      <input type="hidden" name="id" id="ubah-unit-id">
      <input type="hidden" name="tab_asal" id="ubah-unit-tab-asal">
      <label class="teks-kecil mb-1 block">Nama unit kerja</label>
      <input type="text" name="nama" id="ubah-unit-nama" required>
      <label class="teks-kecil mt-3 mb-1 block">Atasan default unit</label>
      @include('admin.unit.pilih_pegawai', [
        'namaField' => 'atasan_id',
        'idSelect' => 'ubah-unit-atasan',
        'daftar' => $pegawaiPilihan,
        'terpilih' => 0,
        'labelKosong' => '— Atasan unit —',
        'judul' => 'Atasan default unit kerja ini',
      ])
      <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap mt-3">
        <input type="checkbox" name="punya_sub" value="1" id="ubah-unit-punya-sub" class="w-auto">
        Memiliki sub unit
      </label>
      <div class="flex justify-end gap-2 mt-5">
        <button type="button" class="btn btn-garis" data-tutup-modal="modal-ubah">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  var tabBar   = document.getElementById('tab-bar');
  var isiTab   = document.getElementById('isi-tab');
  var pesan    = document.getElementById('pesan-unit');
  var csrf     = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  var urlData  = '{{ route('admin.unit.data') }}';
  var urlAksi  = '{{ route('admin.unit.aksi') }}';

  var tab = '{{ $mode === 'unit' && $unitAktif ? (int) $unitAktif->id : $mode }}';
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

  function tabAktifDariTabs() {
    var aktif = tabBar.querySelector('.tab-unit.aktif[data-tab]');
    return aktif ? aktif.getAttribute('data-tab') : null;
  }

  function muatTab(kunci) {
    if (sedangMuat) return;
    sedangMuat = true;
    tampilPesan('', true);

    fetch(urlData + '?' + new URLSearchParams({ tab: kunci }).toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) { tampilPesan('Gagal memuat data.', false); return; }
        tab = kunci;
        tabBar.innerHTML = h.tabs;
        isiTab.innerHTML = h.isi;
      })
      .catch(function () { tampilPesan('Terjadi kesalahan jaringan.', false); })
      .then(function () { sedangMuat = false; });
  }

  window.unitTutup = function (id) {
    var m = document.getElementById(id);
    if (! m) return;
    m.classList.add('hidden');
    m.classList.remove('flex');
  };

  function bukaModal(id) {
    var m = document.getElementById(id);
    if (! m) return;
    m.classList.remove('hidden');
    m.classList.add('flex');
  }

  function isiModalUbah(b) {
    document.getElementById('ubah-unit-id').value = b.getAttribute('data-unit') || '';
    document.getElementById('ubah-unit-nama').value = b.getAttribute('data-nama') || '';
    document.getElementById('ubah-unit-atasan').value = b.getAttribute('data-atasan') || '';
    document.getElementById('ubah-unit-punya-sub').checked = b.getAttribute('data-punya-sub') === '1';
    // Supaya setelah disimpan halaman tetap berada di tab unit itu, bukan
    // lompat ke tab Semua Unit Kerja.
    document.getElementById('ubah-unit-tab-asal').value = b.getAttribute('data-unit') || '';
    bukaModal('modal-ubah');
    unitSinkronPilihan(document.getElementById('ubah-unit-atasan'));
  }

  /* ── Pilih pegawai: combo box cari nama ───────────────────────────────
     <select> asli tetap ada (fallback tanpa JS & sumber nilai form), lalu
     disembunyikan dan digantikan kotak cari + daftar terfilter. Semua
     perekatannya di document supaya ikut bekerja pada markup hasil fetch. */
  // WeakMap: kunci objek tidak boleh berubah jadi string seperti [object HTMLDivElement].
  var cariPegawai = new WeakMap();

  function unitSiapkanPilihan(bungkus) {
    if (! bungkus || ! bungkus.isConnected || bungkus.classList.contains('siap')) return;
    var select = bungkus.querySelector('select');
    var cari = bungkus.querySelector('.pilih-pegawai-cari');
    var daftar = bungkus.querySelector('.pilih-pegawai-daftar');
    if (! select || ! cari || ! daftar) return;

    var isi = [{ nilai: '', label: select.options[0] ? select.options[0].textContent : '', cari: '' }];
    Array.prototype.forEach.call(select.options, function (o, i) {
      if (i === 0 && o.value === '') return;
      isi.push({ nilai: o.value, label: o.textContent.trim(), cari: (o.getAttribute('data-cari') || o.textContent).toLowerCase() });
    });

    bungkus.classList.add('siap');
    cariPegawai.set(bungkus, { select: select, cari: cari, daftar: daftar, isi: isi, sorot: -1 });
    unitTampilPilihan(bungkus);
  }

  function unitTampilPilihan(bungkus) {
    var s = cariPegawai.get(bungkus);
    if (! s) return;
    var terpilih = s.isi.filter(function (o) { return o.nilai === s.select.value; })[0];
    s.cari.value = terpilih ? terpilih.label : '';
  }

  window.unitSinkronPilihan = function (select) {
    if (! select) return;
    unitSiapkanPilihan(select.closest('[data-pilih-pegawai]'));
    unitTampilPilihan(select.closest('[data-pilih-pegawai]'));
  };

  function unitGambarDaftar(bungkus, kata) {
    var s = cariPegawai.get(bungkus);
    if (! s) return;
    var koma = (kata || '').toLowerCase().trim();
    var cocok = s.isi.filter(function (o) { return ! koma || o.cari.indexOf(koma) !== -1; });

    s.daftar.innerHTML = '';
    if (! cocok.length) {
      var kosong = document.createElement('li');
      kosong.className = 'kosong';
      kosong.textContent = 'Nama tidak ditemukan';
      s.daftar.appendChild(kosong);
    } else {
      cocok.forEach(function (o) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.setAttribute('data-nilai', o.nilai);
        li.textContent = o.label;
        s.daftar.appendChild(li);
      });
    }
    s.sorot = -1;
  }

  function unitBukaDaftar(bungkus, kata) {
    var s = cariPegawai.get(bungkus);
    if (! s) return;
    unitGambarDaftar(bungkus, kata);
    s.daftar.classList.remove('hidden');
    s.cari.setAttribute('aria-expanded', 'true');
  }

  function unitTutupDaftar(bungkus) {
    var s = cariPegawai.get(bungkus);
    if (! s) return;
    s.daftar.classList.add('hidden');
    s.cari.setAttribute('aria-expanded', 'false');
    s.sorot = -1;
  }

  function unitSorot(bungkus, arah) {
    var s = cariPegawai.get(bungkus);
    if (! s) return;
    var item = s.daftar.querySelectorAll('li[data-nilai]');
    if (! item.length) return;
    s.sorot = (s.sorot + arah + item.length) % item.length;
    Array.prototype.forEach.call(item, function (li, i) { li.classList.toggle('sorot', i === s.sorot); });
    if (item[s.sorot].scrollIntoView) item[s.sorot].scrollIntoView({ block: 'nearest' });
  }

  document.addEventListener('focusin', function (e) {
    var bungkus = e.target.closest ? e.target.closest('[data-pilih-pegawai]') : null;
    if (! bungkus || ! e.target.classList.contains('pilih-pegawai-cari')) return;
    e.target.select();
    unitBukaDaftar(bungkus, '');
  });

  document.addEventListener('input', function (e) {
    var bungkus = e.target.closest ? e.target.closest('[data-pilih-pegawai]') : null;
    if (! bungkus || ! e.target.classList.contains('pilih-pegawai-cari')) return;
    unitBukaDaftar(bungkus, e.target.value);
  });

  document.addEventListener('keydown', function (e) {
    var bungkus = e.target.closest ? e.target.closest('[data-pilih-pegawai]') : null;
    if (! bungkus || ! e.target.classList.contains('pilih-pegawai-cari')) return;
    var s = cariPegawai.get(bungkus);

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (s.daftar.classList.contains('hidden')) unitBukaDaftar(bungkus, s.cari.value);
      else unitSorot(bungkus, e.key === 'ArrowDown' ? 1 : -1);
    } else if (e.key === 'Enter') {
      var item = s.daftar.querySelectorAll('li[data-nilai]');
      if (item[s.sorot]) { e.preventDefault(); item[s.sorot].dispatchEvent(new MouseEvent('click', { bubbles: true })); }
    } else if (e.key === 'Escape') {
      unitTutupDaftar(bungkus);
    }
  });

  document.addEventListener('click', function (e) {
    var bungkus = e.target.closest ? e.target.closest('[data-pilih-pegawai]') : null;

    if (! bungkus) {
      Array.prototype.forEach.call(document.querySelectorAll('[data-pilih-pegawai]'), unitTutupDaftar);
      return;
    }
    if (! e.target.classList.contains('pilih-pegawai-cari') && ! bungkus.contains(e.target)) return;

    var li = e.target.closest('li[data-nilai]');
    if (li) {
      var s = cariPegawai.get(bungkus);
      s.select.value = li.getAttribute('data-nilai');
      s.select.dispatchEvent(new Event('change', { bubbles: true }));
      unitTampilPilihan(bungkus);
      unitTutupDaftar(bungkus);
      s.cari.blur();
      return;
    }

    if (e.target.classList.contains('pilih-pegawai-cari')) unitBukaDaftar(bungkus, e.target.value);
  });

  function unitSiapkanSemua() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-pilih-pegawai]'), unitSiapkanPilihan);
  }

  if (window.MutationObserver) {
    new MutationObserver(unitSiapkanSemua).observe(document.documentElement, { childList: true, subtree: true });
  }
  unitSiapkanSemua();

  function kirim(form, tambahan) {
    var data = new FormData(form);
    Object.keys(tambahan || {}).forEach(function (k) { data.set(k, tambahan[k]); });

    tampilPesan('Menyimpan…', true);

    return fetch(urlAksi, {
      method: 'POST',
      body: data,
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().then(function (h) { return { ok: r.ok, h: h }; }); })
      .then(function (b) {
        var h = b.h;
        if (h.tabs) { tabBar.innerHTML = h.tabs; isiTab.innerHTML = h.isi; }
        if (h.mode === 'unit') tab = tabAktifDariTabs() || tab;
        else if (h.mode) tab = h.mode;

        tampilPesan(h.pesan, !! h.sukses);

        if (h.sukses && form.dataset.ajax === 'tambah_unit') window.unitTutup('modal-tambah');
        if (h.sukses && form.dataset.ajax === 'ubah_unit') window.unitTutup('modal-ubah');
        if (h.sukses && form.isConnected) { form.reset(); unitSiapkanSemua(); }
      })
      .catch(function () { tampilPesan('Terjadi kesalahan jaringan.', false); });
  }

  // Delegasi: seluruh tombol & form hasil render dinamis tertangkap di sini.
  document.addEventListener('click', function (e) {
    var target = e.target;

    var tabKlik = target.closest('[data-tab]');
    if (tabKlik) {
      e.preventDefault();
      muatTab(tabKlik.getAttribute('data-tab'));
      return;
    }

    var buka = target.closest('[data-buka-modal]');
    if (buka) {
      e.preventDefault();
      if (buka.getAttribute('data-buka-modal') === 'modal-ubah') isiModalUbah(buka);
      else bukaModal(buka.getAttribute('data-buka-modal'));
      return;
    }

    var tutup = target.closest('[data-tutup-modal]');
    if (tutup) {
      e.preventDefault();
      window.unitTutup(tutup.getAttribute('data-tutup-modal'));
      return;
    }

    var aksi = target.closest('button[data-ajax]');
    if (aksi && aksi.getAttribute('data-ajax') !== 'ubah_sub') {
      e.preventDefault();

      var konfirmasi = aksi.getAttribute('data-konfirmasi');
      if (konfirmasi && ! confirm(konfirmasi)) return;

      var form = document.createElement('form');
      form.dataset.ajax = aksi.getAttribute('data-ajax');
      kirim(form, {
        aksi: aksi.getAttribute('data-ajax'),
        id: aksi.getAttribute('data-id') || '',
        nama: aksi.getAttribute('data-nama') || '',
        unit_kerja_id: aksi.getAttribute('data-unit-kerja-id') || '',
        tab_asal: aksi.getAttribute('data-tab-asal') || tab
      });
    }
  });

  // Simpan atasan sub unit: nilai select di sebelah tombol Simpan.
  document.addEventListener('click', function (e) {
    var aksi = e.target.closest('button[data-ajax="ubah_sub"]');
    if (! aksi) return;
    e.preventDefault();

    var select = aksi.closest('td').querySelector('select[name="atasan_id"]');

    var form = document.createElement('form');
    form.dataset.ajax = 'ubah_sub';
    kirim(form, {
      aksi: 'ubah_sub',
      id: aksi.getAttribute('data-id'),
      nama: aksi.getAttribute('data-nama'),
      atasan_id: select ? select.value : '',
      unit_kerja_id: aksi.getAttribute('data-unit-kerja-id')
    });
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    // Semua form di halaman ini AJAX: tambah unit, ubah unit, tambah sub unit.
    if (! form.dataset || ! form.dataset.ajax) return;

    e.preventDefault();
    kirim(form, { unit_kerja_id: form.querySelector('[name="unit_kerja_id"]')?.value || '' });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['modal-tambah', 'modal-ubah'].forEach(function (id) {
      var m = document.getElementById(id);
      if (m && ! m.classList.contains('hidden')) window.unitTutup(id);
    });
  });
})();
</script>
@endsection