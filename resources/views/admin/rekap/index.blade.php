@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Rekap Kehadiran Bulan {{ BULAN_ID[$bulan] . ' ' . $tahun }}</h2>
    <span class="badge badge-biru">{{ count($pegawai) }} pegawai</span>
  </div>

  <form method="get" action="{{ url('admin/rekap') }}" class="bilah-alat">
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
    <select name="jab" title="Kategori jabatan">
      <option value="">Semua Jabatan</option>
      @foreach($kategoriJab as $k)
        <option {{ $f['jab'] === $k ? 'selected' : '' }}>{{ $k }}</option>
      @endforeach
    </select>
    <select name="njab" title="Nama jabatan tertentu">
      <option value="">Semua Nama Jabatan</option>
      @foreach($jabPilihan as $kat => $daftar)
        <optgroup label="{{ $kat }}">
          @foreach($daftar as $j)
            <option value="{{ (int) $j['id'] }}" {{ $f['njab'] === (int) $j['id'] ? 'selected' : '' }}>
              {{ $j['nama'] }}</option>
          @endforeach
        </optgroup>
      @endforeach
    </select>
    <select name="org" title="Bidang / Bagian pada struktur organisasi">
      <option value="">Semua Bidang/Bagian</option>
      @foreach($orgList as $o)
        <option value="{{ (int) $o['id'] }}" {{ $f['org'] === (int) $o['id'] ? 'selected' : '' }}>
          {{ $o['unit_label'] }}</option>
      @endforeach
    </select>
    <select name="prof">
      <option value="">Semua Profesi</option>
      @foreach($profList as $pr)
        <option value="{{ (int) $pr->id }}" {{ $f['prof'] === (int) $pr->id ? 'selected' : '' }}>
          {{ $pr->nama }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>
  {{-- <p class="petunjuk">Filter Bidang/Bagian mencakup pejabat struktural di dalamnya (Kabid/Kabag
    beserta Kasi/Kasubag bawahannya). Filter dapat digabung dan ikut terbawa ke Cetak PDF
    maupun Export Excel.</p> --}}

  <div class="aksi-baris mb-3.5">
    <form method="post" action="{{ url('admin/rekap/generate') }}">
      @csrf
      <input type="hidden" name="bulan" value="{{ $bulan }}">
      <input type="hidden" name="tahun" value="{{ $tahun }}">
      <input type="hidden" name="unit" value="{{ $fUnit }}">
      <input type="hidden" name="jab" value="{{ $f['jab'] }}">
      <input type="hidden" name="njab" value="{{ $f['njab'] }}">
      <input type="hidden" name="org" value="{{ $f['org'] }}">
      <input type="hidden" name="prof" value="{{ $f['prof'] }}">
      <button type="submit" class="btn btn-primer btn-kecil">{!! ikon('centang', 15) !!} Generate &amp; Simpan Rekap</button>
    </form>
    <a class="btn btn-navy btn-kecil" target="_blank"
       href="{{ url('admin/rekap/cetak?' . $qs) }}">{!! ikon('cetak', 15) !!} Cetak Laporan (PDF)</a>
    <a class="btn btn-garis btn-kecil"
       href="{{ url('admin/rekap/excel?' . $qs . '&mode=rekap') }}">{!! ikon('unduh', 15) !!} Export Excel (Rekap)</a>
    <a class="btn btn-garis btn-kecil"
       href="{{ url('admin/rekap/excel?' . $qs . '&mode=detail') }}">{!! ikon('unduh', 15) !!} Export Excel (Detail Harian)</a>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Nama Pegawai</th><th>Unit</th><th>Hari Efektif</th><th>Hadir</th>
          <th>Tepat</th><th>Telat</th><th>Alpa</th><th>Izin</th><th>Sakit</th>
          <th>Cuti</th><th>Dinas</th><th>Total Jam</th><th>%</th><th title="Rata-rata bintang ketepatan waktu (pegawai teladan = bintang 5)">Bintang</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pegawai as $p) @php $r = $rekapPer[(int) $p->id]; @endphp
        <tr>
          <td><strong>{{ $p->nama_lengkap }}</strong><br>
              <span class="teks-kecil teks-redup">{{ $p->jabatan_nama
                    ?? ($p->jabatan_kategori ?? '') }}@if(
                    $p->profesi_nama) · {{ $p->profesi_nama }}@endif</span></td>
          <td>{{ $p->unit_nama ?? '—' }}@if($p->sub_nama) — {{ $p->sub_nama }}@endif</td>
          <td class="angka">{{ $r['hari_efektif'] }}</td>
          <td class="angka">{{ $r['hadir'] }}</td>
          <td class="angka"><span class="badge badge-hijau">{{ $r['tepat'] }}</span></td>
          <td class="angka"><span class="badge badge-amber">{{ $r['terlambat'] }}</span></td>
          <td class="angka"><span class="badge badge-merah">{{ $r['alpa'] }}</span></td>
          <td class="angka">{{ $r['izin'] }}</td>
          <td class="angka">{{ $r['sakit'] }}</td>
          <td class="angka">{{ $r['cuti'] }}</td>
          <td class="angka">{{ $r['dinas_luar'] }}</td>
          <td class="angka">{{ menit_ke_teks($r['total_menit']) }}</td>
          <td class="angka"><strong>{{ $r['persen'] }}%</strong></td>
          <td class="angka">
            @if($r['bintang_bulanan'] === null)
              <span class="teks-redup">—</span>
            @else
              @php $bi = app(\App\Services\BintangService::class); @endphp
              <span title="{{ number_format((float) $r['bintang_bulanan'], 1) }} dari 5"
                    class="{{ (float) $r['bintang_bulanan'] >= 4.5 ? 'teks-bintang' : 'teks-redup' }}">
                {{ $bi->simbol((int) round((float) $r['bintang_bulanan'], 0, PHP_ROUND_HALF_UP)) }}
              </span>
              @if((float) $r['bintang_bulanan'] >= 4.5)
                <span class="badge badge-emas">Teladan</span>
              @endif
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $pegawai)
        <tr><td colspan="14" class="tengah teks-redup">Tidak ada pegawai pada filter ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  {{-- <p class="petunjuk">Angka dihitung langsung dari data absensi, izin yang disetujui, dan kalender
    hari libur. <strong>Bintang</strong> = rata-rata bintang harian {{ '(bintang masuk + bintang pulang) / 2' }}
    bulan ini (alpa = 0 bintang); rata-rata ≥ 4.5 diberi tanda <span class="badge badge-emas">Teladan</span>.
    <em>Generate &amp; Simpan Rekap</em> menyimpan salinan ke tabel arsip
    <code>rekap_bulanan</code> — juga tersedia bagi SIMRS melalui API.</p> --}}
</section>

@endsection
