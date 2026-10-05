@forelse($rows as $p)
  @php
    $listAtasan = ($peta['relasi'][$p->id] ?? collect())->pluck('atasan_id')->all();
  @endphp
  <tr>
    <td class="w-[42px] tengah">
      <input type="checkbox" class="chk-pilih w-auto" value="{{ (int) $p->id }}"
             data-id="{{ (int) $p->id }}" data-nama="{{ $p->nama_lengkap }}"
             aria-label="Pilih {{ $p->nama_lengkap }}">
    </td>
    <td>
      <div class="font-medium">{{ $p->nama_lengkap }}</div>
      <div class="teks-redup text-xs">{{ $p->email }}</div>
    </td>
    <td>
      @if($p->unit_nama || $p->sub_nama)
        {{ trim(($p->unit_nama ?? '').($p->unit_nama && $p->sub_nama ? ' / ' : '').($p->sub_nama ?? '')) }}
      @else
        <span class="teks-redup">—</span>
      @endif
    </td>
    <td>
      @if(count($listAtasan) > 0)
        <div class="flex flex-wrap gap-1">
          @foreach($listAtasan as $idA)
            <span class="badge badge-hijau">{{ $peta['nama'][$idA] ?? '#'.$idA }}</span>
          @endforeach
        </div>
      @else
        <span class="badge badge-amber">Belum diatur</span>
      @endif
    </td>
    <td class="w-[150px]">
      <button type="button" class="btn btn-navy btn-kecil" data-atur="{{ (int) $p->id }}"
              data-atasan="{{ implode(',', array_map('intval', $listAtasan)) }}">
        Atur
      </button>
    </td>
  </tr>
@empty
  <tr><td colspan="5" class="tengah teks-redup py-6">Tidak ada pegawai yang cocok.</td></tr>
@endforelse