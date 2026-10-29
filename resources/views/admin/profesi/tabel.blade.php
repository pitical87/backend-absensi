<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr>
        <th class="w-[50px]">#</th>
        <th>Nama Profesi</th>
        <th class="tengah">Pegawai</th>
        <th class="w-[150px]">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($daftar as $p)
      <tr>
        <td class="angka">{{ $daftar->firstItem() + $loop->index }}</td>
        <td><div class="font-medium">{{ $p->nama }}</div></td>
        <td class="tengah angka">{{ $p->users_count }} pegawai</td>
        <td class="whitespace-nowrap">
          <button type="button" class="btn btn-garis btn-kecil btn-ubah-profesi"
                  data-id="{{ (int) $p->id }}"
                  data-nama="{{ $p->nama }}">Ubah</button>
          <form method="post" action="{{ route('admin.profesi.aksi') }}"
                class="inline-block" data-aksi-form>
            @csrf
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="{{ (int) $p->id }}">
            <button type="submit" class="btn btn-bahaya btn-kecil"
                    data-konfirmasi="Hapus profesi {{ $p->nama }}?">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" class="tengah teks-redup py-6">
          @if($q !== '')Tidak ada profesi yang cocok dengan “{{ $q }}”.
          @else Belum ada profesi terdaftar.@endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $daftar, 'satuan' => 'profesi'])
