@extends('layouts.admin')

@php
  $podium = $baris->take(3);
  $medali = [
    ['lingkar' => 'bg-gradient-to-br from-[#F2C94C] to-[#B8860B]', 'kartu' => 'from-[#FFF9E6] to-[#FFEFC2] border-[#F2C94C]', 'label' => 'Juara 1', 'ikon' => '🥇'],
    ['lingkar' => 'bg-gradient-to-br from-[#C0C7D1] to-[#8A94A6]', 'kartu' => 'from-[#F5F7FA] to-[#E4E9F1] border-[#AEB8C6]', 'label' => 'Juara 2', 'ikon' => '🥈'],
    ['lingkar' => 'bg-gradient-to-br from-[#E0965A] to-[#A05A2C]', 'kartu' => 'from-[#FBF1E7] to-[#F3DEC9] border-[#D29A63]', 'label' => 'Juara 3', 'ikon' => '🥉'],
  ];
  $inisial = function ($nama) {
    $kata = preg_split('/\s+/', trim($nama));
    return strtoupper(substr($kata[0], 0, 1) . (isset($kata[1]) ? substr($kata[1], 0, 1) : ''));
  };
  $maks = max((float) ($baris->first()->total_bintang ?? 0), 1);
@endphp

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('bintang') !!} Pegawai Teladan — {{ BULAN_ID[$bulan] . ' ' . $tahun }}</h2>
    <span class="badge badge-biru">{{ count($baris) }} pegawai terdata</span>
  </div>

  <form method="get" action="{{ url('admin/pegawai_teladan') }}" class="bilah-alat">
    <select name="bulan">
      @foreach(BULAN_ID as $i => $nb)
        <option value="{{ $i }}" {{ $i === $bulan ? 'selected' : '' }}>{{ $nb }}</option>
      @endforeach
    </select>
    <select name="tahun">
      @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
        <option {{ $t === $tahun ? 'selected' : '' }}>{{ $t }}</option>
      @endfor
    </select>
    <select name="unit">
      <option value="">Semua Unit Kerja</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $fUnit === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="petunjuk">Peringkat dihitung dari akumulasi total bintang absensi selama sebulan penuh
    (bintang masuk + pulang per hari: 5 = lebih awal/melewati jam, 4 = tepat waktu, menurun bila terlambat melewati toleransi).</div>
</section>

@if(count($podium) === 0)
<section class="kartu">
  <div class="tengah py-10 teks-redup">
    {!! ikon('bintang') !!}
    <p class="font-medium">Belum ada data bintang pada periode ini.</p>
    <p class="text-sm mt-1">Pilih bulan lain atau tunggu absensi terisi.</p>
  </div>
</section>
@else

<section class="grid gap-4 md:grid-cols-{{ min(count($podium), 3) }} mb-5">
  @foreach($podium as $i => $p)
    @php($m = $medali[$i])
    <div class="rounded-2xl border-2 bg-gradient-to-b {{ $m['kartu'] }} p-5 shadow-md relative overflow-hidden">
      <span class="absolute -right-4 -top-5 text-[90px] leading-none opacity-15 select-none">{{ $i + 1 }}</span>
      <div class="flex items-center gap-4">
        <div class="{{ $m['lingkar'] }} w-16 h-16 rounded-full flex items-center justify-center text-white text-xl font-bold shadow-inner ring-4 ring-white/60 shrink-0">
          {{ $inisial($p->nama_lengkap) }}
        </div>
        <div class="min-w-0">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $m['ikon'] }} {{ $m['label'] }}</div>
          <div class="font-bold text-lg truncate" title="{{ $p->nama_lengkap }}">{{ $p->nama_lengkap }}</div>
          <div class="text-sm text-slate-500 truncate">
            @if($p->unit_nama || $p->sub_nama)
              {{ trim(($p->unit_nama ?? '').($p->unit_nama && $p->sub_nama ? ' / ' : '').($p->sub_nama ?? '')) }}
            @else
              &mdash;
            @endif
          </div>
        </div>
      </div>
      <div class="mt-4 flex items-end justify-between">
        <div>
          <div class="text-4xl font-extrabold text-slate-800 leading-none">{{ number_format((float) $p->total_bintang, 1) }}</div>
          <div class="text-sm text-slate-500 mt-1">total bintang · rata-rata {{ number_format((float) $p->rata_bintang, 2) }}/hari</div>
        </div>
        <div class="text-right text-sm space-y-1">
          <div><span class="badge badge-hijau">{{ (int) $p->hari_tercatat }} hari hadir</span></div>
          <div><span class="badge {{ (int) $p->jumlah_telat > 0 ? 'badge-amber' : 'badge-biru' }}">{{ (int) $p->jumlah_telat }}× telat</span></div>
        </div>
      </div>
    </div>
  @endforeach
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h3>Daftar Peringkat Lengkap</h3>
    <span class="badge badge-abu">skala maksimum {{ number_format($maks, 1) }} bintang</span>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>#</th>
          <th>Pegawai</th>
          <th>Unit / Sub Unit</th>
          <th>Hadir</th>
          <th>Total Bintang</th>
          <th>Rata-rata</th>
          <th>★5</th>
          <th>Telat</th>
          <th>Pulang Awal</th>
        </tr>
      </thead>
      <tbody>
        @forelse($baris as $i => $b)
          <tr class="{{ $i < 3 ? 'bg-amber-50/50' : '' }}">
            <td>
              @if($i < 3)
                <span class="{{ $medali[$i]['lingkar'] }} inline-flex items-center justify-center w-7 h-7 rounded-full text-white text-sm font-bold shadow">{{ $i + 1 }}</span>
              @else
                <span class="font-semibold teks-redup">{{ $i + 1 }}</span>
              @endif
            </td>
            <td>
              <div class="font-medium">{{ $b->nama_lengkap }}</div>
            </td>
            <td>
              @if($b->unit_nama || $b->sub_nama)
                {{ trim(($b->unit_nama ?? '').($b->unit_nama && $b->sub_nama ? ' / ' : '').($b->sub_nama ?? '')) }}
              @else
                <span class="teks-redup">&mdash;</span>
              @endif
            </td>
            <td class="angka">{{ (int) $b->hari_tercatat }}</td>
            <td class="w-48">
              <div class="flex items-center gap-2">
                <span class="font-bold whitespace-nowrap">{{ number_format((float) $b->total_bintang, 1) }}</span>
                <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full bg-gradient-to-r from-[#F2C94C] to-[#F7DE86]" style="width: {{ round(((float) $b->total_bintang / $maks) * 100) }}%"></div>
                </div>
              </div>
            </td>
            <td class="angka">{{ number_format((float) $b->rata_bintang, 2) }}</td>
            <td class="angka">{{ (int) $b->hari_bintang_lima }}×</td>
            <td class="angka">{{ (int) $b->jumlah_telat }}× <span class="teks-redup">({{ (int) $b->total_menit_telat }}')</span></td>
            <td class="angka">{{ (int) $b->jumlah_pulang_awal }}×</td>
          </tr>
        @empty
          <tr><td colspan="9" class="tengah teks-redup py-6">Tidak ada data untuk ditampilkan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@endsection
