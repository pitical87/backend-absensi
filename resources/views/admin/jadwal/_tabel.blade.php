@php
  $mulai = $total > 0 ? $offset + 1 : 0;
  $sampai = $offset + $pegawaiBertugas->count();
  $jendela = 2;
  $halAwal = max(1, $page - $jendela);
  $halAkhir = min($lastPage, $page + $jendela);
@endphp

@if($total === 0)
  <div class="tabel-bungkus overflow-x-auto">
    <table id="tabel-data-jadwal" class="tabel jadwal-grid" style="min-width:{{ 210 + ($hariDalamBulan * 46) }}px">
      <thead>
        <tr>
          <th class="angka min-w-[36px]">No</th>
          <th class="sticky left-0 bg-white z-[2] min-w-[150px]">Nama Pegawai</th>
          @for($d = 1; $d <= $hariDalamBulan; $d++)
            @php
              $dtD = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
            @endphp
            <th class="angka min-w-[40px]" style="{{ $dtD->dayOfWeek === Carbon\Carbon::SUNDAY ? 'color:var(--merah)' : '' }}"
                title="{{ $dtD->translatedFormat('l') }}">{{ $d }}</th>
          @endfor
        </tr>
      </thead>
      <tbody>
        <tr>
          <td colspan="{{ $hariDalamBulan + 2 }}" class="tengah teks-redup py-[30px]">
            {{ $cari !== '' ? 'Tidak ada jadwal yang cocok dengan pencarian "'.$cari.'".' : 'Belum ada jadwal tersimpan pada periode ini.' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
@else
  <div class="tabel-bungkus overflow-x-auto">
    <table id="tabel-data-jadwal" class="tabel jadwal-grid" style="min-width:{{ 210 + ($hariDalamBulan * 46) }}px">
      <thead>
        <tr>
          <th class="angka min-w-[36px]">No</th>
          <th class="sticky left-0 bg-white z-[2] min-w-[150px]">Nama Pegawai</th>
          @for($d = 1; $d <= $hariDalamBulan; $d++)
            @php
              $dtD = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
            @endphp
            <th class="angka min-w-[40px]" style="{{ $dtD->dayOfWeek === Carbon\Carbon::SUNDAY ? 'color:var(--merah)' : '' }}"
                title="{{ $dtD->translatedFormat('l') }}">{{ $d }}</th>
          @endfor
        </tr>
      </thead>
      <tbody>
        @foreach($pegawaiBertugas as $i => $pg)
        <tr class="baris-data" data-user="{{ $pg->id }}">
          <td class="angka teks-redup">{{ $offset + $i + 1 }}</td>
          <td class="sticky left-0 z-[1] whitespace-nowrap">
            <strong>{{ $pg->nama_lengkap }}</strong>
            @if($pg->sub_unit_nama)
              <br><span class="teks-redup teks-kecil">{{ $pg->unit_nama }} — {{ $pg->sub_unit_nama }}</span>
            @elseif($pg->unit_nama)
              <br><span class="teks-redup teks-kecil">{{ $pg->unit_nama }}</span>
            @endif
          </td>
          @for($d = 1; $d <= $hariDalamBulan; $d++)
            @php
              $tglS = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
              $sh = $shiftById->get($jadwalPegawai[$pg->id][$tglS] ?? null);
              $warnaSh = $sh ? match ($sh->kategori) {
                  'Pagi' => '#fef3c7', 'Siang' => '#dbeafe', 'Sore' => '#e0e7ff', default => '#e2e8f0',
              } : null;
            @endphp
            <td class="text-center teks-kecil"
                style="{{ $warnaSh ? 'background:'.$warnaSh.';border-radius:4px' : '' }}"
                title="{{ $sh ? $sh->kategori.' '.$sh->jam_masuk->format('H:i').'-'.$sh->jam_pulang->format('H:i') : '' }}">
              {{ $sh?->kategori }}
            </td>
          @endfor
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="px-3 py-2 flex flex-wrap items-center gap-2 border-t border-garis no-cetak">
    <span class="teks-redup teks-kecil">Menampilkan {{ $mulai }}–{{ $sampai }} dari {{ $total }} pegawai</span>

    @if($lastPage > 1)
      <nav class="ms-auto flex items-center gap-1" aria-label="Navigasi halaman">
        <button type="button" class="btn btn-garis btn-kecil" data-hal="{{ $page - 1 }}"
                {{ $page <= 1 ? 'disabled' : '' }}>‹ Sebelumnya</button>

        @if($halAwal > 1)
          <button type="button" class="btn btn-garis btn-kecil" data-hal="1">1</button>
          @if($halAwal > 2)<span class="teks-redup px-1">…</span>@endif
        @endif

        @for($h = $halAwal; $h <= $halAkhir; $h++)
          <button type="button"
                  class="btn btn-kecil {{ $h === $page ? 'btn-navy' : 'btn-garis' }}"
                  data-hal="{{ $h }}" {{ $h === $page ? 'aria-current="page"' : '' }}>{{ $h }}</button>
        @endfor

        @if($halAkhir < $lastPage)
          @if($halAkhir < $lastPage - 1)<span class="teks-redup px-1">…</span>@endif
          <button type="button" class="btn btn-garis btn-kecil" data-hal="{{ $lastPage }}">{{ $lastPage }}</button>
        @endif

        <button type="button" class="btn btn-garis btn-kecil" data-hal="{{ $page + 1 }}"
                {{ $page >= $lastPage ? 'disabled' : '' }}>Berikutnya ›</button>
      </nav>
    @endif
  </div>
@endif
