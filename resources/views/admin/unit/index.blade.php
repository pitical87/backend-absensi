@extends('layouts.admin')

@section('content')


{{-- ===== TAB ===== --}}
<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="tab-bar">
  {!! $tabs !!}
</div>

{{-- ===== PESAN ===== --}}
<div id="pesan-unit" class="hidden mb-4 px-4 py-3 rounded-xl text-sm"></div>

{{-- ===== ISI TAB ===== --}}
{{-- Seluruh isi tab dirender sekaligus di sini; JS hanya menampilkan/menyembunyikan
     panel sehingga berpindah tab tidak perlu menunggu jaringan. --}}
<div id="isi-tab">
  @foreach($isiSemua as $kunci => $htmlPanel)
    <div class="tab-panel {{ (string) $kunci === (string) $tabAktif ? '' : 'hidden' }}"
         data-panel="{{ $kunci }}">{!! $htmlPanel !!}</div>
  @endforeach
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
          'opsiLengkap' => true,
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

{{-- ===== MODAL KELOLA PEGAWAI SUB UNIT ===== --}}
<div id="modal-pegawai-sub" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this) window.unitTutup('modal-pegawai-sub')">
  <div class="kartu w-full max-w-3xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <h3 class="text-base font-bold text-navy">Pegawai :  <span id="pegawai-sub-nama" class=" teks-primary"></span></h3>

      </div>
      <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal="modal-pegawai-sub">×</button>
    </div>
    <label class="blok mb-3">
      <input type="text" id="pegawai-sub-cari" placeholder="Cari nama atau email…" autocomplete="off">
    </label>
    <div class="tabel-bungkus max-h-[60vh] overflow-auto">
      <table class="tabel min-w-[600px]">
        <thead>
          <tr><th>Nama</th><th>Email</th><th class="w-[170px]">Aksi</th></tr>
        </thead>
        <tbody id="pegawai-sub-daftar">
          <tr><td colspan="3" class="teks-redup">Memuat daftar pegawai…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== MODAL HAPUS SUB UNIT ===== --}}
<div id="modal-hapus-sub" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this) window.unitTutup('modal-hapus-sub')">
  <div class="kartu w-full max-w-3xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-start justify-between gap-3 mb-3">
      <h3 class="text-base font-bold text-navy">Hapus Sub Unit</h3>
      <button type="button" class="btn btn-garis btn-kecil" data-tutup-modal="modal-hapus-sub">×</button>
    </div>
    <p class="teks-kecil mb-3">
      Tentukan tindakan untuk pegawai pada sub unit <strong id="hapus-sub-nama"></strong> sebelum sub unit dihapus.
    </p>
    <form method="post" action="{{ url('admin/unit/aksi') }}" data-ajax="hapus_sub">
      @csrf
      <input type="hidden" name="aksi" value="hapus_sub">
      <input type="hidden" name="id" id="hapus-sub-id">
      <input type="hidden" name="unit_kerja_id" id="hapus-sub-unit-id">
      <fieldset class="border border-garis rounded-lg p-3 mb-4">
        <legend class="teks-kecil font-semibold px-1">Pilih tindakan</legend>
        <div class="flex flex-col sm:flex-row gap-3">
          <label class="flex items-start gap-2 rounded-lg border border-garis p-3 cursor-pointer flex-1">
            <input type="radio" name="mode_hapus_sub" value="pindahkan" class="mt-1 w-auto">
            <span>
              <span class="font-semibold block">Pindahkan pegawai</span>
              <span class="teks-redup teks-kecil">Atur sub unit tujuan untuk setiap pegawai.</span>
            </span>
          </label>
          <label class="flex items-start gap-2 rounded-lg border border-garis p-3 cursor-pointer flex-1">
            <input type="radio" name="mode_hapus_sub" value="nonaktifkan" class="mt-1 w-auto">
            <span>
              <span class="font-semibold block">Nonaktifkan pegawai</span>
              <span class="teks-redup teks-kecil">Pegawai dinonaktifkan dan dilepas dari sub unit ini.</span>
            </span>
          </label>
        </div>
      </fieldset>
      <div id="hapus-sub-tabel-bungkus" class="border border-garis rounded-lg p-3 mb-4 hidden">
        <div class="font-semibold teks-kecil mb-2">Pegawai terdampak (<span id="hapus-sub-jumlah">0</span>)</div>
        <div id="hapus-sub-semua-bungkus" class="flex flex-wrap items-end gap-2 mb-3">
          <div class="flex-1 min-w-[220px]">
            <label class="teks-kecil mb-1 block" for="hapus-sub-semua-tujuan">Pindahkan semua ke</label>
            <select id="hapus-sub-semua-tujuan">
              <option value="">Pilih tujuan untuk semua pegawai</option>
            </select>
          </div>
          <button type="button" class="btn btn-navy btn-kecil" id="hapus-sub-terapkan-semua" disabled>
            Terapkan ke semua
          </button>
        </div>
        <div class="tabel-bungkus max-h-72 overflow-auto">
          <table class="tabel min-w-[640px]">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Email</th>
                <th id="hapus-sub-kolom-tindakan">Pilih unit</th>
              </tr>
            </thead>
            <tbody id="hapus-sub-pegawai">
              <tr><td colspan="3" class="teks-redup">Memuat daftar pegawai…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button type="button" class="btn btn-garis" data-tutup-modal="modal-hapus-sub">Batal</button>
        <button type="submit" class="btn btn-bahaya" id="hapus-sub-submit" disabled>Hapus Sub Unit</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('script')
{{-- Daftar pegawai untuk combo atasan dibagikan sekali di sini; tiap combo
     mengisinya lewat JS agar tidak diduplikasi pada setiap baris. --}}
