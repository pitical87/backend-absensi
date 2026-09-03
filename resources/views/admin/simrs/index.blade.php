@extends('layouts.admin')

@section('content')

@php
  $kategori = match (true) {
      ! $hasil['sukses'] => ['Gagal', 'badge-merah'],
      $hasil['ms_total'] < 100 => ['Cepat', 'badge-hijau'],
      $hasil['ms_total'] < 500 => ['Sedang', 'badge-amber'],
      default => ['Lambat', 'badge-merah'],
  };
@endphp

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="baris-tab-simrs">
  <button type="button" class="simrs-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="koneksi">Cek Koneksi</button>
  <button type="button" class="simrs-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="mapping">Mapping Akun</button>
  <button type="button" class="simrs-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="tindakan">Data Tindakan</button>
  <button type="button" class="simrs-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="lab">Data Lab</button>
</div>

{{-- Tab: Cek Koneksi --}}
<section class="kartu simrs-panel" id="panel-koneksi">
  <div class="kartu-kepala">
    <h2>Cek Koneksi SIMRS</h2>
    <button type="button" id="refresh-koneksi" class="btn btn-garis btn-kecil">{!! ikon('centang', 14) !!} Cek Ulang</button>
  </div>

  <div class="p-4">
    @if($hasil['sukses'])
      <div class="flex items-center gap-2 mb-4 flex-wrap">
        {!! ikon('centang', 20) !!}
        <strong>Terhubung ke SIMRS</strong>
        <span class="badge {{ $kategori[1] }}">{{ $kategori[0] }} &middot; {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms</span>
        <span class="teks-redup teks-kecil">Dicek {{ now()->translatedFormat('d/m/Y H:i:s') }}</span>
      </div>
    @else
      <div class="flex items-center gap-2 mb-4 flex-wrap">
        {!! ikon('silang', 20) !!}
        <strong>Gagal terhubung ke SIMRS</strong>
        <span class="teks-redup teks-kecil">Dicek {{ now()->translatedFormat('d/m/Y H:i:s') }}</span>
      </div>
      <p class="text-red-600 text-sm mb-1">{{ $hasil['pesan'] ?? 'Koneksi tidak dapat dibuat.' }}</p>
      <p class="teks-redup teks-kecil mb-4">Dibatalkan setelah {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms (timeout {{ $timeout }} detik).</p>
    @endif

    <div class="tabel-bungkus">
      <table class="tabel">
        <tbody>
          <tr>
            <td style="width:220px" class="teks-redup">Host</td>
            <td><code>{{ $hasil['host'] }}</code></td>
          </tr>
          @if($hasil['sukses'])
          <tr>
            <td class="teks-redup">Waktu koneksi</td>
            <td>{{ number_format($hasil['ms_koneksi'], 0, ',', '.') }} ms</td>
          </tr>
          <tr>
            <td class="teks-redup">Respons query</td>
            <td>{{ number_format($hasil['ms_query'], 0, ',', '.') }} ms</td>
          </tr>
          <tr>
            <td class="teks-redup">Total kecepatan</td>
            <td>
              {{ number_format($hasil['ms_total'], 0, ',', '.') }} ms
              <span class="badge {{ $kategori[1] }}">{{ $kategori[0] }}</span>
            </td>
          </tr>
          <tr>
            <td class="teks-redup">Versi server MySQL</td>
            <td><code>{{ $hasil['versi_server'] }}</code></td>
          </tr>
          <tr>
            <td class="teks-redup">Waktu pada server SIMRS</td>
            <td>{{ \Carbon\Carbon::parse($hasil['waktu_server'])->translatedFormat('d/m/Y H:i:s') }}
              @if(abs(now()->getTimestamp() - \Carbon\Carbon::parse($hasil['waktu_server'])->getTimestamp()) > 60)
                <span class="badge badge-amber">Selisih jam dengan aplikasi</span>
              @endif
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
</section>

{{-- Tab: Mapping Akun --}}
<section class="kartu simrs-panel hidden" id="panel-mapping">
  <div class="kartu-kepala">
    <h2>Mapping Akun SIMRS <span class="badge badge-biru">{{ number_format($total, 0, ',', '.') }}</span></h2>
  </div>

  <form method="get" action="{{ route('admin.simrs') }}" class="bilah-alat">
    <input type="hidden" name="tab" value="mapping">
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
      $dasar = 'admin/simrs?' . http_build_query(array_filter(['q' => $q ?: null, 'tab' => 'mapping']));
    @endphp
    @for($h = max(1, $halaman - 3); $h <= min($totalHal, $halaman + 3); $h++)
      @if($h === $halaman)
        <span class="aktif">{{ $h }}</span>
      @else
        <a href="{{ url($dasar . '&hal=' . $h) }}">{{ $h }}</a>
      @endif
    @endfor
    <span class="info">hal. {{ $halaman }} / {{ $totalHal }}</span>
  </div>
  @endif
</section>

{{-- Tab: Data Tindakan --}}
<section class="kartu simrs-panel hidden" id="panel-tindakan">
  <div class="kartu-kepala">
    <h2>Data Tindakan SIMRS <span class="badge badge-biru">{{ number_format(count($terMapping), 0, ',', '.') }} pegawai terMapping</span></h2>
  </div>

  <form id="form-tindakan" class="bilah-alat" onsubmit="return false">
    <label class="teks-redup teks-kecil">Dari
      <input type="date" id="tindakan-dari" required>
    </label>
    <label class="teks-redup teks-kecil">Sampai
      <input type="date" id="tindakan-sampai" required>
    </label>
    <label class="teks-redup teks-kecil">Pilih Pegawai
      <select id="tindakan-pegawai" class="min-w-56">
        @forelse($terMapping as $p)
          <option value="{{ $p['user_id'] }}">{{ $p['nama_lengkap'] }} - {{ $p['simrs_user_id'] }}</option>
        @empty
          <option value="" disabled>Belum ada pegawai terMapping</option>
        @endforelse
      </select>
    </label>
    <button type="button" id="tombol-ambil-tindakan" class="btn btn-navy btn-kecil">{!! ikon('unduh', 14) !!} Ambil Tindakan</button>
  </form>

  <div id="info-tindakan" class="p-2 text-sm"></div>

  <div class="p-2">
    <pre id="json-tindakan" class="teks-redup text-xs bg-latar rounded-xl p-4 overflow-auto max-h-[60vh] m-0">Pilih rentang tanggal lalu klik &quot;Ambil Tindakan&quot;.</pre>
  </div>
</section>

{{-- Tab: Data Lab --}}
<section class="kartu simrs-panel hidden" id="panel-lab">
  <div class="kartu-kepala">
    <h2>Data Lab SIMRS <span class="badge badge-biru">{{ number_format(count($terMapping), 0, ',', '.') }} pegawai terMapping</span></h2>
  </div>

  <form id="form-lab" class="bilah-alat" onsubmit="return false">
    <label class="teks-redup teks-kecil">Dari
      <input type="date" id="lab-dari" required>
    </label>
    <label class="teks-redup teks-kecil">Sampai
      <input type="date" id="lab-sampai" required>
    </label>
    <label class="teks-redup teks-kecil">Pilih Pegawai
      <select id="lab-pegawai" class="min-w-56">
        @forelse($terMapping as $p)
          <option value="{{ $p['user_id'] }}">{{ $p['nama_lengkap'] }} - {{ $p['simrs_user_id'] }}</option>
        @empty
          <option value="" disabled>Belum ada pegawai terMapping</option>
        @endforelse
      </select>
    </label>
    <button type="button" id="tombol-ambil-lab" class="btn btn-navy btn-kecil">{!! ikon('unduh', 14) !!} Ambil Data Lab</button>
  </form>

  <div id="info-lab" class="p-2 text-sm"></div>

  <div class="p-2">
    <pre id="json-lab" class="teks-redup text-xs bg-latar rounded-xl p-4 overflow-auto max-h-[60vh] m-0">Pilih rentang tanggal lalu klik &quot;Ambil Data Lab&quot;.</pre>
  </div>
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
  // ── Tab switcher ──
  const tombolTab = document.querySelectorAll('.simrs-tab');
  const panelTab  = document.querySelectorAll('.simrs-panel');

  function bukaTab(id) {
    tombolTab.forEach(function (b) {
      const aktif = b.dataset.tab === id;
      b.classList.toggle('bg-white', aktif);
      b.classList.toggle('text-slate-900', aktif);
      b.classList.toggle('shadow-sm', aktif);
      b.classList.toggle('text-slate-500', !aktif);
    });
    panelTab.forEach(function (p) { p.classList.toggle('hidden', p.id !== 'panel-' + id); });
    if (window.history.replaceState) history.replaceState(null, '', '#tab=' + id);
  }

  tombolTab.forEach(function (b) {
    b.addEventListener('click', function () { bukaTab(b.dataset.tab); });
  });

  let tabAwal = '';
  const hash = (location.hash || '').replace('#', '');
  if (hash.startsWith('tab=')) tabAwal = hash.slice(4);
  const dariQuery = new URLSearchParams(location.search).get('tab');
  if (!tabAwal && dariQuery) tabAwal = dariQuery;
  bukaTab(['koneksi', 'mapping', 'tindakan', 'lab'].includes(tabAwal) ? tabAwal : 'koneksi');

  // ── Refres koneksi ──
  const refresh = document.getElementById('refresh-koneksi');
  if (refresh) refresh.addEventListener('click', function () {
    refresh.disabled = true;
    refresh.textContent = 'Memeriksa…';
    window.location.href = '{{ route("admin.simrs", ["tab" => "koneksi"]) }}';
  });

  // ── Modal Mapping ──
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

  // ── Tindakan ──
  (function () {
    const form    = document.getElementById('form-tindakan');
    const dari    = document.getElementById('tindakan-dari');
    const sampai  = document.getElementById('tindakan-sampai');
    const pilih   = document.getElementById('tindakan-pegawai');
    const tombol  = document.getElementById('tombol-ambil-tindakan');
    const info    = document.getElementById('info-tindakan');
    const kotak   = document.getElementById('json-tindakan');
    if (!tombol) return;

    const awalBulan = new Date();
    awalBulan.setDate(1);
    dari.value  = awalBulan.toISOString().slice(0, 10);
    sampai.value = new Date().toISOString().slice(0, 10);

    tombol.addEventListener('click', function () {
      if (! dari.value || ! sampai.value) {
        info.innerHTML = '<span class="text-red-600">Tanggal awal dan akhir wajib diisi.</span>';
        return;
      }
      if (sampai.value < dari.value) {
        info.innerHTML = '<span class="text-red-600">Tanggal akhir tidak boleh sebelum tanggal awal.</span>';
        return;
      }

      tombol.disabled = true;
      info.innerHTML = '<span class="teks-redup">Mengambil data tindakan dari SIMRS&hellip;</span>';
      kotak.textContent = '';

      const params = new URLSearchParams();
      params.set('dari', dari.value);
      params.set('sampai', sampai.value);
      Array.from(pilih.selectedOptions).forEach(function (o) {
        if (o.value) params.append('pegawai[]', o.value);
      });

      const url = '{{ route("admin.simrs.tindakan.ambil") }}?' + params.toString();

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (h) {
          if (! h.sukses) {
            info.innerHTML = '<span class="text-red-600">' + (h.pesan || 'Gagal mengambil data.') + '</span>';
            return;
          }
          info.innerHTML = '<strong>' + (h.total || 0) + '</strong> tindakan ditemukan '
                         + 'untuk periode ' + dari.value + ' s/d ' + sampai.value + '.';
          const hanyaPesan = { sukses: h.sukses, total: h.total, data: (h.data || []).map(function (r) { return r.pesan; }) };
          kotak.textContent = JSON.stringify(hanyaPesan, null, 2);
        })
        .catch(function () {
          info.innerHTML = '<span class="text-red-600">Terjadi kesalahan jaringan.</span>';
        })
        .finally(function () {
          tombol.disabled = false;
        });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); });
  })();

  // ── Lab ──
  (function () {
    const form    = document.getElementById('form-lab');
    const dari    = document.getElementById('lab-dari');
    const sampai  = document.getElementById('lab-sampai');
    const pilih   = document.getElementById('lab-pegawai');
    const tombol  = document.getElementById('tombol-ambil-lab');
    const info    = document.getElementById('info-lab');
    const kotak   = document.getElementById('json-lab');
    if (!tombol) return;

    const awalBulan = new Date();
    awalBulan.setDate(1);
    dari.value  = awalBulan.toISOString().slice(0, 10);
    sampai.value = new Date().toISOString().slice(0, 10);

    tombol.addEventListener('click', function () {
      if (! dari.value || ! sampai.value) {
        info.innerHTML = '<span class="text-red-600">Tanggal awal dan akhir wajib diisi.</span>';
        return;
      }
      if (sampai.value < dari.value) {
        info.innerHTML = '<span class="text-red-600">Tanggal akhir tidak boleh sebelum tanggal awal.</span>';
        return;
      }

      tombol.disabled = true;
      info.innerHTML = '<span class="teks-redup">Mengambil data lab dari SIMRS&hellip;</span>';
      kotak.textContent = '';

      const params = new URLSearchParams();
      params.set('dari', dari.value);
      params.set('sampai', sampai.value);
      Array.from(pilih.selectedOptions).forEach(function (o) {
        if (o.value) params.append('pegawai[]', o.value);
      });

      const url = '{{ route("admin.simrs.lab.ambil") }}?' + params.toString();

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (h) {
          if (! h.sukses) {
            info.innerHTML = '<span class="text-red-600">' + (h.pesan || 'Gagal mengambil data.') + '</span>';
            return;
          }
          info.innerHTML = '<strong>' + (h.total || 0) + '</strong> pemeriksaan lab ditemukan '
                         + 'untuk periode ' + dari.value + ' s/d ' + sampai.value + '.';
          const hanyaPesan = { sukses: h.sukses, total: h.total, data: (h.data || []).map(function (r) { return r.pesan; }) };
          kotak.textContent = JSON.stringify(hanyaPesan, null, 2);
        })
        .catch(function () {
          info.innerHTML = '<span class="text-red-600">Terjadi kesalahan jaringan.</span>';
        })
        .finally(function () {
          tombol.disabled = false;
        });
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); });
  })();
})();
</script>
@endsection
