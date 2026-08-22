@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>Mapping Akun SIMRS <span class="badge badge-biru">{{ number_format($total, 0, ',', '.') }}</span></h2>
  </div>

  <form method="get" action="{{ route('admin.mapping_simrs') }}" class="bilah-alat">
    <input type="text" name="q" placeholder="Cari nama / email / NIP" value="{{ $q }}">
    <button type="submit" class="btn btn-navy btn-kecil">Cari</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Pengguna</th><th>ID SIMRS</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @foreach($pegawai as $p)
        <tr>
          <td>
            <strong>{{ $p->nama_lengkap }}</strong><br>
            <span class="teks-redup teks-kecil">{{ $p->email }}</span>
          </td>
          <td>
            @if($p->mappingSimrs)
              <strong>{{ $p->mappingSimrs->simrs_user_id }}</strong>
            @else
              <span class="teks-redup">&mdash;</span>
            @endif
          </td>
          <td>
            {!! $p->mappingSimrs
                ? '<span class="badge badge-hijau">TerMapping</span>'
                : '<span class="badge badge-amber">Belum di Mapping</span>' !!}
          </td>
          <td>
            <button type="button"
                    class="btn btn-navy btn-kecil tombol-input-kode"
                    data-user-id="{{ $p->id }}"
                    data-nama="{{ $p->nama_lengkap }}">Input Kode</button>
          </td>
        </tr>
        @endforeach
        @if($pegawai->isEmpty())
        <tr><td colspan="4" class="tengah teks-redup">Belum ada Pegawai.</td></tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($totalHal > 1)
  <div class="paginasi">
    @php
      $dasar = 'admin/mapping-simrs?' . http_build_query(array_filter(['q' => $q ?: null]));
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

