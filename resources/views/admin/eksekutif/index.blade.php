@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('grafik') !!} Dashboard Eksekutif</h2>
    <span class="badge badge-biru">{{ $labelPeriode }}</span>
  </div>

  <form method="get" action="{{ url('admin/eksekutif') }}" class="bilah-alat flex-wrap">
    <select name="mode">
      <option value="tren" {{ $f['mode'] === 'tren' ? 'selected' : '' }}>Tren Tahunan</option>
      <option value="bulan" {{ $f['mode'] === 'bulan' ? 'selected' : '' }}>Per Bulan</option>
      <option value="minggu" {{ $f['mode'] === 'minggu' ? 'selected' : '' }}>Per Minggu</option>
      <option value="unit" {{ $f['mode'] === 'unit' ? 'selected' : '' }}>Perbandingan Unit</option>
    </select>
    @if($f['mode'] === 'tren')
      <select name="tahun">
        @for($t = (int) date('Y'); $t >= 2024; $t--)
          <option {{ $t === $f['tahun'] ? 'selected' : '' }}>{{ $t }}</option>
        @endfor
      </select>
    @elseif($f['mode'] === 'bulan')
      <select name="bulan">
        @foreach(BULAN_ID as $i => $nb)
          <option value="{{ $i }}" {{ $i === $f['bulan'] ? 'selected' : '' }}>{{ $nb }}</option>
        @endforeach
      </select>
      <select name="tahun">
        @for($t = (int) date('Y'); $t >= 2024; $t--)
          <option {{ $t === $f['tahun'] ? 'selected' : '' }}>{{ $t }}</option>
        @endfor
      </select>
    @elseif($f['mode'] === 'minggu')
      <select name="minggu">
        @foreach($mingguList as $mg)
          <option value="{{ $mg['minggu'] }}" {{ $mg['minggu'] === $f['minggu'] ? 'selected' : '' }}>{{ $mg['label'] }}</option>
        @endforeach
      </select>
      <select name="tahun">
        @for($t = (int) date('Y'); $t >= 2024; $t--)
          <option {{ $t === $f['tahun'] ? 'selected' : '' }}>{{ $t }}</option>
        @endfor
      </select>
    @else
      <input type="date" name="dari" value="{{ $f['dari'] }}">
      <span class="teks-redup teks-kecil">s/d</span>
      <input type="date" name="sampai" value="{{ $f['sampai'] }}">
    @endif
    <select name="unit">
      <option value="">Semua Unit</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $f['unit'] === (int) $uk->id ? 'selected' : '' }}>{{ $uk->nama }}</option>
      @endforeach
    </select>
    <select name="sub">
      <option value="">Semua Sub Unit</option>
      @foreach($subUnitList as $su)
        <option value="{{ (int) $su->id }}" {{ $f['sub'] === (int) $su->id ? 'selected' : '' }}>{{ $su->unitKerja->nama ?? 'Tanpa Unit' }} — {{ $su->nama }}</option>
      @endforeach
    </select>
    <select name="jab" title="Kategori jabatan">
      <option value="">Semua Jabatan</option>
      @foreach($kategoriJab as $k)
        <option {{ $f['jab'] === $k ? 'selected' : '' }}>{{ $k }}</option>
      @endforeach
    </select>
    <select name="prof" title="Profesi">
      <option value="">Semua Profesi</option>
      @foreach(\App\Models\Profesi::orderBy('nama')->get()->all() as $pr)
        <option value="{{ (int) $pr->id }}" {{ $f['prof'] === (int) $pr->id ? 'selected' : '' }}>{{ $pr->nama }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
  </form>

  <div class="aksi-baris mb-3.5">
    @php
      $sqs = http_build_query(array_filter([
        'mode' => $f['mode'],
        'tahun' => in_array($f['mode'], ['tren', 'bulan', 'minggu'], true) ? $f['tahun'] : null,
        'bulan' => $f['mode'] === 'bulan' ? $f['bulan'] : null,
        'minggu' => $f['mode'] === 'minggu' ? $f['minggu'] : null,
        'dari' => $f['mode'] === 'unit' ? $f['dari'] : null,
        'sampai' => $f['mode'] === 'unit' ? $f['sampai'] : null,
        'unit' => $f['unit'] ?: null, 'sub' => $f['sub'] ?: null,
        'jab' => $f['jab'] ?: null, 'prof' => $f['prof'] ?: null,
      ]));
    @endphp
    <a class="btn btn-garis btn-kecil" target="__blank" href="{{ url('admin/eksekutif/cetak?' . $sqs) }}">{!! ikon('cetak', 15) !!} Cetak Laporan (PDF)</a>
  </div>

  @if($ringkasan)
  <div class="stat-grid">
    @php $rr = $ringkasan; @endphp
    <div class="stat">
      <span>Total Pegawai</span><strong>{{ $rr['total_pegawai'] }}</strong>
    </div>
    <div class="stat hijau">
      <span>Kehadiran</span><strong>{{ number_format($rr['absensi']['hadir'], 0, ',', '.') }}</strong>
    </div>
    <div class="stat amber">
      <span>Keterlambatan</span><strong>{{ number_format($rr['absensi']['terlambat'], 0, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Izin / Cuti</span><strong>{{ $rr['izin']['disetujui'] }}</strong>
    </div>
    <div class="stat">
      <span>Jam Lembur (disetujui)</span><strong>{{ number_format($rr['lembur']['total_jam'], 1, ',', '.') }}</strong>
    </div>
    <div class="stat">
      <span>Entri Logbook</span><strong>{{ number_format($rr['logbook']['jumlah'], 0, ',', '.') }}</strong>
    </div>
  </div>
  @endif

  @if($f['mode'] === 'tren' && $tren)
    <div class="teks-redup teks-kecil mb-2">Tren Kehadiran per Bulan — Tahun {{ $f['tahun'] }}</div>
    @php
      $maks = max(1, ...array_column($tren, 'hadir'));
    @endphp
    <div class="grafik-batang" style="display:flex;align-items:flex-end;gap:6px;height:170px;padding:8px 0">
      @foreach($tren as $t)
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
          <span class="teks-kecil" style="font-size:9px">{{ $t['hadir'] }}</span>
          <div class="batang" style="width:100%;height:{{ round($t['hadir'] * 100 / $maks) }}%;background:#1568B8;border-radius:3px 3px 0 0" title="{{ $t['label'] }}: {{ $t['hadir'] }} hadir"></div>
          <span class="teks-redup teks-kecil" style="font-size:8px">{{ $t['label'] }}</span>
        </div>
      @endforeach
    </div>

    <div class="tabel-bungkus" style="margin-top:14px">
      <table class="tabel">
        <thead>
          <tr>
            <th>Bulan</th><th class="angka">Hadir</th><th class="angka">Tepat</th>
            <th class="angka">Terlambat</th><th class="angka">Izin Pelaksanaan</th>
            <th class="angka">Jam Lembur</th><th class="angka">Logbook</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tren as $t)
          <tr>
            <td>{{ $t['label'] }}</td>
            <td class="angka">{{ $t['hadir'] }}</td>
            <td class="angka">{{ $t['tepat'] }}</td>
            <td class="angka">{{ $t['terlambat'] }}</td>
            <td class="angka">{{ $t['izin'] }}</td>
            <td class="angka">{{ number_format($t['jam_lembur'], 1, ',', '.') }}</td>
            <td class="angka">{{ $t['logbook'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @if(in_array($f['mode'], ['bulan', 'minggu'], true))
    @if($harian)
    <div class="teks-redup teks-kecil mb-2">Tren Kehadiran Harian — {{ $labelPeriode }}</div>
    @php
      $maksHarian = max(1, ...array_column($harian, 'hadir'));
      $jarakBatang = $f['mode'] === 'bulan' ? 3 : 6;
    @endphp
    <div class="grafik-batang" style="display:flex;align-items:flex-end;gap:{{ $jarakBatang }}px;height:170px;padding:8px 0">
      @foreach($harian as $h)
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
          <span class="teks-kecil" style="font-size:8px">{{ $h['hadir'] }}</span>
          <div class="batang" style="width:100%;height:{{ round($h['hadir'] * 100 / $maksHarian) }}%;background:#1568B8;border-radius:3px 3px 0 0" title="{{ $h['hari'] }}, {{ $h['label'] }}: {{ $h['hadir'] }} hadir"></div>
          <span class="teks-redup teks-kecil" style="font-size:7px">{{ $f['mode'] === 'bulan' ? (int) date('j', strtotime($h['tanggal'])) : substr($h['hari'], 0, 3) }}</span>
        </div>
      @endforeach
    </div>
    @endif

    <div class="teks-redup teks-kecil mb-2" style="margin-top:14px">Rincian Harian — {{ $labelPeriode }}</div>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead>
          <tr>
            <th>Tanggal</th><th class="angka">Hadir</th><th class="angka">Tepat</th>
            <th class="angka">Terlambat</th><th class="angka">Izin Pelaksanaan</th>
            <th class="angka">Jam Lembur</th><th class="angka">Logbook</th>
          </tr>
        </thead>
        <tbody>
          @foreach($harian as $h)
          <tr>
            <td>{{ $h['hari'] }}, {{ $h['label'] }}</td>
            <td class="angka">{{ $h['hadir'] }}</td>
            <td class="angka">{{ $h['tepat'] }}</td>
            <td class="angka">{{ $h['terlambat'] }}</td>
            <td class="angka">{{ $h['izin'] }}</td>
            <td class="angka">{{ number_format($h['jam_lembur'], 1, ',', '.') }}</td>
            <td class="angka">{{ $h['logbook'] }}</td>
          </tr>
          @endforeach
          @if(! $harian)
          <tr><td colspan="7" class="tengah teks-redup">Tidak ada data pada periode ini.</td></tr>
          @endif
        </tbody>
      </table>
    </div>
  @endif

  @if($f['mode'] === 'unit')
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead>
          <tr>
            <th>Unit Kerja</th><th class="angka">Pegawai</th><th class="angka">Hadir</th>
            <th class="angka">Terlambat</th><th class="angka">Izin Pelaksanaan</th>
            <th class="angka">Jam Lembur</th><th class="angka">Logbook</th>
          </tr>
        </thead>
        <tbody>
          @foreach($perUnit as $u)
          <tr>
            <td>{{ $u['unit_nama'] }}</td>
            <td class="angka">{{ $u['total_pegawai'] }}</td>
            <td class="angka">{{ $u['hadir'] }}</td>
            <td class="angka">{{ $u['terlambat'] }}</td>
            <td class="angka">{{ $u['izin'] }}</td>
            <td class="angka">{{ number_format($u['jam_lembur'], 1, ',', '.') }}</td>
            <td class="angka">{{ $u['logbook'] }}</td>
          </tr>
          @endforeach
          @if(! array_filter(array_column($perUnit, 'hadir')))
          <tr><td colspan="7" class="tengah teks-redup">Tidak ada data pada periode ini.</td></tr>
          @endif
        </tbody>
      </table>
    </div>
  @endif
</section>

@endsection
