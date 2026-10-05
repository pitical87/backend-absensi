<section class="kartu">
  <div class="kartu-kepala">
    <h2>
      <span class="w-7 h-7 rounded-lg bg-blue-100 text-[#007afc] flex items-center justify-center shrink-0">
        {!! ikon('gedung', 14) !!}
      </span>
      {{ $unitAktif->nama }}
    </h2>
    <div class="flex flex-wrap items-center gap-1.5">
      <span class="badge badge-biru">{{ (int) $unitAktif->jml_pegawai }} pegawai</span>
      <span class="badge badge-abu">{{ $subAktif->count() }} sub unit</span>
      <button type="button" class="btn btn-garis btn-kecil"
              data-buka-modal="modal-ubah"
              data-unit="{{ (int) $unitAktif->id }}"
              data-nama="{{ $unitAktif->nama }}"
              data-punya-sub="{{ $unitAktif->punya_sub ? 1 : 0 }}"
              data-atasan="{{ (int) $unitAktif->atasan_id }}">
        Ubah Unit
      </button>
      <button type="button" class="btn btn-bahaya btn-kecil"
              data-ajax="hapus_unit" data-id="{{ (int) $unitAktif->id }}"
              data-tab-asal="semua"
              data-konfirmasi="Hapus unit {{ $unitAktif->nama }} beserta seluruh sub unitnya?">Hapus Unit
      </button>
    </div>
  </div>
  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat mt-4" data-ajax="tambah_sub">
    @csrf
    <input type="hidden" name="aksi" value="tambah_sub">
    <input type="hidden" name="unit_kerja_id" value="{{ (int) $unitAktif->id }}">
    <input type="text" name="nama" placeholder="Nama sub unit baru untuk {{ $unitAktif->nama }}…" required>
    @include('admin.unit.pilih_pegawai', [
      'namaField' => 'atasan_id',
      'daftar' => $pegawaiPilihan,
      'terpilih' => 0,
      'labelKosong' => '— Atasan sub unit —',
      'judul' => 'Atasan default pegawai sub unit ini',
    ])
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Sub Unit</button>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Sub Unit</th>
          <th>Atasan Default</th>
          <th class="tengah w-[110px]">Pegawai</th>
          <th class="w-[110px]">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($subAktif as $su)
          <tr>
            <td class="font-medium">{{ $su->nama }}</td>
            <td>
              <div class="flex items-center gap-1.5">
                @include('admin.unit.pilih_pegawai', [
                  'namaField' => 'atasan_id',
                  'daftar' => $pegawaiPilihan,
                  'terpilih' => $su->atasan_id,
                  'labelKosong' => '— Atasan sub unit —',
                  'judul' => 'Atasan default pegawai sub unit ini',
                ])
                <button type="button" class="btn btn-navy btn-kecil"
                        data-ajax="ubah_sub"
                        data-id="{{ (int) $su->id }}"
                        data-nama="{{ $su->nama }}"
                        data-atasan="{{ (int) $su->atasan_id }}"
                        data-unit-kerja-id="{{ (int) $unitAktif->id }}">Simpan
                </button>
              </div>
            </td>
            <td class="tengah angka">{{ (int) $su->jml_pegawai }}</td>
            <td>
              <button type="button" class="btn btn-bahaya btn-kecil"
                      data-ajax="hapus_sub" data-id="{{ (int) $su->id }}"
                      data-nama="{{ $su->nama }}"
                      data-unit-kerja-id="{{ (int) $unitAktif->id }}"
                      data-konfirmasi="Hapus sub unit {{ $su->nama }}?">Hapus
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="tengah teks-redup py-6">Belum ada sub unit pada unit ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  
</section>