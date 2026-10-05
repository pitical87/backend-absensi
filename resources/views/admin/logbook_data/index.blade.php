@extends('layouts.admin')

@section('content')

@php
  $namaBulan = [1 => 'Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
  $tahunKini = now()->year;
@endphp

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('log') !!} Data Logbook</h2>
    <span class="badge badge-biru">{{ number_format($total, 0, ',', '.') }} pegawai</span>
  </div>


  <form method="get" action="{{ url('admin/logbook-data') }}" class="bilah-alat">
    <select name="bulan">
      @foreach($namaBulan as $i => $nm)
        <option value="{{ $i }}" {{ $bulan === $i ? 'selected' : '' }}>{{ $nm }}</option>
      @endforeach
    </select>
    <select name="tahun">
      @for($y = $tahunKini + 1; $y >= $tahunKini - 5; $y--)
        <option value="{{ $y }}" {{ $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
      @endfor
    </select>
    <input type="text" name="q" placeholder="Cari nama pegawai…" value="{{ $q }}">
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr>
        <th>Nama Pegawai</th>
        <th>Unit / Bidang</th>
        <th class="tengah">Entri</th>
        <th class="tengah">Hari Kerja</th>
        <th class="tengah">Verifikasi</th>
        <th class="tengah">Aksi</th>
      </tr></thead>
      <tbody>
        @foreach($daftar as $r)
          @php
            $entri   = (int) $r->jumlah_entri;
            $verif   = (int) $r->jumlah_verifikasi;
            $sisa    = $entri - $verif;
          @endphp
        <tr>
          <td>
            <strong>{{ $r->nama_lengkap }}</strong>
            @if($r->status !== 'aktif')
              <span class="badge badge-merah ml-1">nonaktif</span>
            @endif
            @if($r->nip)
              <br><span class="teks-kecil teks-redup">NIP {{ $r->nip }}</span>
            @endif
          </td>
          <td>{{ $r->unit_nama }}@if($r->sub_nama) — {{ $r->sub_nama }}@endif</td>
          <td class="angka">{{ number_format($entri, 0, ',', '.') }}</td>
          <td class="angka">
            <span class="badge {{ (int) $r->jumlah_hari > 0 ? 'badge-hijau' : 'badge-amber' }}">
              {{ (int) $r->jumlah_hari }} hari
            </span>
          </td>
          <td class="tengah">
            @if($entri === 0)
              <span class="teks-redup teks-kecil">—</span>
            @else
              <span class="badge {{ $sisa === 0 ? 'badge-hijau' : 'badge-amber' }}">
                {{ $verif }}/{{ $entri }}
              </span>
              @if($sisa > 0)
                <br><span class="teks-kecil teks-redup">{{ $sisa }} belum diverifikasi</span>
              @endif
            @endif
          </td>
          <td class="tengah">
            <button type="button" class="btn btn-garis btn-kecil tombol-detail-logbook"
                    data-id="{{ $r->id }}" data-nama="{{ $r->nama_lengkap }}">Detail</button>
          </td>
        </tr>
        @endforeach
        @if(! $daftar)
        <tr><td colspan="6" class="tengah teks-redup">
          Tidak ada pegawai yang cocok dengan filter.
        </td></tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($totalHal > 1)
  <div class="paginasi">
    @php
      $dasar = 'admin/logbook-data?' . http_build_query([
        'bulan' => $bulan, 'tahun' => $tahun, 'q' => $q ?: null,
      ]);
      $pisah = str_contains($dasar, '?') && ! str_ends_with($dasar, '?') ? '&' : '';
    @endphp
    @for($h = max(1, $halaman - 3); $h <= min($totalHal, $halaman + 3); $h++)
      @if($h === $halaman)
        <span class="aktif">{{ $h }}</span>
      @else
        <a href="{{ url($dasar . $pisah . 'hal=' . $h) }}">{{ $h }}</a>
      @endif
    @endfor
    <span class="info">hal. {{ $halaman }} / {{ $totalHal }}</span>
  </div>
  @endif
</section>

{{-- Modal Detail Logbook Pegawai --}}
<div id="modal-detail" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-4xl max-h-[90vh] flex flex-col">
    <div class="kartu-kepala">
      <h2>{!! ikon('log') !!} Detail Logbook</h2>
      <button type="button" id="detail-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>

    <div class="px-4 pt-3 flex flex-wrap items-end justify-between gap-2">
      <div>
        <strong id="detail-nama">—</strong>
        <br><span class="teks-redup teks-kecil" id="detail-subjudul">&nbsp;</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <label class="teks-redup teks-kecil flex items-center gap-1">
          <input type="checkbox" id="pilih-semua-detail" class="w-auto">
          Pilih semua
        </label>
        <button type="button" id="btn-verifikasi-semua" class="btn btn-navy btn-kecil" disabled>Verifikasi Semua</button>
        <button type="button" id="btn-batal-semua" class="btn btn-garis btn-kecil" disabled>Batal Verifikasi Semua</button>
        <button type="button" id="btn-hapus-semua" class="btn btn-bahaya btn-kecil" disabled>Hapus Terpilih</button>
      </div>
    </div>

    <div class="px-4 pt-3 pb-1 overflow-y-auto" id="detail-isi"></div>
    <p class="teks-redup teks-kecil px-4 pb-2" id="detail-pesan"></p>

    <div class="flex justify-end gap-2 px-4 py-3 border-t border-slate-200">
      <button type="button" id="detail-tutup-2" class="btn btn-garis btn-kecil">Tutup</button>
    </div>
  </div>
</div>

{{-- Modal Edit Entri --}}
<div id="modal-edit" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-md">
    <div class="kartu-kepala">
      <h2>Edit Entri Logbook</h2>
      <button type="button" id="edit-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <div class="px-4 pt-3">
      <p class="teks-redup teks-kecil">Entri milik <strong id="edit-pemilik">—</strong>.
        Bila entri sudah diverifikasi, status verifikasinya akan dilepas.</p>
    </div>
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
      <button type="submit" form="form-edit" class="btn btn-navy btn-kecil">Simpan Perubahan</button>
    </div>
    <form id="form-edit" action="{{ route('admin.logbook_data.ubah') }}" method="post">
      @csrf
      <input type="hidden" name="id" id="edit-id">
    </form>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  const modal      = document.getElementById('modal-detail');
  const isiD       = document.getElementById('detail-isi');
  const namaD      = document.getElementById('detail-nama');
  const subD       = document.getElementById('detail-subjudul');
  const pesanD     = document.getElementById('detail-pesan');
  const pilihSemua = document.getElementById('pilih-semua-detail');
  const btnVerif   = document.getElementById('btn-verifikasi-semua');
  const btnBatal   = document.getElementById('btn-batal-semua');
  const btnHapus   = document.getElementById('btn-hapus-semua');

  const modalEdit  = document.getElementById('modal-edit');
  const formEdit   = document.getElementById('form-edit');
  const editTutup  = document.getElementById('edit-tutup');
  const editBatal  = document.getElementById('edit-batal');
  const editPesan  = document.getElementById('edit-pesan');
  const editPemilik= document.getElementById('edit-pemilik');
  const eTanggal   = document.getElementById('edit-tanggal');
  const eJam       = document.getElementById('edit-jam');
  const eIsi       = document.getElementById('edit-isi');

  const CSRF      = document.querySelector('meta[name="csrf"]').content;
  const BULAN     = @json(BULAN_ID);
  const BULAN_INI = {{ (int) $bulan }};
  const TAHUN_INI = {{ (int) $tahun }};

  let entriAktif = {};   // id -> {tanggal, jam, isi, verified}
  let pemilikAktif = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function namaTanggal(iso) {
    const p = iso.split('-');
    return parseInt(p[2], 10) + ' ' + (BULAN[parseInt(p[1], 10)] || '') + ' ' + p[0];
  }

  function csrfHeaders() {
    return {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': CSRF,
    };
  }

  function bukaModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function tutupModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    isiD.innerHTML = '';
    entriAktif = {};
    pemilikAktif = null;
    pilihSemua.checked = false;
    pesanD.textContent = '';
    perbaruiTombolMassal();
  }

  document.getElementById('detail-tutup').addEventListener('click', tutupModal);
  document.getElementById('detail-tutup-2').addEventListener('click', tutupModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });

  // ── Muat detail seorang pegawai ──────────────────
  function muatDetail(userId, nama) {
    pemilikAktif = userId;
    namaD.textContent = nama;
    subD.textContent = 'Memuat data…';
    isiD.innerHTML = '';
    pesanD.textContent = '';
    pilihSemua.checked = false;
    bukaModal();

    const params = new URLSearchParams({
      user_id: userId, bulan: String(BULAN_INI), tahun: String(TAHUN_INI),
    });

    fetch('{{ route("admin.logbook_data.detail") }}?' + params.toString(),
      { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          subD.textContent = '';
          isiD.innerHTML = '<p class="tengah teks-redup py-6">'
            + esc(h.pesan || 'Gagal memuat data.') + '</p>';
          return;
        }

        subD.textContent = h.unit + ' · ' + h.total_hari + ' hari · ' + h.total_entri
          + ' entri · ' + h.terverifikasi + ' terverifikasi';

        if (! h.total_entri) {
          isiD.innerHTML = '<p class="tengah teks-redup py-6">'
            + 'Belum ada entri logbook pada bulan ini.</p>';
          return;
        }

        entriAktif = {};
        let html = '';
        Object.keys(h.data).forEach(function (tgl) {
          html += '<div class="mt-2 first:mt-0"><strong class="teks-kecil">'
            + esc(namaTanggal(tgl)) + '</strong></div>';
          html += '<table class="tabel"><thead><tr>'
            + '<th class="w-8"></th><th class="w-20">Jam</th><th>Isi Aktivitas</th>'
            + '<th class="w-44">Status</th><th class="w-32 text-center">Aksi</th>'
            + '</tr></thead><tbody>';
          h.data[tgl].forEach(function (e) {
            entriAktif[e.id] = { tanggal: tgl, jam: e.jam, isi: e.isi, verified: e.verified };
            html += '<tr>'
              + '<td><input type="checkbox" class="pilih-entri" data-id="' + e.id + '"></td>'
              + '<td class="angka">' + esc(e.jam) + '</td>'
              + '<td>' + esc(e.isi).replace(/\n/g, '<br>') + '</td>'
              + '<td>' + (e.verified
                  ? '<span class="badge badge-hijau">Terverifikasi</span>'
                    + (e.verified_at
                        ? '<br><span class="teks-redup teks-kecil">' + esc(e.verified_at) + '</span>'
                        : '')
                    + (e.verifikator
                        ? '<br><span class="teks-redup teks-kecil">oleh ' + esc(e.verifikator) + '</span>'
                        : '')
                  : '<span class="badge badge-amber">Belum diverifikasi</span>') + '</td>'
              + '<td class="tengah">'
                + '<div class="flex flex-wrap gap-1 justify-center">'
                  + (e.verified
                      ? '<button type="button" class="btn btn-garis btn-kecil tombol-batal-verifikasi" data-id="' + e.id + '">Batal</button>'
                      : '<button type="button" class="btn btn-navy btn-kecil tombol-verifikasi" data-id="' + e.id + '">Verifikasi</button>')
                  + '<button type="button" class="btn btn-garis btn-kecil tombol-edit-entri" data-id="' + e.id + '">Edit</button>'
                  + '<button type="button" class="btn btn-bahaya btn-kecil tombol-hapus-entri" data-id="' + e.id + '">Hapus</button>'
                + '</div></td>'
              + '</tr>';
          });
          html += '</tbody></table>';
        });
        isiD.innerHTML = html;
        perbaruiTombolMassal();
      })
      .catch(function () {
        subD.textContent = '';
        isiD.innerHTML = '<p class="tengah teks-redup py-6">Terjadi kesalahan jaringan.</p>';
      });
  }

  document.querySelectorAll('.tombol-detail-logbook').forEach(function (btn) {
    btn.addEventListener('click', function () {
      muatDetail(btn.dataset.id, btn.dataset.nama);
    });
  });

  // ── Aksi baris: verifikasi / edit / hapus ────────
  function kirimAksi(url, body) {
    pesanD.textContent = 'Memproses…';
    return fetch(url, { method: 'POST', headers: csrfHeaders(), body: body })
      .then(function (r) { return r.json().then(function (h) { if (! r.ok) throw h; return h; }); })
      .then(function (h) {
        pesanD.textContent = (h.pesan || 'Selesai.') + ' Memuat ulang…';
        muatDetail(pemilikAktif, namaD.textContent);
      })
      .catch(function (err) {
        pesanD.textContent = (err && err.pesan) || 'Gagal memproses.';
      });
  }

  isiD.addEventListener('click', function (e) {
    const verif = e.target.closest('.tombol-verifikasi');
    if (verif) {
      kirimAksi('{{ route("admin.logbook_data.verifikasi") }}',
        new URLSearchParams({ 'ids[]': verif.dataset.id, aksi: 'verifikasi' }));
      return;
    }

    const batal = e.target.closest('.tombol-batal-verifikasi');
    if (batal) {
      kirimAksi('{{ route("admin.logbook_data.verifikasi") }}',
        new URLSearchParams({ 'ids[]': batal.dataset.id, aksi: 'batal' }));
      return;
    }

    const hapus = e.target.closest('.tombol-hapus-entri');
    if (hapus) {
      if (! confirm('Hapus entri logbook ini?')) return;
      kirimAksi('{{ route("admin.logbook_data.hapus") }}',
        new URLSearchParams({ 'ids[]': hapus.dataset.id }));
      return;
    }

    const edit = e.target.closest('.tombol-edit-entri');
    if (edit) bukaEdit(Number(edit.dataset.id));
  });

  // ── Pilih banyak + aksi massal ────────────────────
  function idTerpilih() {
    return Array.from(isiD.querySelectorAll('.pilih-entri:checked')).map(function (c) { return c.dataset.id; });
  }

  function perbaruiTombolMassal() {
    const jumlah = idTerpilih().length;
    btnVerif.disabled = jumlah === 0;
    btnBatal.disabled = jumlah === 0;
    btnHapus.disabled = jumlah === 0;
    btnVerif.textContent = jumlah ? 'Verifikasi (' + jumlah + ')' : 'Verifikasi Semua';
    btnBatal.textContent = jumlah ? 'Batal Verifikasi (' + jumlah + ')' : 'Batal Verifikasi Semua';
  }

  pilihSemua.addEventListener('change', function () {
    isiD.querySelectorAll('.pilih-entri').forEach(function (c) { c.checked = pilihSemua.checked; });
    perbaruiTombolMassal();
  });

  isiD.addEventListener('change', function (e) {
    if (e.target.classList.contains('pilih-entri')) perbaruiTombolMassal();
  });

  function kirimTerpilih(url, ekstra, konfirmasi) {
    const ids = idTerpilih();
    if (! ids.length) return;
    if (konfirmasi && ! confirm(konfirmasi + ' ' + ids.length + ' entri logbook?')) return;

    const body = new URLSearchParams();
    ids.forEach(function (id) { body.append('ids[]', id); });
    if (ekstra) Object.keys(ekstra).forEach(function (k) { body.set(k, ekstra[k]); });

    kirimAksi(url, body);
  }

  btnVerif.addEventListener('click', function () {
    kirimTerpilih('{{ route("admin.logbook_data.verifikasi") }}', { aksi: 'verifikasi' });
  });

  btnBatal.addEventListener('click', function () {
    kirimTerpilih('{{ route("admin.logbook_data.verifikasi") }}', { aksi: 'batal' });
  });

  btnHapus.addEventListener('click', function () {
    kirimTerpilih('{{ route("admin.logbook_data.hapus") }}', null, 'Hapus');
  });

  // ── Modal edit entri ──────────────────────────────
  let editId = null;

  function bukaEdit(id) {
    const e = entriAktif[id];
    if (! e) return;
    editId = id;
    editPemilik.textContent = namaD.textContent;
    eTanggal.value = e.tanggal;
    eJam.value = e.jam;
    eIsi.value = e.isi;
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

  editTutup.addEventListener('click', tutupEdit);
  editBatal.addEventListener('click', tutupEdit);
  modalEdit.addEventListener('click', function (e) { if (e.target === modalEdit) tutupEdit(); });

  formEdit.addEventListener('submit', function (e) {
    e.preventDefault();
    if (editId == null) return;
    if (! eTanggal.value || ! eJam.value || ! eIsi.value.trim()) {
      editPesan.textContent = 'Lengkapi tanggal, jam, dan isi.';
      editPesan.classList.add('text-red-600');
      return;
    }

    editPesan.classList.remove('text-red-600');
    editPesan.textContent = 'Menyimpan…';

    fetch(formEdit.action, {
      method: 'POST',
      headers: csrfHeaders(),
      body: new URLSearchParams({
        id: String(editId),
        tanggal: eTanggal.value,
        jam: eJam.value,
        isi: eIsi.value,
      }),
    })
      .then(function (r) { return r.json().then(function (h) { if (! r.ok) throw h; return h; }); })
      .then(function () {
        tutupEdit();
        pesanD.textContent = 'Entri logbook diperbarui. Memuat ulang…';
        muatDetail(pemilikAktif, namaD.textContent);
      })
      .catch(function (err) {
        let msg = (err && err.pesan) || 'Gagal menyimpan perubahan.';
        if (err && err.errors) {
          const k = Object.keys(err.errors)[0];
          if (k && err.errors[k][0]) msg = err.errors[k][0];
        }
        editPesan.textContent = msg;
        editPesan.classList.add('text-red-600');
      });
  });
})();
</script>
@endsection