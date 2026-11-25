@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <div>
      <h2>{!! ikon('pegawai') !!} Rekap Absen Per Status Pegawai</h2>
      <p class="teks-kecil teks-redup mt-0.5 mb-0">
        {{ $matriks['judul'] }} · {{ $matriks['periode_label'] }}
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <span class="badge badge-biru">{{ number_format($matriks['total_pegawai'], 0, ',', '.') }} pegawai</span>
      <span class="badge badge-hijau">Rata-rata {{ $matriks['rata_persen'] }}%</span>
    </div>
  </div>

  <form method="get" action="{{ route('admin.rekap_status.index') }}" id="form-filter-status" class="bilah-alat">
    <select name="status_pegawai" title="Status Pegawai">
      <option value="">Semua Status Pegawai</option>
      @foreach($statusList as $sp)
        <option value="{{ $sp }}" {{ $f['status_pegawai'] === $sp ? 'selected' : '' }}>{{ $sp }}</option>
      @endforeach
    </select>

    <select name="bulan" title="Bulan">
      @foreach(BULAN_ID as $i => $nb)
        <option value="{{ $i }}" {{ $i === $f['bulan'] ? 'selected' : '' }}>{{ $nb }}</option>
      @endforeach
    </select>

    <select name="tahun" title="Tahun">
      @for($t = (int) date('Y') + 1; $t >= 2024; $t--)
        <option value="{{ $t }}" {{ $t === $f['tahun'] ? 'selected' : '' }}>{{ $t }}</option>
      @endfor
    </select>

    <select name="unit" title="Unit Kerja">
      <option value="">Semua Unit Kerja</option>
      @foreach($unitList as $uk)
        <option value="{{ (int) $uk->id }}" {{ $f['unit'] === (int) $uk->id ? 'selected' : '' }}>
          {{ $uk->nama }}
        </option>
      @endforeach
    </select>

    <select name="sub" title="Sub Unit">
      <option value="">Semua Sub Unit</option>
      @foreach($subUnitList as $su)
        <option value="{{ (int) $su->id }}" {{ $f['sub'] === (int) $su->id ? 'selected' : '' }}>
          {{ $su->unitKerja->nama ?? 'Tanpa Unit' }} — {{ $su->nama }}
        </option>
      @endforeach
    </select>

    <select name="prof" title="Profesi">
      <option value="">Semua Profesi</option>
      @foreach($profList as $pr)
        <option value="{{ (int) $pr->id }}" {{ $f['prof'] === (int) $pr->id ? 'selected' : '' }}>
          {{ $pr->nama }}
        </option>
      @endforeach
    </select>

    <select name="grup" title="Pengelompokan tabel">
      @foreach($grupPilihan as $kode => $label)
        <option value="{{ $kode }}" {{ $f['grup'] === $kode ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>

    <input type="text" name="q" value="{{ $f['q'] }}" placeholder="Cari nama / NIP…" autocomplete="off">

    <button type="submit" class="btn btn-primer btn-kecil">
      {!! ikon('cari', 14) !!} Tampilkan
    </button>
  </form>

  <div class="aksi-baris mb-4 gap-2">
    <a class="btn btn-navy btn-kecil" data-no-skeleton data-ekspor="{{ route('admin.rekap_status.pdf') }}"
       href="{{ route('admin.rekap_status.pdf') . ($qs ? '?' . $qs : '') }}">
      {!! ikon('cetak', 15) !!} Export PDF
    </a>
    <a class="btn btn-garis btn-kecil" data-no-skeleton data-ekspor="{{ route('admin.rekap_status.excel') }}"
       href="{{ route('admin.rekap_status.excel') . ($qs ? '?' . $qs : '') }}">
      {!! ikon('unduh', 15) !!} Export Excel (.xlsx)
    </a>
    @if($f['status_pegawai'] || $f['unit'] || $f['sub'] || $f['prof'] || $f['q'])
      <a class="btn btn-garis btn-kecil" href="{{ route('admin.rekap_status.index', ['bulan' => $f['bulan'], 'tahun' => $f['tahun']]) }}">
        Reset Filter
      </a>
    @endif
  </div>

  <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
    <span><strong class="text-emerald-700">H</strong> = Hadir</span>
    <span>·</span>
    <span><strong class="text-slate-800">L</strong> = Libur / Minggu</span>
    <span>·</span>
    <span><strong class="text-blue-700">I / S / C / DL</strong> = Izin / Sakit / Cuti / Dinas Luar</span>
    <span>·</span>
    <span><strong class="text-red-700">A</strong> = Alpa</span>
  </div>

  @php
    $hariDalamBulan = $matriks['hari_dalam_bulan'];
    $totalKolom = $hariDalamBulan + 5;
  @endphp

  @forelse($matriks['kelompok'] as $kelompok)
    <div class="overflow-x-auto border border-slate-200 rounded-xl mb-4">
      <table class="w-full border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100 text-slate-800">
            <th rowspan="2" class="border border-slate-300 px-2 py-1.5 text-center w-9">NO</th>
            <th rowspan="2" class="border border-slate-300 px-2.5 py-1.5 text-left min-w-[210px]">N A M A</th>
            <th colspan="{{ $hariDalamBulan }}" class="border border-slate-300 px-2 py-1 text-center tracking-widest">T A N G G A L</th>
            <th rowspan="2" class="border border-slate-300 px-2 py-1.5 text-center w-12">JMLH</th>
            <th rowspan="2" class="border border-slate-300 px-2 py-1.5 text-center w-11">%</th>
            <th rowspan="2" class="border border-slate-300 px-2.5 py-1.5 text-left min-w-[160px]">KET</th>
          </tr>
          <tr class="bg-slate-100 text-slate-800">
            @for($d = 1; $d <= $hariDalamBulan; $d++)
              @php $merah = $matriks['tanggal_meta'][$d]['merah']; @endphp
              <th
                title="{{ $matriks['tanggal_meta'][$d]['ket_libur'] ?? $matriks['tanggal_meta'][$d]['tgl'] }}"
                class="border border-slate-300 px-1 py-1 text-center min-w-[24px] {{ $merah ? 'bg-[#C00000] text-white' : '' }}"
              >{{ $d }}</th>
            @endfor
          </tr>
        </thead>
        <tbody>
          @if($kelompok['nama'] !== '')
            <tr class="bg-slate-50">
              <td colspan="{{ $totalKolom }}" class="border border-slate-300 px-3 py-1.5 font-bold text-slate-800 tracking-wide">
                {{ $kelompok['nama'] }}
              </td>
            </tr>
          @endif
          @foreach($kelompok['pegawai'] as $idx => $p)
            <tr class="hover:bg-slate-50/70">
              <td class="border border-slate-300 px-1.5 py-1 text-center text-slate-600">{{ $idx + 1 }}</td>
              <td class="border border-slate-300 px-2.5 py-1 font-semibold text-slate-800 whitespace-nowrap">
                {{ $p['nama'] }}
                @if($f['status_pegawai'] === '')
                  <span class="ml-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $p['status_pegawai'] }}</span>
                @endif
              </td>
              @foreach($p['sel'] as $cell)
                @php
                  $cls = match ($cell['tipe']) {
                      'hadir' => 'text-emerald-700 font-medium',
                      'libur' => 'bg-slate-50 text-slate-800 font-bold',
                      'alpa' => 'text-red-700 font-bold bg-red-50/40',
                      'izin', 'dinas' => 'text-blue-700 font-bold' . ($cell['gabung'] ? ' bg-blue-50/70 text-[11px]' : ''),
                      default => 'text-slate-300',
                  };
                @endphp
                <td
                  @if($cell['colspan'] > 1) colspan="{{ $cell['colspan'] }}" @endif
                  class="border border-slate-300 px-1 py-1 text-center {{ $cls }}"
                >{{ $cell['teks'] }}</td>
              @endforeach
              <td class="border border-slate-300 px-1.5 py-1 text-center font-semibold text-slate-800">{{ $p['jmlh'] }}</td>
              <td class="border border-slate-300 px-1.5 py-1 text-center font-semibold {{ $p['persen'] >= 80 ? 'text-emerald-700' : ($p['persen'] >= 50 ? 'text-amber-700' : 'text-red-700') }}">
                {{ $p['persen'] }}
              </td>
              <td class="border border-slate-300 px-2 py-1 text-[11px] text-slate-700">{{ $p['ket'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @empty
    <div class="rounded-xl border border-slate-200 bg-slate-50 py-10 text-center text-sm text-slate-500">
      Tidak ada data pegawai sesuai filter yang dipilih.
    </div>
  @endforelse
</section>

<script>
(function () {
  var form = document.getElementById('form-filter-status');
  if (!form) return;

  function sinkronEkspor() {
    var params = new URLSearchParams();
    form.querySelectorAll('select[name], input[name]').forEach(function (el) {
      if (el.value !== '') params.set(el.name, el.value);
    });
    var qs = params.toString();
    document.querySelectorAll('[data-ekspor]').forEach(function (a) {
      a.href = a.dataset.ekspor + (qs ? '?' + qs : '');
    });
  }

  form.addEventListener('change', sinkronEkspor);
  form.addEventListener('input', sinkronEkspor);
  sinkronEkspor();
})();
</script>

@endsection
