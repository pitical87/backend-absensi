@extends('layouts.admin')

@section('content')
<section class="kartu" id="template-jadwal"
         data-url-data="{{ route('admin.jadwal.template-mingguan.data') }}"
         data-url-simpan="{{ route('admin.jadwal.template-mingguan') }}"
         data-url-hapus="{{ route('admin.jadwal.template-mingguan.hapus') }}">
  <div class="kartu-kepala flex items-center justify-between flex-wrap gap-2">
    <div>
      <h2>{!! ikon('kalender') !!} Template Jadwal Mingguan</h2>
      <span class="teks-redup teks-kecil">Berlaku terus untuk sub unit terpilih</span>
    </div>
    <button type="button" class="btn btn-primer btn-kecil" id="template-tambah">
      {!! ikon('tambah', 15) !!} Tambah Template
    </button>
  </div>

  <div id="template-pesan" class="hidden rounded-md px-3 py-2 teks-kecil mb-3" role="status" aria-live="polite"></div>
  <p class="teks-redup teks-kecil px-3">
    Hari yang dikosongkan berarti libur. Hari Libur tidak memakai template, dan jadwal yang diatur langsung
    per tanggal tetap menjadi prioritas.
  </p>

  <div class="tabel-bungkus overflow-x-auto" id="template-tabel" aria-busy="true">
    <p class="teks-redup tengah py-6">Memuat template jadwal…</p>
  </div>

  <div class="modal-tirai" id="modal-template-jadwal" aria-hidden="true">
    <div class="modal-kamera">
      <header class="flex items-center justify-between">
        <span class="flex items-center text-black gap-2">{!! ikon('kalender', 19) !!}
          <span id="template-modal-judul">Tambah Template Jadwal</span>
        </span>
        <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal>&times;</button>
      </header>
      <div class="isi">
        <form id="form-template-jadwal" method="post" action="{{ route('admin.jadwal.template-mingguan') }}">
          @csrf
          <div class="form-grup">
            <label for="template-sub-unit-cari" class="wajib">Sub Unit</label>
            <input type="hidden" name="sub_unit_id" id="template-sub-unit-id">
            <div class="relative" id="template-sub-unit-combo">
              <input type="text" id="template-sub-unit-cari" autocomplete="off" required
                     placeholder="Cari nama unit atau sub unit…" role="combobox"
                     aria-autocomplete="list" aria-expanded="false" aria-controls="template-sub-unit-daftar">
              <ul id="template-sub-unit-daftar"
                  class="pilih-pegawai-daftar hidden absolute z-20 left-0 right-0 bg-white shadow-lg"
                  role="listbox"></ul>
            </div>
          </div>
          <div id="template-form-error" class="hidden rounded-md bg-rose-50 text-rose-700 px-3 py-2 teks-kecil mb-3"
               role="alert"></div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @php
              $hariTemplate = [
                1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
                5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
              ];
            @endphp
            @foreach($hariTemplate as $hari => $namaHari)
              <div class="form-grup">
                <label for="template-hari-{{ $hari }}">{{ $namaHari }}</label>
                <select id="template-hari-{{ $hari }}" name="jadwal[{{ $hari }}]">
                  <option value="">Libur / tanpa shift</option>
                  @foreach($shiftList as $s)
                    <option value="{{ (int) $s->id }}">
                      {{ $s->kategori }} ({{ $s->jam_masuk->format('H:i') }}–{{ $s->jam_pulang->format('H:i') }})
                    </option>
                  @endforeach
                </select>
              </div>
            @endforeach
          </div>
          <div class="aksi-baris justify-end mt-4">
            <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal>Batal</button>
            <button type="submit" class="btn btn-primer btn-kecil" id="template-simpan">
              {!! ikon('centang', 15) !!} Simpan Template
            </button>
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
  const konteks = document.getElementById('template-jadwal');
  if (!konteks) return;

  const tabel = document.getElementById('template-tabel');
  const pesan = document.getElementById('template-pesan');
  const modal = document.getElementById('modal-template-jadwal');
  const form = document.getElementById('form-template-jadwal');
  const comboSubUnit = document.getElementById('template-sub-unit-combo');
  const cariSubUnit = document.getElementById('template-sub-unit-cari');
  const daftarSubUnit = document.getElementById('template-sub-unit-daftar');
  const idSubUnit = document.getElementById('template-sub-unit-id');
  const galatForm = document.getElementById('template-form-error');
  const tombolSimpan = document.getElementById('template-simpan');
  const judulModal = document.getElementById('template-modal-judul');
  const hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
  const opsiSubUnit = [
    @foreach($subUnits as $su)
      {
        id: @json((string) $su->id),
        label: @json($su->unit_nama.' — '.$su->nama),
        cari: @json(mb_strtolower($su->unit_nama.' '.$su->nama)),
      },
    @endforeach
  ];
  let templateBySubUnit = new Map();
  let subUnitSorot = -1;

  function filterSubUnit() {
    const query = cariSubUnit.value.trim().toLocaleLowerCase();
    const hasil = opsiSubUnit.filter((option) =>
      option.cari.includes(query) && !templateBySubUnit.has(option.id)
    );
    daftarSubUnit.replaceChildren();
    subUnitSorot = -1;
    if (!hasil.length) {
      const kosong = document.createElement('li');
      kosong.className = 'kosong';
      kosong.textContent = 'Sub unit tidak ditemukan atau sudah memiliki template.';
      daftarSubUnit.append(kosong);
    } else {
      hasil.forEach((option) => {
        const item = document.createElement('li');
        item.textContent = option.label;
        item.dataset.id = option.id;
        item.setAttribute('role', 'option');
        daftarSubUnit.append(item);
      });
    }
    bukaDaftar(true);
  }

  function bukaDaftar(buka) {
    daftarSubUnit.classList.toggle('hidden', !buka);
    cariSubUnit.setAttribute('aria-expanded', buka ? 'true' : 'false');
  }

  function pilihUnit(id) {
    const pilihan = opsiSubUnit.find((option) => option.id === String(id));
    if (!pilihan || templateBySubUnit.has(pilihan.id)) return;
    cariSubUnit.value = pilihan.label;
    idSubUnit.value = pilihan.id;
    bukaDaftar(false);
  }

  function sorotHasil(arah) {
    const hasil = Array.from(daftarSubUnit.querySelectorAll('[role="option"]'));
    if (!hasil.length) return;
    subUnitSorot = (subUnitSorot + arah + hasil.length) % hasil.length;
    hasil.forEach((item, index) => item.classList.toggle('sorot', index === subUnitSorot));
    hasil[subUnitSorot].scrollIntoView({ block: 'nearest' });
  }

  function tampilkanPesan(teks, sukses) {
    pesan.textContent = teks;
    pesan.classList.toggle('hidden', !teks);
    pesan.classList.toggle('bg-emerald-50', Boolean(teks) && sukses);
    pesan.classList.toggle('text-emerald-700', Boolean(teks) && sukses);
    pesan.classList.toggle('bg-rose-50', Boolean(teks) && !sukses);
    pesan.classList.toggle('text-rose-700', Boolean(teks) && !sukses);
  }

  function isiSel(row, isi, kelas) {
    const cell = document.createElement('td');
    if (kelas) cell.className = kelas;
    if (typeof isi === 'string') cell.textContent = isi;
    else cell.append(isi);
    row.append(cell);
    return cell;
  }

  function renderTabel(daftar) {
    templateBySubUnit = new Map(daftar.map((template) => [String(template.sub_unit_id), template]));
    const table = document.createElement('table');
    table.className = 'tabel min-w-[900px]';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    ['Unit Kerja', 'Sub Unit', ...hari, 'Aksi'].forEach((label) => {
      const th = document.createElement('th');
      th.textContent = label;
      headRow.append(th);
    });
    thead.append(headRow);
    table.append(thead);

    const tbody = document.createElement('tbody');
    if (!daftar.length) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 10;
      cell.className = 'teks-redup tengah py-6';
      cell.textContent = 'Belum ada template jadwal yang dibuat.';
      row.append(cell);
      tbody.append(row);
    } else {
      daftar.forEach((template) => {
        const row = document.createElement('tr');
        isiSel(row, template.unit_nama);
        isiSel(row, template.sub_unit_nama);
        hari.forEach((_, index) => {
          const shift = template.jadwal[index + 1];
          const cell = document.createElement('td');
          if (shift) {
            const name = document.createElement('span');
            name.className = 'font-medium';
            name.textContent = shift.nama;
            const time = document.createElement('span');
            time.className = 'block teks-kecil teks-redup';
            time.textContent = shift.jam_masuk + '–' + shift.jam_pulang;
            cell.append(name, time);
          } else {
            const libur = document.createElement('span');
            libur.className = 'teks-redup';
            libur.textContent = 'Libur';
            cell.append(libur);
          }
          row.append(cell);
        });
        const aksi = document.createElement('button');
        aksi.type = 'button';
        aksi.className = 'btn btn-garis btn-kecil';
        aksi.dataset.ubahTemplate = String(template.sub_unit_id);
        aksi.textContent = 'Ubah';
        isiSel(row, aksi);
        const hapus = document.createElement('button');
        hapus.type = 'button';
        hapus.className = 'btn btn-garis btn-kecil text-red-600 ml-1';
        hapus.dataset.hapusTemplate = String(template.sub_unit_id);
        hapus.dataset.namaSubUnit = template.sub_unit_nama;
        hapus.textContent = 'Hapus';
        row.lastElementChild.append(hapus);
        tbody.append(row);
      });
    }

    table.append(tbody);
    tabel.replaceChildren(table);
    tabel.setAttribute('aria-busy', 'false');
  }

  async function muatTabel() {
    tabel.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(konteks.dataset.urlData, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Gagal memuat daftar template jadwal.');
      const result = await response.json();
      renderTabel(result.data);
    } catch (error) {
      tabel.setAttribute('aria-busy', 'false');
      tabel.textContent = '';
      const text = document.createElement('p');
      text.className = 'teks-redup tengah py-6';
      text.textContent = error.message || 'Gagal memuat daftar template jadwal.';
      tabel.append(text);
    }
  }

  function bukaModal(template) {
    form.reset();
    galatForm.textContent = '';
    galatForm.classList.add('hidden');
    cariSubUnit.value = '';
    idSubUnit.value = '';
    cariSubUnit.disabled = Boolean(template);
    if (template) {
      cariSubUnit.value = opsiSubUnit.find((option) => option.id === String(template.sub_unit_id))?.label || '';
      idSubUnit.value = String(template.sub_unit_id);
      bukaDaftar(false);
    }
    judulModal.textContent = template ? 'Ubah Template Jadwal' : 'Tambah Template Jadwal';
    hari.forEach((_, index) => {
      const shift = template && template.jadwal[index + 1];
      document.getElementById('template-hari-' + (index + 1)).value = shift ? String(shift.shift_id) : '';
    });
    modal.classList.add('terbuka');
    modal.setAttribute('aria-hidden', 'false');
    if (template) {
      document.getElementById('template-hari-1').focus();
    } else {
      cariSubUnit.focus();
      filterSubUnit();
    }
  }

  function tutupModal() {
    modal.classList.remove('terbuka');
    modal.setAttribute('aria-hidden', 'true');
    cariSubUnit.disabled = false;
    bukaDaftar(false);
  }

  document.getElementById('template-tambah').addEventListener('click', () => bukaModal(null));
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ubah-template]');
    if (button && konteks.contains(button)) {
      bukaModal(templateBySubUnit.get(button.dataset.ubahTemplate));
      return;
    }
    const hapus = event.target.closest('[data-hapus-template]');
    if (hapus && konteks.contains(hapus)) {
      hapusTemplate(hapus);
    }
  });
  modal.querySelectorAll('[data-tutup-modal]').forEach((button) => button.addEventListener('click', tutupModal));
  modal.addEventListener('click', (event) => { if (event.target === modal) tutupModal(); });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('terbuka')) tutupModal();
  });
  cariSubUnit.addEventListener('focus', filterSubUnit);
  cariSubUnit.addEventListener('input', () => {
    idSubUnit.value = '';
    filterSubUnit();
  });
  cariSubUnit.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      if (daftarSubUnit.classList.contains('hidden')) filterSubUnit();
      sorotHasil(1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      sorotHasil(-1);
    } else if (event.key === 'Enter' && subUnitSorot >= 0) {
      event.preventDefault();
      const hasil = daftarSubUnit.querySelectorAll('[role="option"]');
      if (hasil[subUnitSorot]) pilihUnit(hasil[subUnitSorot].dataset.id);
    } else if (event.key === 'Escape') {
      bukaDaftar(false);
    }
  });
  daftarSubUnit.addEventListener('mousedown', (event) => {
    const item = event.target.closest('[role="option"]');
    if (item) {
      event.preventDefault();
      pilihUnit(item.dataset.id);
    }
  });
  document.addEventListener('click', (event) => {
    if (!comboSubUnit.contains(event.target)) bukaDaftar(false);
  });

  async function hapusTemplate(button) {
    const namaSubUnit = button.dataset.namaSubUnit;
    if (!window.confirm('Hapus template jadwal untuk sub unit ' + namaSubUnit + '?')) return;

    button.disabled = true;
    const data = new FormData();
    data.set('sub_unit_id', button.dataset.hapusTemplate);
    const token = form.querySelector('input[name="_token"]');
    if (token) data.set('_token', token.value);

    try {
      const response = await fetch(konteks.dataset.urlHapus, {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.pesan || result.message || 'Template jadwal gagal dihapus.');

      tampilkanPesan(result.pesan || 'Template jadwal berhasil dihapus.', true);
      await muatTabel();
    } catch (error) {
      tampilkanPesan(error.message || 'Gagal menghapus template jadwal. Periksa koneksi lalu coba lagi.', false);
      button.disabled = false;
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    galatForm.classList.add('hidden');
    if (!idSubUnit.value) {
      galatForm.textContent = 'Pilih sub unit dari daftar hasil pencarian.';
      galatForm.classList.remove('hidden');
      cariSubUnit.focus();
      return;
    }
    tombolSimpan.disabled = true;
    try {
      const response = await fetch(konteks.dataset.urlSimpan, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (!response.ok) {
        const errors = result.errors ? Object.values(result.errors).flat().join(' ') : result.message;
        throw new Error(errors || 'Template jadwal gagal disimpan.');
      }
      tutupModal();
      tampilkanPesan(result.pesan || 'Template jadwal berhasil disimpan.', true);
      await muatTabel();
    } catch (error) {
      galatForm.textContent = error.message || 'Gagal menyimpan template jadwal. Periksa koneksi lalu coba lagi.';
      galatForm.classList.remove('hidden');
    } finally {
      tombolSimpan.disabled = false;
    }
  });

  muatTabel();
})();
</script>
@endsection
