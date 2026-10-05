function pasangAjaxTabel(konteks) {
  const panel = konteks.querySelector('[data-pesan]');
  const tabelWadah = konteks.querySelector('[data-tabel]');
  const cari = konteks.querySelector('[data-cari]');
  const urlData = konteks.dataset.urlData;
  const urlAksi = konteks.dataset.urlAksi || '';

  const state = { status: konteks.dataset.statusAktif || '', q: (cari.value || '').trim(), hal: 1, antre: 0 };

  function urlDataNow() {
    const url = new URL(urlData, window.location.origin);
    const params = new URLSearchParams();
    params.set('hal', String(state.hal));
    if (state.status) params.set('status', state.status);
    if (state.q) params.set('q', state.q);
    konteks.querySelectorAll('[data-kategori]').forEach((el) => {
      if (el.value) params.set(el.dataset.kategori, el.value);
    });
    url.search = params.toString();

    return url;
  }

  function sinkronkanKontrol(data) {
    if (typeof data.q === 'string' && cari.value !== data.q) {
      cari.value = data.q;
    }
    if (typeof data.status === 'string' && data.status) {
      state.status = data.status;
      konteks.dataset.statusAktif = data.status;
    }
    if (typeof data.hal === 'number' && data.hal > 0) {
      state.hal = data.hal;
    }
    if (data.jumlah && typeof data.jumlah === 'object') {
      konteks.querySelectorAll('[data-hitung]').forEach((el) => {
        const nilai = data.jumlah[el.dataset.hitung];
        if (nilai !== undefined) el.textContent = String(nilai);
      });
    }
    konteks.querySelectorAll('[data-status]').forEach((el) => {
      el.classList.toggle('aktif', el.dataset.status === state.status);
    });
  }

  function tampilkanPesan(pesan, sukses) {
    if (!panel) return;
    panel.textContent = pesan || '';
    panel.classList.toggle('hidden', !pesan);
    panel.classList.toggle('bg-emerald-50', Boolean(pesan) && sukses);
    panel.classList.toggle('text-emerald-700', Boolean(pesan) && sukses);
    panel.classList.toggle('bg-rose-50', Boolean(pesan) && !sukses);
    panel.classList.toggle('text-rose-700', Boolean(pesan) && !sukses);
  }

  async function muat() {
    const tiket = (state.antre += 1);
    try {
      const respons = await window.fetch(urlDataNow(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const data = await respons.json();
      if (tiket !== state.antre) return;
      if (typeof data.html === 'string' && tabelWadah) tabelWadah.innerHTML = data.html;
      sinkronkanKontrol(data);
      if (data.pesan) tampilkanPesan(data.pesan, Boolean(data.sukses));
    } catch (e) {
      if (tiket === state.antre) tampilkanPesan('Gagal memuat data. Periksa koneksi lalu coba lagi.', false);
    }
  }

  function keStatus(baru) {
    if (baru === state.status && state.hal === 1) return;
    state.status = baru;
    state.hal = 1;
    muat();
  }

  function keHal(hal) {
    const nomor = parseInt(hal, 10);
    if (! nomor || nomor === state.hal) return;
    state.hal = nomor;
    muat();
    const tabel = konteks.querySelector('[data-tabel]');
    if (tabel && typeof tabel.scrollIntoView === 'function') {
      tabel.scrollIntoView({ block: 'nearest' });
    }
  }

  let jeda = null;
  cari.addEventListener('input', function () {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(function () {
      state.q = (cari.value || '').trim();
      state.hal = 1;
      muat();
    }, 350);
  });

  konteks.querySelectorAll('[data-kategori]').forEach((el) => {
    el.addEventListener('change', function () {
      state.hal = 1;
      muat();
    });
  });

  konteks.addEventListener('click', function (e) {
    const chip = e.target.closest('[data-status]');
    if (chip && chip !== konteks && konteks.contains(chip)) {
      e.preventDefault();
      keStatus(chip.dataset.status);
      return;
    }
    const halaman = e.target.closest('[data-hal]');
    if (halaman && konteks.contains(halaman)) {
      e.preventDefault();
      keHal(halaman.dataset.hal);
    }
  });

  konteks.addEventListener('submit', async function (e) {
    const form = e.target.closest('[data-aksi-form]');
    if (!form || !konteks.contains(form) || !urlAksi) return;
    e.preventDefault();

    const tombol = e.submitter;
    const sumber = tombol && tombol.dataset && tombol.dataset.konfirmasi ? tombol : form;
    if (sumber.dataset.konfirmasi && !window.confirm(sumber.dataset.konfirmasi)) return;

    const data = new FormData(form);
    if (tombol && tombol.dataset && tombol.dataset.putusan) {
      data.set('putusan', tombol.dataset.putusan);
    }
    if (state.q) data.set('q', state.q);
    if (state.status) data.set('status', state.status);
    data.set('hal', String(state.hal));
    konteks.querySelectorAll('[data-kategori]').forEach((el) => {
      if (el.value) data.set(el.dataset.kategori, el.value);
    });

    const tiket = (state.antre += 1);
    if (tombol) tombol.disabled = true;
    try {
      const respons = await window.fetch(urlAksi, {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const hasil = await respons.json();
      if (tiket !== state.antre) return;
      if (typeof hasil.html === 'string' && tabelWadah) tabelWadah.innerHTML = hasil.html;
      sinkronkanKontrol(hasil);
      tampilkanPesan(hasil.pesan || (respons.ok ? 'Selesai.' : 'Aksi gagal.'), respons.ok);
      if (respons.ok && form.dataset.reset !== undefined) form.reset();
      if (form.dataset.tutup) {
        const tombolTutup = document.querySelector(form.dataset.tutup);
        if (tombolTutup) tombolTutup.click();
      }
    } catch (e) {
      if (tiket === state.antre) tampilkanPesan('Gagal memproses. Periksa koneksi lalu coba lagi.', false);
    } finally {
      if (tombol) tombol.disabled = false;
    }
  });
}

export function initAjaxTabel() {
  document.querySelectorAll('[data-ajax-tabel]').forEach((konteks) => {
    if (konteks._ajaxPasang) return;
    konteks._ajaxPasang = true;
    pasangAjaxTabel(konteks);
  });
}

document.addEventListener('DOMContentLoaded', initAjaxTabel);
initAjaxTabel();