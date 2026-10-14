@if($rows === [])
  <tr><td colspan="5" class="tengah teks-redup">Tidak ada absen mencurigakan pada periode ini.</td></tr>
@else
  @foreach($rows as $r)
    <tr>
      <td class="tengah">
        <input type="checkbox" class="checkbox-pilih" data-email="{{ $r['email'] ?? '' }}"
               data-nama="{{ $r['nama'] ?? '' }}" data-user="{{ $r['user_id'] ?? '' }}">
      </td>
      <td>
        <div class="font-semibold">{{ $r['nama'] }}</div>
        <div class="teks-kecil teks-redup">{{ $r['email'] ?? '—' }}</div>
      </td>
      <td>
        @if($r['masuk'])
          <span class="badge badge-amber">{{ jam_singkat($r['masuk']['nilai']) }}</span>
          <div class="teks-kecil teks-redup">{{ $r['masuk']['jumlah'] }} hari</div>
        @else
          —
        @endif
      </td>
      <td>
        @if($r['pulang'])
          <span class="badge badge-amber">{{ jam_singkat($r['pulang']['nilai']) }}</span>
          <div class="teks-kecil teks-redup">{{ $r['pulang']['jumlah'] }} hari</div>
        @else
          —
        @endif
      </td>
      <td>
        @if($r['koord'])
          @php $k = $r['koord']['nilai']; $p = explode(',', $k); $lat = trim($p[0] ?? ''); $lng = trim($p[1] ?? ''); @endphp
          <a href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}" target="_blank"
             class="teks-kecil font-semibold text-navy" rel="noopener">
            {{ number_format($lat, 6, ',', '.') }}, {{ number_format($lng, 6, ',', '.') }}
          </a>
          <div class="teks-kecil teks-redup">{{ $r['koord']['jumlah'] }} kali</div>
        @else
          —
        @endif
      </td>
    </tr>
  @endforeach
@endif