<div id="modal-kode" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-xl">
    <div class="kartu-kepala">
      <h2 id="modal-kode-judul">Input Kode SIMRS</h2>
      <button type="button" id="modal-kode-tutup" class="btn btn-garis btn-kecil">&times; Tutup</button>
    </div>

    <div class="p-2 text-sm h-[90vh]">
      <p id="modal-status" class="mb-2"></p>

      <div class="flex items-center gap-2 mb-2">
        <input type="text" id="modal-q" placeholder="Cari NIK / nama di SIMRS" class="flex-1">
        <button type="button" id="modal-cari" class="btn btn-garis btn-kecil">Cari</button>
      </div>

      <form method="post" action="{{ route('admin.mapping_simrs.simpan') }}" id="form-pilih-kode">
        @csrf
        <input type="hidden" name="user_id" id="modal-user-id">

        <div id="modal-hasil" class="tabel-bungkus max-h-64 overflow-auto"></div>
        <div id="modal-pager" class="paginasi mb-3"></div>

        <div class="flex justify-end gap-2">
          <button type="button" id="modal-batal" class="btn btn-garis btn-kecil">Batal</button>
          <button type="submit" id="modal-simpan" class="btn btn-navy btn-kecil" disabled>Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  const modal   = document.getElementById('modal-kode');
  const judul   = document.getElementById('modal-kode-judul');
  const status  = document.getElementById('modal-status');
  const kotakQ  = document.getElementById('modal-q');
  const tombolC = document.getElementById('modal-cari');
  const hasil   = document.getElementById('modal-hasil');
  const pager   = document.getElementById('modal-pager');
  const userId  = document.getElementById('modal-user-id');
  const simpan  = document.getElementById('modal-simpan');

  let qAktif = '';
  let halAktif = 1;
  let totalHalAktif = 1;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function buka() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function tutupModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

  document.getElementById('modal-kode-tutup').addEventListener('click', tutupModal);
  document.getElementById('modal-batal').addEventListener('click', tutupModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });

  function muatStatus(id) {
    status.innerHTML = '<span class="teks-redup teks-kecil">Memeriksa mapping&hellip;</span>';
    fetch('{{ route("admin.mapping_simrs.cek") }}?user_id=' + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          status.innerHTML = '<span class="text-red-600 teks-kecil">' + esc(h.pesan || 'Gagal memeriksa mapping.') + '</span>';
          return;
        }
        if (! h.data) {
          status.innerHTML = '<span class="badge badge-amber">ID SIMRS tidak ditemukan pada database SIMRS</span>';
          return;
        }
        status.innerHTML = '<span class="badge badge-hijau">TerMapping: ' + esc(h.data.nik) + ' &mdash; ' + esc(h.data.nama) + '</span>';
      })
      .catch(function () { status.innerHTML = ''; });
  }

  function muatHasil(q, hal) {
    qAktif = q;
    halAktif = hal;
    simpan.disabled = true;
    hasil.innerHTML = '<p class="teks-redup p-4 tengah">Mengambil data SIMRS&hellip;</p>';
    pager.innerHTML = '';
    fetch('{{ route("admin.mapping_simrs.cari") }}?q=' + encodeURIComponent(q) + '&hal=' + encodeURIComponent(hal))
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          hasil.innerHTML = '<p class="text-red-600 p-4">' + esc(h.pesan || 'Gagal mengambil data.') + '</p>';
          return;
        }
        if (! h.data || ! h.data.length) {
          hasil.innerHTML = '<p class="teks-redup p-4 tengah">Data pegawai SIMRS tidak ditemukan.</p>';
          return;
        }
        totalHalAktif = h.totalHal || 1;
        const tabel = document.createElement('table');
        tabel.className = 'tabel';
        tabel.innerHTML = '<thead><tr><th></th><th>ID SIMRS</th><th>Nama</th></tr></thead>';
        const tbody = document.createElement('tbody');
        h.data.forEach(function (p) {
          const tr = document.createElement('tr');
          tr.innerHTML =
            '<td class="px-1 py-0 my-0"><input type="radio" name="simrs_user_id" value="' + esc(p.nik) + '"></td>' +
            '<td class="px-1 py-0 my-0"><code>' + esc(p.nik) + '</code></td>' +
            '<td class="px-1 py-0 my-0">' + esc(p.nama) + '</td>';
          tr.addEventListener('click', function () {
            tr.querySelector('input').checked = true;
            simpan.disabled = false;
          });
          tbody.appendChild(tr);
        });
        tabel.appendChild(tbody);
        hasil.innerHTML = '';
        hasil.appendChild(tabel);
        renderPager();
      })
      .catch(function () {
        hasil.innerHTML = '<p class="text-red-600 p-4">Terjadi kesalahan jaringan.</p>';
      });
  }

  function renderPager() {
    if (totalHalAktif <= 1) { pager.innerHTML = ''; return; }
    const info = document.createElement('span');
    info.className = 'info';
    info.textContent = 'hal. ' + halAktif + ' / ' + totalHalAktif;

    const sebelum = document.createElement('button');
    sebelum.type = 'button';
    sebelum.className = 'btn btn-garis btn-kecil';
    sebelum.textContent = '« Sebelumnya';
    sebelum.disabled = halAktif <= 1;
    sebelum.addEventListener('click', function () { muatHasil(qAktif, halAktif - 1); });

    const sesudah = document.createElement('button');
    sesudah.type = 'button';
    sesudah.className = 'btn btn-garis btn-kecil';
    sesudah.textContent = 'Berikutnya »';
    sesudah.disabled = halAktif >= totalHalAktif;
    sesudah.addEventListener('click', function () { muatHasil(qAktif, halAktif + 1); });

    pager.innerHTML = '';
    pager.appendChild(sebelum);
    pager.appendChild(info);
    pager.appendChild(sesudah);
  }

  hasil.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'simrs_user_id') simpan.disabled = false;
  });

  function jalankanCari() { muatHasil(kotakQ.value, 1); }
  tombolC.addEventListener('click', jalankanCari);
  kotakQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') jalankanCari(); });

  document.querySelectorAll('.tombol-input-kode').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
      userId.value = tombol.dataset.userId;
      judul.textContent = 'Input Kode SIMRS: ' + (tombol.dataset.nama || '');
      kotakQ.value = '';
      simpan.disabled = true;
      hasil.innerHTML = '';
      buka();
      muatStatus(tombol.dataset.userId);
      muatHasil('', 1);
    });
  });
})();
</script>
@endsection