@php
  $daftarPegawaiJson = json_encode($pegawaiPilihan->map(function ($p) {
      return [
          'id' => (int) $p->id,
          'nama' => $p->nama_lengkap,
          'cari' => mb_strtolower(trim($p->nama_lengkap.' '.($p->nip ?? '').' '.($p->email ?? ''))),
      ];
  })->values()->all(), JSON_UNESCAPED_UNICODE);
@endphp
<script>
window.PEGAWAI_UNIT = {!! $daftarPegawaiJson !!};
</script>
<script>
  (function () {
    var tabBar   = document.getElementById('tab-bar');
    var isiTab   = document.getElementById('isi-tab');
    var pesan    = document.getElementById('pesan-unit');
    var csrf     = (document.querySelector('meta[name="csrf"]') || {}).content || '';
    var urlAksi  = '{{ route('admin.unit.aksi') }}';

    var tab = '{{ $tabAktif }}';
    var subUnitKelolaAktif = null;
    var daftarPegawaiSubAktif = [];

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

    // Semua isi tab sudah ada di halaman, jadi berpindah tab hanya perlu
    // menampilkan/menyembunyikan panel — tidak ada lagi permintaan jaringan.
    function aktifkanTab(kunci) {
      kunci = String(kunci);
      tab = kunci;

      Array.prototype.forEach.call(isiTab.querySelectorAll('.tab-panel'), function (p) {
        p.classList.toggle('hidden', p.getAttribute('data-panel') !== kunci);
      });

      Array.prototype.forEach.call(tabBar.querySelectorAll('.tab-unit[data-tab]'), function (a) {
        a.classList.toggle('aktif', a.getAttribute('data-tab') === kunci);
      });
    }

    // Bangun ulang seluruh panel dari respons server (peta kunci => HTML).
    function renderSemuaPanel(peta) {
      if (! peta) return;
      var html = '';
      Object.keys(peta).forEach(function (k) {
        html += '<div class="tab-panel hidden" data-panel="' + k + '">' + peta[k] + '</div>';
      });
      isiTab.innerHTML = html;
    }

    window.unitTutup = function (id) {
      var m = document.getElementById(id);
      if (! m) return;
      m.classList.add('hidden');
      m.classList.remove('flex');
    };

    function gambarPegawaiSub(pegawai) {
      var tbody = document.getElementById('pegawai-sub-daftar');
      var q = (document.getElementById('pegawai-sub-cari').value || '').trim().toLowerCase();
      var hasil = pegawai.filter(function (p) {
        return !q || (p.nama + ' ' + (p.email || '')).toLowerCase().indexOf(q) !== -1;
      });

      tbody.innerHTML = '';
      if (!hasil.length) {
        var kosong = document.createElement('tr');
        var cell = document.createElement('td');
        cell.colSpan = 3;
        cell.className = 'tengah teks-redup py-4';
        cell.textContent = 'Tidak ada pegawai yang cocok.';
        kosong.appendChild(cell);
        tbody.appendChild(kosong);
        return;
      }

      hasil.forEach(function (p) {
        var baris = document.createElement('tr');
        var nama = document.createElement('td');
        nama.className = 'font-medium';
        nama.textContent = p.nama;
        var email = document.createElement('td');
        email.className = 'teks-kecil';
        email.textContent = p.email || '—';
        var aksi = document.createElement('td');
        if (p.di_sub_unit) {
          var sudah = document.createElement('span');
          sudah.className = 'badge badge-hijau';
          sudah.textContent = 'Sudah di sini';
          aksi.appendChild(sudah);
        } else {
          var pindah = document.createElement('button');
          pindah.type = 'button';
          pindah.className = 'btn btn-primer btn-kecil';
          pindah.textContent = p.unit_kerja_id === Number(subUnitKelolaAktif.unitKerjaId)
            ? 'Pindahkan ke sini'
            : 'Pindahkan dari unit lain';
          pindah.setAttribute('data-pindahkan-ke-sub', '');
          pindah.dataset.userId = p.id;
          pindah.dataset.subUnitId = subUnitKelolaAktif.id;
          pindah.dataset.unitKerjaId = subUnitKelolaAktif.unitKerjaId;
          aksi.appendChild(pindah);
        }
        baris.appendChild(nama);
        baris.appendChild(email);
        baris.appendChild(aksi);
        tbody.appendChild(baris);
      });
    }

    function muatPegawaiSub(subUnitId, nama, unitKerjaId) {
      subUnitKelolaAktif = { id: subUnitId, nama: nama, unitKerjaId: unitKerjaId };
      document.getElementById('pegawai-sub-nama').textContent = nama;
      document.getElementById('pegawai-sub-cari').value = '';
      document.getElementById('pegawai-sub-daftar').innerHTML =
        '<tr><td colspan="3" class="teks-redup">Memuat daftar pegawai…</td></tr>';
      bukaModal('modal-pegawai-sub');

      return fetch('{{ url('admin/unit/sub') }}/' + encodeURIComponent(subUnitId) + '/pegawai/kelola', {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) {
          if (!r.ok) throw new Error('Daftar pegawai tidak dapat dimuat.');
          return r.json();
        })
        .then(function (data) {
          subUnitKelolaAktif.unitKerjaId = data.sub_unit.unit_kerja_id;
          daftarPegawaiSubAktif = data.pegawai || [];
          gambarPegawaiSub(daftarPegawaiSubAktif);
        })
        .catch(function (error) {
          var tbody = document.getElementById('pegawai-sub-daftar');
          tbody.innerHTML = '';
          var row = document.createElement('tr');
          var cell = document.createElement('td');
          cell.colSpan = 3;
          cell.className = 'teks-merah';
          cell.textContent = error.message || 'Gagal memuat pegawai.';
          row.appendChild(cell);
          tbody.appendChild(row);
        });
    }

    document.getElementById('pegawai-sub-cari').addEventListener('input', function () {
      gambarPegawaiSub(daftarPegawaiSubAktif);
    });

    function isiModalHapusSub(tombol) {
      var id = tombol.getAttribute('data-id') || '';
      var nama = tombol.getAttribute('data-nama') || '';
      var unitId = tombol.getAttribute('data-unit-kerja-id') || '';
      var daftar = document.getElementById('hapus-sub-pegawai');
      var tujuanSemua = document.getElementById('hapus-sub-semua-tujuan');
      var terapkanSemua = document.getElementById('hapus-sub-terapkan-semua');
      var tabelBungkus = document.getElementById('hapus-sub-tabel-bungkus');
      var semuaBungkus = document.getElementById('hapus-sub-semua-bungkus');
      var kolomTindakan = document.getElementById('hapus-sub-kolom-tindakan');
      var submit = document.getElementById('hapus-sub-submit');

      document.querySelectorAll('input[name="mode_hapus_sub"]').forEach(function (radio) {
        radio.checked = false;
      });
      document.getElementById('hapus-sub-id').value = id;
      document.getElementById('hapus-sub-unit-id').value = unitId;
      document.getElementById('hapus-sub-nama').textContent = nama;
      document.getElementById('hapus-sub-jumlah').textContent = '…';
      daftar.innerHTML = '<tr><td colspan="3" class="teks-redup">Memuat daftar pegawai…</td></tr>';
      tujuanSemua.innerHTML = '<option value="">Pilih tujuan untuk semua pegawai</option>';
      terapkanSemua.disabled = true;
      tabelBungkus.classList.add('hidden');
      delete tabelBungkus.dataset.siap;
      semuaBungkus.classList.remove('hidden');
      kolomTindakan.textContent = 'Pilih unit';
      submit.disabled = true;
      bukaModal('modal-hapus-sub');

      fetch('{{ url('admin/unit/sub') }}/' + encodeURIComponent(id) + '/pegawai', {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) {
          if (! r.ok) throw new Error('Daftar pegawai tidak dapat dimuat.');
          return r.json();
        })
        .then(function (data) {
          daftar.innerHTML = '';
          document.getElementById('hapus-sub-jumlah').textContent = String(data.pegawai.length);

          if (! data.pegawai.length) {
            var kosong = document.createElement('tr');
            kosong.innerHTML = '<td colspan="3" class="teks-redup">Tidak ada pegawai pada sub unit ini.</td>';
            daftar.appendChild(kosong);
          }

          data.tujuan.forEach(function (item) {
            var opsi = document.createElement('option');
            opsi.value = item.id;
            opsi.textContent = item.nama;
            tujuanSemua.appendChild(opsi);
          });
          var tanpaSub = document.createElement('option');
          tanpaSub.value = 'none';
          tanpaSub.textContent = 'Tetap di unit kerja tanpa sub unit';
          tujuanSemua.appendChild(tanpaSub);

          data.pegawai.forEach(function (pegawai) {
            var item = document.createElement('tr');
            item.setAttribute('data-pegawai-id', pegawai.id);

            var identitas = document.createElement('div');
            identitas.className = 'font-medium';
            identitas.textContent = pegawai.nama;
            var namaCell = document.createElement('td');
            namaCell.appendChild(identitas);

            var emailCell = document.createElement('td');
            emailCell.className = 'teks-kecil';
            emailCell.textContent = pegawai.email || '—';

            var tujuanPegawai = document.createElement('select');
            tujuanPegawai.name = 'pindah_ke_sub_unit_id[' + pegawai.id + ']';
            tujuanPegawai.required = true;
            tujuanPegawai.setAttribute('aria-label', 'Tujuan pemindahan ' + pegawai.nama);

            var pilih = document.createElement('option');
            pilih.value = '';
            pilih.textContent = 'Pilih tujuan';
            tujuanPegawai.appendChild(pilih);

            data.tujuan.forEach(function (tujuan) {
              var opsiPegawai = document.createElement('option');
              opsiPegawai.value = tujuan.id;
              opsiPegawai.textContent = tujuan.nama;
              tujuanPegawai.appendChild(opsiPegawai);
            });
            var opsiTanpaSub = document.createElement('option');
            opsiTanpaSub.value = 'none';
            opsiTanpaSub.textContent = 'Tetap di unit kerja tanpa sub unit';
            tujuanPegawai.appendChild(opsiTanpaSub);
            var tujuanCell = document.createElement('td');
            tujuanCell.appendChild(tujuanPegawai);
            var pegawaiIds = document.createElement('input');
            pegawaiIds.type = 'hidden';
            pegawaiIds.name = 'pegawai_ids[]';
            pegawaiIds.value = pegawai.id;
            tujuanCell.appendChild(pegawaiIds);

            item.appendChild(namaCell);
            item.appendChild(emailCell);
            item.appendChild(tujuanCell);
            daftar.appendChild(item);
          });
          terapkanSemua.disabled = data.pegawai.length === 0;
          tabelBungkus.dataset.siap = '1';
          submit.disabled = !document.querySelector('input[name="mode_hapus_sub"]:checked');
        })
        .catch(function (error) {
          daftar.innerHTML = '';
          var gagal = document.createElement('tr');
          var pesanGagal = document.createElement('td');
          pesanGagal.colSpan = 3;
          pesanGagal.className = 'teks-merah';
          pesanGagal.textContent = error.message || 'Gagal memuat daftar pegawai.';
          gagal.appendChild(pesanGagal);
          daftar.appendChild(gagal);
          tampilPesan(pesanGagal.textContent, false);
        });
    }

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

      var nilaiAwal = select.value || '';
      var labelKosong = select.options[0] ? select.options[0].textContent : '';

      // Isi daftar <select> dari daftar pegawai global supaya pilihan atasan tetap
      // tersimpan saat formulir dikirim (nilai select = id pegawai terpilih).
      select.innerHTML = '';
      var optKosong = document.createElement('option');
      optKosong.value = '';
      optKosong.textContent = labelKosong;
      select.appendChild(optKosong);

      var isi = [{ nilai: '', label: labelKosong, cari: '' }];
      (window.PEGAWAI_UNIT || []).forEach(function (p) {
        var o = document.createElement('option');
        o.value = String(p.id);
        o.textContent = p.nama;
        select.appendChild(o);
        isi.push({ nilai: String(p.id), label: p.nama, cari: p.cari || '' });
      });

      select.value = nilaiAwal;

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

    var spinner = '<svg class="w-3.5 h-3.5 inline-block align-[-2px] animate-spin" viewBox="0 0 24 24" fill="none">'
                + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
                + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>';

    // Loading per tombol: hanya tombol yang memicu disimpan + dimatikan sampai
    // respons tiba, tombol lain tetap bisa diklik sehingga beberapa data dapat
    // disimpan berurutan sekaligus.
    function tandaiMemuat(tombol) {
      if (! tombol || tombol.disabled) return;
      tombol.dataset.teksAwal = tombol.innerHTML;
      tombol.disabled = true;
      tombol.classList.add('opacity-70', 'cursor-wait');
      tombol.innerHTML = spinner + ' Menyimpan…';
    }

    function pulihkanTombol(tombol) {
      if (! tombol) return;
      if (tombol.dataset.teksAwal != null) tombol.innerHTML = tombol.dataset.teksAwal;
      tombol.disabled = false;
      tombol.classList.remove('opacity-70', 'cursor-wait');
    }

    function kirim(form, tambahan, tombol) {
      var data = new FormData(form);
      Object.keys(tambahan || {}).forEach(function (k) { data.set(k, tambahan[k]); });

      tampilPesan('Menyimpan…', true);
      tandaiMemuat(tombol);

      return fetch(urlAksi, {
        method: 'POST',
        body: data,
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json().then(function (h) { return { ok: r.ok, h: h }; }); })
        .then(function (b) {
          var h = b.h;
          if (h.tabs) tabBar.innerHTML = h.tabs;
          if (h.isiSemua) renderSemuaPanel(h.isiSemua);
          if (h.mode === 'unit') tab = tabAktifDariTabs() || tab;
          else if (h.mode) tab = h.mode;
          aktifkanTab(tab);
          unitSiapkanSemua();

          tampilPesan(h.pesan, !! h.sukses);

          if (h.sukses && form.dataset.ajax === 'tambah_unit') window.unitTutup('modal-tambah');
          if (h.sukses && form.dataset.ajax === 'ubah_unit') window.unitTutup('modal-ubah');
          if (h.sukses && form.dataset.ajax === 'hapus_sub') window.unitTutup('modal-hapus-sub');
          if (h.sukses && form.isConnected) { form.reset(); unitSiapkanSemua(); }
        })
        .catch(function () { tampilPesan('Terjadi kesalahan jaringan.', false); })
        .finally(function () { pulihkanTombol(tombol); });
    }

    // Delegasi: seluruh tombol & form hasil render dinamis tertangkap di sini.
    document.addEventListener('click', function (e) {
      var target = e.target;

      var tabKlik = target.closest('[data-tab]');
      if (tabKlik) {
        e.preventDefault();
        aktifkanTab(tabKlik.getAttribute('data-tab'));
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

      var kelolaPegawai = target.closest('[data-kelola-pegawai-sub]');
      if (kelolaPegawai) {
        e.preventDefault();
        muatPegawaiSub(
          kelolaPegawai.getAttribute('data-id'),
          kelolaPegawai.getAttribute('data-nama'),
          kelolaPegawai.getAttribute('data-unit-kerja-id')
        );
        return;
      }

      var pindahkanKeSub = target.closest('[data-pindahkan-ke-sub]');
      if (pindahkanKeSub) {
        e.preventDefault();
        if (pindahkanKeSub.disabled || !subUnitKelolaAktif) return;

        var teksAwalPindah = pindahkanKeSub.textContent;
        var dataPindah = new FormData();
        dataPindah.set('aksi', 'pindahkan_pegawai_sub');
        dataPindah.set('user_id', pindahkanKeSub.dataset.userId);
        dataPindah.set('sub_unit_id', pindahkanKeSub.dataset.subUnitId);
        dataPindah.set('unit_kerja_id', pindahkanKeSub.dataset.unitKerjaId);
        pindahkanKeSub.disabled = true;
        pindahkanKeSub.textContent = 'Memindahkan…';
        tampilPesan('Memindahkan pegawai…', true);

        fetch(urlAksi, {
          method: 'POST',
          body: dataPindah,
          headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        })
          .then(function (r) {
            return r.json().then(function (h) {
              if (!r.ok || !h.sukses) throw new Error(h.pesan || 'Pegawai gagal dipindahkan.');
              if (h.tabs) tabBar.innerHTML = h.tabs;
              if (h.isiSemua) renderSemuaPanel(h.isiSemua);
              if (h.mode === 'unit') tab = tabAktifDariTabs() || tab;
              else if (h.mode) tab = h.mode;
              aktifkanTab(tab);
              unitSiapkanSemua();
              tampilPesan(h.pesan, true);
              return muatPegawaiSub(
                subUnitKelolaAktif.id,
                subUnitKelolaAktif.nama,
                subUnitKelolaAktif.unitKerjaId
              );
            });
          })
          .catch(function (error) {
            tampilPesan(error.message || 'Gagal memindahkan pegawai.', false);
          })
          .finally(function () {
            if (pindahkanKeSub.isConnected) {
              pindahkanKeSub.disabled = false;
              pindahkanKeSub.textContent = teksAwalPindah;
            }
          });
        return;
      }

      if (target.closest('#hapus-sub-terapkan-semua')) {
        e.preventDefault();
        var nilaiSemua = document.getElementById('hapus-sub-semua-tujuan').value;
        if (! nilaiSemua) {
          tampilPesan('Pilih tujuan yang akan diterapkan ke semua pegawai.', false);
          return;
        }
        document.querySelectorAll('#hapus-sub-pegawai select[name^="pindah_ke_sub_unit_id["]')
          .forEach(function (select) { select.value = nilaiSemua; });
        tampilPesan('Tujuan pemindahan diterapkan ke semua pegawai. Anda masih dapat mengubah tujuan masing-masing.', true);
        return;
      }

      var modeHapusSub = target.closest('input[name="mode_hapus_sub"]');
      if (modeHapusSub) {
        var tabelBungkus = document.getElementById('hapus-sub-tabel-bungkus');
        var semuaBungkus = document.getElementById('hapus-sub-semua-bungkus');
        var kolomTindakan = document.getElementById('hapus-sub-kolom-tindakan');
        var submitHapus = document.getElementById('hapus-sub-submit');
        var nonaktifkan = modeHapusSub.value === 'nonaktifkan';
        tabelBungkus.classList.remove('hidden');
        semuaBungkus.classList.toggle('hidden', nonaktifkan);
        kolomTindakan.textContent = nonaktifkan ? 'Tindakan' : 'Pilih unit';

        document.querySelectorAll('#hapus-sub-pegawai select[name^="pindah_ke_sub_unit_id["]')
          .forEach(function (select) {
            select.required = ! nonaktifkan;
            select.disabled = nonaktifkan;
          });
        document.querySelectorAll('#hapus-sub-pegawai tr[data-pegawai-id]').forEach(function (row) {
          var cell = row.children[2];
          var select = cell.querySelector('select');
          var badge = cell.querySelector('[data-tindakan-nonaktif]');
          if (nonaktifkan && ! badge) {
            badge = document.createElement('span');
            badge.setAttribute('data-tindakan-nonaktif', '');
            badge.className = 'badge badge-abu';
            badge.textContent = 'Akan dinonaktifkan';
            cell.appendChild(badge);
          } else if (! nonaktifkan && badge) {
            badge.remove();
          }
          if (select) select.classList.toggle('hidden', nonaktifkan);
        });
        submitHapus.disabled = tabelBungkus.dataset.siap !== '1';
        return;
      }

      var hapusSub = target.closest('[data-hapus-sub]');
      if (hapusSub) {
        e.preventDefault();
        isiModalHapusSub(hapusSub);
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
        }, aksi);
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
      }, aksi);
    });

    document.addEventListener('submit', function (e) {
      var form = e.target;
      // Semua form di halaman ini AJAX: tambah unit, ubah unit, tambah sub unit.
      if (! form.dataset || ! form.dataset.ajax) return;

      e.preventDefault();
      var tombol = e.submitter || form.querySelector('button[type="submit"]');
      kirim(form, { unit_kerja_id: form.querySelector('[name="unit_kerja_id"]')?.value || '' }, tombol);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      ['modal-tambah', 'modal-ubah', 'modal-hapus-sub', 'modal-pegawai-sub'].forEach(function (id) {
        var m = document.getElementById(id);
        if (m && ! m.classList.contains('hidden')) window.unitTutup(id);
      });
    });
  })();
</script>
@endsection