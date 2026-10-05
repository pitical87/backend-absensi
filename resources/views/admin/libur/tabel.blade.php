@php $tetap = hari_libur_tetap((int) $tahun); @endphp
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th class="w-[64px]">#</th><th>Tanggal</th><th>Keterangan</th><th class="w-[100px]">Aksi</th></tr></thead>
    <tbody>
      @forelse($daftar as $h)
      <tr>
        <td class="angka">{{ $daftar->firstItem() + $loop->index }}</td>
        <td class="angka">{{ tgl_id($h->tanggal) }}</td>
        <td>{{ $h->keterangan }}
          @if(isset($tetap[$h->tanggal->format('Y-m-d')]))
            <span class="badge badge-teal teks-kecil">Otomatis</span>
          @endif
        </td>
        <td class="whitespace-nowrap">
          <button type="button" class="btn btn-garis btn-kecil btn-ubah-libur"
                  data-id="{{ (int) $h->id }}"
                  data-tanggal="{{ $h->tanggal->format('Y-m-d') }}"
                  data-keterangan="{{ $h->keterangan }}">Ubah</button>
          <form method="post" action="{{ route('admin.libur.aksi') }}" class="inline-block" data-aksi-form>
            @csrf
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="tahun" value="{{ (int) $tahun }}">
            <input type="hidden" name="id" value="{{ (int) $h->id }}">
            <button type="submit" class="btn btn-bahaya btn-kecil" data-konfirmasi="Hapus hari libur ini?">Hapus</button>
          </form>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" class="tengah teks-redup">
          @if($q !== '')Tidak ada hari libur pada tahun {{ $tahun }} yang cocok dengan “{{ $q }}”.
          @else Belum ada hari libur terdaftar pada tahun {{ $tahun }}.@endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $daftar, 'satuan' => 'hari libur'])