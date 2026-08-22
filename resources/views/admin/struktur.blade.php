@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('struktur') !!} Bagan Organisasi RSUD Merauke</h2>
    <a class="btn btn-garis btn-kecil" href="{{ route('struktur') }}" target="_blank"
       rel="noopener">Tampilan Pegawai</a>
  </div>
  <div class="tabel-bungkus">
    @include('partials.pohon', ['cabang' => $pohon, 'kelola' => true, 'akar' => true])
  </div>
  <p class="petunjuk">Pemegang jabatan ditetapkan melalui <strong>Data Pegawai</strong> (field
    Jabatan &amp; Nama Jabatan). Satu jabatan struktural hanya dapat dipegang satu pegawai aktif.</p>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('atur') !!} Tambah Jabatan pada Struktur</h2></div>
  <form method="post" action="{{ url('admin/struktur/aksi') }}" class="bilah-alat">
    @csrf
    <input type="hidden" name="aksi" value="tambah">
    <input type="text" name="nama" placeholder="Nama jabatan, cth. Kasi Rekam Medis" required
           class="min-w-[220px]">
    <select name="kategori" required>
      <option value="">Kategori…</option>
      @foreach($kategoriJab as $k)<option>{{ $k }}</option>@endforeach
    </select>
    <select name="induk_id">
      <option value="">Induk (atasan langsung)…</option>
      @foreach($semua as $j)
        <option value="{{ (int) $j['id'] }}">{{ $j['nama'] }}</option>
      @endforeach
    </select>
    <input type="text" name="unit_label" placeholder="Label unit (khusus Bidang/Bagian, opsional)">
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah</button>
  </form>
  <p class="petunjuk">Label unit hanya diisi untuk node setingkat Bidang/Bagian
    (cth. <em>Bidang Pelayanan</em>) — dipakai sebagai "Unit Kerja" pada identitas pegawai
    dan filter laporan.</p>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('pegawai') !!} Pengelompokan Pegawai</h2></div>
  <div class="form-baris">
    <div>
      <h3>Per Kategori Jabatan</h3>
      <div class="tabel-bungkus">
        <table class="tabel">
          <thead><tr><th>Jabatan</th><th>Jumlah Pegawai Aktif</th></tr></thead>
          <tbody>
            @foreach(kategori_jabatan_list() as $k)
            <tr><td>{{ $k }}</td>
                <td class="angka">{{ (int) ($perKategori[$k] ?? 0) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    <div>
      <h3>Per Tempat Kerja</h3>
      <div class="tabel-bungkus">
        <table class="tabel">
          <thead><tr><th>Unit Kerja</th><th>Jumlah Pegawai Aktif</th></tr></thead>
          <tbody>
            @foreach($perUnit as $u)
            <tr><td>{{ $u->nama }}</td><td class="angka">{{ (int) $u->jml }}</td></tr>
            @endforeach
            @if(! $perUnit)
            <tr><td colspan="2" class="tengah teks-redup">Belum ada pegawai.</td></tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- Modal sederhana ubah nama jabatan -->
<div class="modal-tirai" id="modal-jabatan">
  <div class="modal-kamera">
    <header>{!! ikon('atur', 19) !!} Ubah Jabatan</header>
    <div class="isi">
      <form method="post" action="{{ url('admin/struktur/aksi') }}">
        @csrf
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="id" id="uj-id">
        <div class="form-grup">
          <label class="wajib">Nama Jabatan</label>
          <input type="text" name="nama" id="uj-nama" required>
        </div>
        <div class="form-grup">
          <label>Label Unit (khusus Bidang/Bagian, kosongkan bila bukan)</label>
          <input type="text" name="unit_label" id="uj-unit" placeholder="cth. Bidang Pelayanan">
        </div>
        <div class="aksi-baris justify-end">
          <button type="button" class="btn btn-garis btn-kecil"
                  onclick="document.getElementById('modal-jabatan').classList.remove('terbuka')">Batal</button>
          <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
function ubahJabatan(id, nama, unit) {
  document.getElementById('uj-id').value   = id;
  document.getElementById('uj-nama').value = nama;
  document.getElementById('uj-unit').value = unit;
  document.getElementById('modal-jabatan').classList.add('terbuka');
}
</script>
@endsection
