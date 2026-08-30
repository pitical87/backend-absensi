@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Rekap Keterlambatan — {{ BULAN_ID[$bulan] . ' ' . $tahun }}</h2>
    <span class="badge badge-biru">{{ count($baris) }} pegawai tercatat</span>
  </div>

  <form method="get" action="{{ url('admin/rekap_keterlambatan') }}" class="bilah-alat">
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

  <div class="petunjuk">Data dihitung dari catatan keterlambatan absensi (menit dibulatkan ke atas).
    Bintang: masuk lebih awal / pulang melewati jam = 5, tepat waktu = 4, pelanggaran efektif setelah toleransi 10 menit menurunkan bintang.</div>
</section>

<section class="kartu">
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Unit / Sub Unit</th>
          <th>Hadir</th>
          <th>Terlambat</th>
          <th>Total Menit Telat</th>
          <th>Terlama (mnt)</th>
          <th>Pulang Awal</th>
          <th>Total Menit Awal</th>
          <th>Bintang Masuk</th>
          <th>Bintang Pulang</th>
          <th>Bintang Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse($baris as $b)
          <tr>
            <td>
              <div class="font-medium">{{ $b->nama_lengkap }}</div>
            </td>
            <td>
              @if($b->unit_nama || $b->sub_nama)
                {{ trim(($b->unit_nama ?? '').($b->unit_nama && $b->sub_nama ? ' / ' : '').($b->sub_nama ?? '')) }}
              @else
                <span class="teks-redup">—</span>
              @endif
            </td>
            <td class="angka">{{ (int) $b->tercatat }}</td>
            <td class="angka">
              @if((int) $b->jumlah_terlambat > 0)
                <span class="badge {{ $b->jumlah_terlambat >= 5 ? 'badge-merah' : ($b->total_menit_telat >= 60 ? 'badge-amber' : 'badge-hijau') }}">
                  {{ (int) $b->jumlah_terlambat }}×</span>
              @else
                <span class="teks-redup">0×</span>
              @endif
            </td>
            <td class="angka">{{ (int) $b->total_menit_telat }}</td>
            <td class="angka">{{ (int) $b->terlama_menit_telat }}</td>
            <td class="angka">{{ (int) $b->jumlah_pulang_awal }}×</td>
            <td class="angka">{{ (int) $b->total_menit_pulang_awal }}</td>
            <td class="angka">{{ $b->rata_bintang_masuk ?? '—' }}</td>
            <td class="angka">{{ $b->rata_bintang_pulang ?? '—' }}</td>
            <td class="angka font-semibold">{{ $b->rata_bintang ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="11" class="tengah teks-redup py-6">Belum ada data keterlambatan pada periode ini.</td></tr>
        @endforelse
      </tbody>
      @if(count($baris) > 0)
      <tfoot>
        <tr class="font-bold">
          <td colspan="2">TOTAL</td>
          <td class="angka">{{ $total['tercatat'] }}</td>
          <td class="angka">{{ $total['terlambat'] }}×</td>
          <td class="angka">{{ $total['menit_telat'] }}</td>
          <td></td>
          <td class="angka">{{ $total['pulang_awal'] }}×</td>
          <td class="angka">{{ $total['menit_awal'] }}</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
      @endif
    </table>
  </div>
</section>

@endsection
