@php
$kelasKategori = [
    'Direktur' => 'direktur', 'Kepala Bidang' => 'kabid', 'Kepala Bagian' => 'kabag',
    'Kepala Seksi' => 'kasi', 'Kepala Sub Bagian' => 'kasubag',
];
@endphp
<ul class="{{ empty($akar) ? '' : 'pohon' }}">
  @foreach($cabang as $j)
  <li>
    <div class="simpul {{ $kelasKategori[$j['kategori']] ?? '' }}">
      <span class="tanda"></span>
      <span class="isi-simpul">
        <span class="nama-jab">{{ $j['nama'] }}</span><br>
        @if($j['pejabat'])
          @foreach($j['pejabat'] as $p)
            <span class="pejabat">{{ $p['nama_lengkap'] }}
              @if($p['nip'])<small>· NIP {{ $p['nip'] }}</small>@endif
            </span><br>
          @endforeach
        @else
          <span class="kosong">— belum terisi —</span>
        @endif
      </span>
      @if(! empty($kelola))
        <span class="aksi-simpul">
          <button type="button" class="btn btn-garis btn-kecil"
                  onclick="ubahJabatan({{ (int) $j['id'] }}, '{{ $j['nama'] }}', '{{ $j['unit_label'] ?? '' }}')">Ubah</button>
          <form method="post" action="{{ url('admin/struktur/aksi') }}"
                onsubmit="return confirm('Hapus jabatan {{ $j['nama'] }} dari struktur?');">
            @csrf
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="{{ (int) $j['id'] }}">
            <button type="submit" class="btn btn-bahaya btn-kecil">Hapus</button>
          </form>
        </span>
      @endif
    </div>
    @if($j['anak'])
      @include('partials.pohon', ['cabang' => $j['anak'], 'kelola' => $kelola ?? false, 'akar' => false])
    @endif
  </li>
  @endforeach
</ul>
