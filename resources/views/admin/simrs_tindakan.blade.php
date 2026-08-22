@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>Data Tindakan SIMRS <span class="badge badge-biru">{{ number_format(count($pegawai), 0, ',', '.') }} pegawai terMapping</span></h2>
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
        @forelse($pegawai as $p)
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

@endsection

@section('script')
<script>
(function () {
  const form    = document.getElementById('form-tindakan');
  const dari    = document.getElementById('tindakan-dari');
  const sampai  = document.getElementById('tindakan-sampai');
  const pilih   = document.getElementById('tindakan-pegawai');
  const tombol  = document.getElementById('tombol-ambil-tindakan');
  const info    = document.getElementById('info-tindakan');
  const kotak   = document.getElementById('json-tindakan');

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
      .then(function (hasil) {
        if (! hasil.sukses) {
          info.innerHTML = '<span class="text-red-600">' + (hasil.pesan || 'Gagal mengambil data.') + '</span>';
          return;
        }
        info.innerHTML = '<strong>' + (hasil.total || 0) + '</strong> tindakan ditemukan '
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
