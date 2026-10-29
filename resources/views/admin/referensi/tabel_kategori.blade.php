<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr>
        <th class="w-[50px]">#</th>
        <th>Nama Kategori</th>
        <th class="tengah">Cakupan</th>
        <th class="tengah">Dipakai</th>
        <th class="tengah w-[80px]">Urutan</th>
        <th class="w-[150px]">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($daftar as $k)
      <tr>
        <td class="angka">{{ $daftar->firstItem() + $loop->index }}</td>
        <td><div class="font-medium">{{ $k->nama }}</div></td>
        <td class="tengah">
          @if($k->untuk_struktur)
            <span class="badge badge-biru">Struktur</span>
          @else
            <span class="badge badge-abu">Umum</span>
          @endif
        </td>
        <td class="tengah teks-kecil teks-redup">
          {{ $k->jabatan_count }} jabatan · {{ $k->users_count }} pegawai
        </td>
        <td class="tengah angka">{{ $k->urutan }}</td>
        <td class="whitespace-nowrap">
          <button type="button" class="btn btn-garis btn-kecil btn-ubah-kategori"
                  data-id="{{ (int) $k->id }}"
                  data-nama="{{ $k->nama }}"
                  data-struktur="{{ $k->untuk_struktur ? '1' : '0' }}"
                  data-urutan="{{ (int) $k->urutan }}">Ubah</button>
          <form method="post" action="{{ route('admin.referensi.aksi', 'kategori') }}"
                class="inline-block" data-aksi-form>
            @csrf
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="{{ (int) $k->id }}">
            <button type="submit" class="btn btn-bahaya btn-kecil"
                    data-konfirmasi="Hapus kategori {{ $k->nama }}?">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" class="tengah teks-redup py-6">
          @if($q !== '')Tidak ada kategori yang cocok dengan “{{ $q }}”.
          @else Belum ada kategori jabatan.@endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $daftar, 'satuan' => 'kategori jabatan'])
