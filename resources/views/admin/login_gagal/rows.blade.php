@php
  $warnaLevel = [
    'low'         => 'bg-slate-100 text-slate-600 border border-slate-200/60',
    'mencurigakan' => 'bg-amber-50 text-amber-700 border border-amber-200/60',
    'medium'      => 'bg-orange-50 text-orange-700 border border-orange-200/60',
    'danger'      => 'bg-red-50 text-red-700 border border-red-200/60',
    'danger2'     => 'bg-red-600 text-white border border-red-700',
  ];
  $labelLevel = [
    'low'         => 'Low',
    'mencurigakan' => 'Mencurigakan',
    'medium'      => 'Medium',
    'danger'      => 'Danger',
    'danger2'     => 'Danger 2',
  ];
@endphp

@forelse($rows as $g)
  <tr>
    <td>
      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[0.7rem] font-bold tracking-wide whitespace-nowrap {{ $warnaLevel[$g['level']] ?? $warnaLevel['low'] }}">
        {{ $labelLevel[$g['level']] ?? $g['level'] }}
      </span>
    </td>

    <td>
      @if($g['kunci'] === 'email')
        <strong class="block text-slate-800">{{ $g['nama'] ?? 'Akun tidak dikenal' }}</strong>
        <span class="teks-kecil teks-redup angka">{{ $g['email'] }}</span>
      @else
        <strong class="block text-slate-800">Tanpa email</strong>
        <span class="teks-kecil teks-redup">Ditebak dari IP</span>
      @endif
    </td>

    <td class="tengah angka font-bold">{{ $g['gagal'] }}×</td>

    <td class="tengah angka" title="Jumlah IP / email target berbeda">
      {{ $g['kunci'] === 'email' ? $g['jml_ip'] : $g['jml_email'] }}
    </td>

    <td class="tengah angka" title="Jumlah perangkat / user agent berbeda">{{ $g['jml_perangkat'] }}</td>

    <td class="teks-kecil angka">{{ $g['ip'] ?? '—' }}</td>

    <td class="log-waktu angka">{{ tgl_id($g['terakhir'], false) }} · {{ jam_id($g['terakhir']) }}</td>

    <td class="tengah">
      @if($g['kunci'] === 'email' && ! empty($g['user_id']))
        @if($g['terblokir'])
          <span class="badge badge-abu">Diblokir</span>
          <button type="button" class="btn btn-garis btn-kecil mt-1" data-buka="{{ $g['user_id'] }}">Buka Blokir</button>
        @else
          <span class="badge badge-hijau">Aktif</span>
          <button type="button" class="btn btn-garis btn-kecil mt-1" data-blokir="{{ $g['user_id'] }}" data-nama="{{ $g['nama'] ?? $g['email'] }}">Blokir</button>
        @endif
      @else
        <span class="teks-kecil teks-redup">—</span>
      @endif
    </td>
  </tr>
@empty
  <tr>
    <td colspan="8" class="tengah teks-redup">
      @if($rows->total() > 0)
        Tidak ada hasil untuk filter ini.
      @else
        Tidak ada percobaan login gagal pada rentang ini. Bagus.
      @endif
    </td>
  </tr>
@endforelse
