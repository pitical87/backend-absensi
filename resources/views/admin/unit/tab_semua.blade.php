<section class="kartu">
  <div class="kartu-kepala">
    <h2>Daftar Unit Kerja</h2>
    <span class="badge badge-abu">{{ $unitList->count() }} unit</span>
  </div>

  @if($unitList->isEmpty())
    <div class="py-10 tengah teks-redup">
      Belum ada unit kerja. Tekan <strong>Tambah Unit</strong> di atas untuk membuat unit pertama.
    </div>
  @else
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead>
          <tr>
            <th>Unit Kerja</th>
            <th class="tengah">Sub Unit</th>
            <th class="tengah">Pegawai</th>
            <th>Atasan Default</th>
            <th class="w-[240px]">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($unitList as $uk)
            <tr>
              <td>
                <div class="font-medium">{{ $uk->nama }}</div>
                @if($uk->punya_sub)
                  <div class="teks-kecil teks-redup">memiliki sub unit</div>
                @endif
              </td>
              <td class="tengah angka">{{ (int) $uk->jml_sub }}</td>
              <td class="tengah angka">{{ (int) $uk->jml_pegawai }}</td>
              <td class="teks-kecil">
                {{ $pegawaiPilihan->firstWhere('id', (int) $uk->atasan_id)->nama_lengkap ?? '— belum diatur —' }}
              </td>
              <td>
                <div class="flex flex-wrap gap-1.5">
                  <a href="{{ url('admin/unit') }}?tab={{ (int) $uk->id }}" data-tab="{{ (int) $uk->id }}"
                     class="btn btn-navy btn-kecil no-underline">Sub Unit</a>
                  <button type="button" class="btn btn-garis btn-kecil"
                          data-buka-modal="modal-ubah"
                          data-unit="{{ (int) $uk->id }}"
                          data-nama="{{ $uk->nama }}"
                          data-punya-sub="{{ $uk->punya_sub ? 1 : 0 }}"
                          data-atasan="{{ (int) $uk->atasan_id }}">
                    Ubah
                  </button>
                  <button type="button" class="btn btn-bahaya btn-kecil"
                          data-ajax="hapus_unit" data-id="{{ (int) $uk->id }}"
                          data-tab-asal="semua"
                          data-konfirmasi="Hapus unit {{ $uk->nama }} beserta seluruh sub unitnya?">Hapus
                  </button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>