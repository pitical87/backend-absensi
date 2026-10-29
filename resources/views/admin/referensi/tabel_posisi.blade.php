<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr>
        <th class="w-[50px]">#</th>
        <th>Nama Posisi</th>
        <th class="tengah">Tingkat</th>
        <th class="tengah">Dipakai</th>
        <th class="tengah w-[80px]">Urutan</th>
        <th class="w-[150px]">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($daftar as $p)
      @php
          $mulaiTahap = (int) $p->tingkat + 1;
          $ketTahap = $mulaiTahap > 4 ? 'Tanpa persetujuan' : 'Mulai tahap '.$mulaiTahap;
      @endphp
      <tr>
        <td class="angka">{{ $daftar->firstItem() + $loop->index }}</td>
        <td><div class="font-medium">{{ $p->nama }}</div></td>
        <td class="tengah">
          <span class="badge badge-hijau">{{ $p->tingkat }}</span>
          <div class="teks-kecil teks-redup">{{ $ketTahap }}</div>
        </td>
        <td class="tengah teks-kecil teks-redup">{{ $p->users_count }} pegawai</td>
        <td class="tengah angka">{{ $p->urutan }}</td>
        <td class="whitespace-nowrap">
          <button type="button" class="btn btn-garis btn-kecil btn-ubah-posisi"
                  data-id="{{ (int) $p->id }}"
                  data-nama="{{ $p->nama }}"
                  data-tingkat="{{ (int) $p->tingkat }}"
                  data-urutan="{{ (int) $p->urutan }}">Ubah</button>
          <form method="post" action="{{ route('admin.referensi.aksi', 'posisi') }}"
                class="inline-block" data-aksi-form>
            @csrf
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="{{ (int) $p->id }}">
            <button type="submit" class="btn btn-bahaya btn-kecil"
                    data-konfirmasi="Hapus posisi {{ $p->nama }}?">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" class="tengah teks-redup py-6">
          @if($q !== '')Tidak ada posisi yang cocok dengan “{{ $q }}”.
          @else Belum ada posisi jabatan.@endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $daftar, 'satuan' => 'posisi'])
