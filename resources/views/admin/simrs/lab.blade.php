@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>Data Lab SIMRS <span class="badge badge-biru">{{ number_format(count($pegawai), 0, ',', '.') }} pegawai terMapping</span></h2>
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
        @forelse($pegawai as $p)
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

@endsection

@section('script')
<script>
(function () {
  const form    = document.getElementById('form-lab');
  const dari    = document.getElementById('lab-dari');
  const sampai  = document.getElementById('lab-sampai');
  const pilih   = document.getElementById('lab-pegawai');
  const tombol  = document.getElementById('tombol-ambil-lab');
  const info    = document.getElementById('info-lab');
  const kotak   = document.getElementById('json-lab');

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
      .then(function (hasil) {
        if (! hasil.sukses) {
          info.innerHTML = '<span class="text-red-600">' + (hasil.pesan || 'Gagal mengambil data.') + '</span>';
          return;
        }
        info.innerHTML = '<strong>' + (hasil.total || 0) + '</strong> pemeriksaan lab ditemukan '
                       + 'untuk periode ' + dari.value + ' s/d ' + sampai.value + '.';
        const hanyaPesan = { sukses: hasil.sukses, total: hasil.total, data: (hasil.data || []).map(function (r) { return r.pesan; }) };
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
</script>
@endsection
