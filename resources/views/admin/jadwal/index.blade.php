@extends('layouts.admin')

@section('content')

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="baris-tab-jadwal">
  <button type="button" class="jadwal-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="data">Data Jadwal</button>
  <button type="button" class="jadwal-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="unit">Per Unit</button>
  <button type="button" class="jadwal-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500"
          data-tab="pegawai">Per Pegawai</button>
</div>

{{-- ══════════════════ TAB 0 : DATA JADWAL (DEFAULT) ══════════════════ --}}
<section id="panel-data" class="jadwal-panel hidden">

  <section class="kartu no-cetak">
    <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Periode</h2></div>
    <form method="get" action="{{ url('admin/jadwal') }}" class="bilah-alat">
      <input type="hidden" name="tab" value="data">
      <select name="bulan">
        @for($m = 1; $m <= 12; $m++)
          <option value="{{ $m }}" {{ $m === $bulan ? 'selected' : '' }}>{{ BULAN_ID[$m] }}</option>
        @endfor
      </select>
      <select name="tahun">
        @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
          <option value="{{ $y }}" {{ $y === $tahun ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
      <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
    </form>
  </section>

  @if(count($pegawaiBertugas))

  <section class="kartu" id="area-cetak-jadwal">
    {{-- kepala khusus cetak --}}
    <div class="kepala-cetak text-center mb-2">
      <h2 class="text-base font-bold">{{ pengaturan('nama_instansi', 'RSUD Merauke') }}</h2>
      <p class="text-sm">Jadwal Shift Pegawai · {{ BULAN_ID[$bulan] }} {{ $tahun }}</p>
    </div>

    <div class="kartu-kepala no-cetak">
      <h2>{!! ikon('jam') !!} Data Jadwal — {{ BULAN_ID[$bulan] }} {{ $tahun }}
        <span class="badge badge-biru ms-1" id="jumlah-filter"></span>
      </h2>
      <div class="flex gap-2">
        <button type="button" id="tombol-filter-pegawai" class="btn btn-garis btn-kecil">
          {!! ikon('pegawai') !!} Filter <span class="ms-1 font-semibold" id="jumlah-pilih-filter"></span>
        </button>
        <button type="button" id="tombol-cetak" class="btn btn-navy btn-kecil">Cetak</button>
      </div>
    </div>

    <div class="tabel-bungkus overflow-x-auto">
      <table class="tabel jadwal-grid" style="min-width:{{ 210 + ($hariDalamBulan * 46) }}px">
        <thead>
          <tr>
            <th class="angka min-w-[36px]">No</th>
            <th class="sticky left-0 bg-white z-[2] min-w-[150px]">Nama Pegawai</th>
            @for($d = 1; $d <= $hariDalamBulan; $d++)
              @php
                $dtD = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
                $tglD = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
              @endphp
              <th class="angka min-w-[40px]" style="{{ $dtD->dayOfWeek === Carbon\Carbon::SUNDAY ? 'color:var(--merah)' : '' }}"
                  title="{{ $dtD->translatedFormat('l') }}">{{ $d }}</th>
            @endfor
          </tr>
        </thead>
        <tbody>
          @foreach($pegawaiBertugas as $i => $pg)
          <tr class="baris-data" data-user="{{ $pg->id }}">
            <td class="angka teks-redup">{{ $i + 1 }}</td>
            <td class="sticky left-0 bg-white z-[1] whitespace-nowrap">
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

    <div class="px-3 py-2 teks-redup teks-kecil border-t border-garis">
      Keterangan:
      @foreach($shiftList as $s)
        <span class="ms-2 whitespace-nowrap"><b>{{ $s->kategori }}</b> {{ $s->jam_masuk->format('H:i') }}–{{ $s->jam_pulang->format('H:i') }}</span>
      @endforeach
    </div>
  </section>

  @else

  <section class="kartu no-cetak">
    <div class="kartu-kepala"><h2>{!! ikon('jam') !!} Data Jadwal</h2></div>
    <p class="teks-redup text-center py-[30px]">
      Belum ada jadwal tersimpan pada {{ BULAN_ID[$bulan] }} {{ $tahun }}.<br>
      Isi melalui tab <strong>Per Unit</strong> atau <strong>Per Pegawai</strong>.
    </p>
  </section>

  @endif
</section>

<style>
  .kepala-cetak { display: none; }
  @media print {
    @page { size: A4 landscape; margin: 10mm; }
    body * { visibility: hidden !important; }
    #area-cetak-jadwal, #area-cetak-jadwal * { visibility: visible !important; }
    #area-cetak-jadwal { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none !important; border: none !important; }
    .kepala-cetak { display: block !important; }
    .no-cetak { display: none !important; }
    .sticky { position: static !important; }
    .tabel-bungkus { overflow: visible !important; }
    .jadwal-grid { min-width: 0 !important; width: 100% !important; font-size: .62rem !important; }
  }
</style>

{{-- ══════════════════ TAB 1 : PER UNIT ══════════════════ --}}
<section id="panel-unit" class="jadwal-panel">

  <section class="kartu">
    <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Filter</h2></div>
    <form method="get" action="{{ url('admin/jadwal') }}" class="bilah-alat">
      <input type="hidden" name="tab" value="unit">
      <select name="sub_unit" required>
        <option value="">Pilih Sub Unit…</option>
        @foreach($subUnits as $su)
          <option value="{{ (int) $su->id }}" {{ $subUnitId === (int) $su->id ? 'selected' : '' }}>
            {{ $su->unit_nama }} — {{ $su->nama }}
          </option>
        @endforeach
      </select>
      <select name="bulan">
        @for($m = 1; $m <= 12; $m++)
          <option value="{{ $m }}" {{ $m === $bulan ? 'selected' : '' }}>{{ BULAN_ID[$m] }}</option>
        @endfor
      </select>
      <select name="tahun">
        @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
          <option value="{{ $y }}" {{ $y === $tahun ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
      <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
    </form>
  </section>

  @if($subUnitId && count($pegawai) > 0)

  <form method="post" action="{{ url('admin/jadwal/aksi') }}" id="jadwal-form">
    @csrf
    <input type="hidden" name="sub_unit_id" value="{{ $subUnitId }}">
    <input type="hidden" name="bulan" value="{{ $bulan }}">
    <input type="hidden" name="tahun" value="{{ $tahun }}">

    <section class="kartu">
      <div class="kartu-kepala">
        @php
          $subUnitAktif = $subUnits->firstWhere('id', $subUnitId);
        @endphp
        <h2>{!! ikon('jam') !!} Jadwal — {{ BULAN_ID[$bulan] }} {{ $tahun }}
          @if($subUnitAktif)
            <span class="text-sm font-normal text-slate-500">· {{ $subUnitAktif->unit_nama }} — {{ $subUnitAktif->nama }}</span>
          @endif
        </h2>
        <button type="submit" class="btn btn-primer btn-kecil">Simpan Semua</button>
      </div>

      <div class="tabel-bungkus overflow-x-auto">
        <table class="tabel jadwal-grid" style="min-width:{{ 180 + ($hariDalamBulan * 52) }}px">
          <thead>
            <tr>
              <th class="sticky left-0 bg-white z-[2] min-w-[160px]">Nama Pegawai</th>
              <th class="min-w-[60px] text-center" title="Isi semua tanggal dengan shift yang sama">Isi Semua</th>
              @for($d = 1; $d <= $hariDalamBulan; $d++)
                @php
                  $dt = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
                  $isLibur = $dt->dayOfWeek === Carbon\Carbon::SUNDAY;
                @endphp
                <th class="angka min-w-[52px]" style="{{ $isLibur ? 'color:var(--merah)' : '' }}" title="{{ $dt->translatedFormat('l') }}">{{ $d }}</th>
              @endfor
            </tr>
          </thead>
          <tbody>
            @foreach($pegawai as $p)
            <tr>
              <td class="sticky left-0 bg-white z-[1] whitespace-nowrap">
                <strong>{{ $p->nama_lengkap }}</strong>
              </td>
               <td class="p-0.5 text-center">
                <select class="jadwal-isiSemua w-[48px] p-0.5 text-[0.7rem] border border-garis rounded" data-user="{{ $p->id }}">
                  <option value="">Isi…</option>
                  @foreach($shiftList as $s)
                    <option value="{{ (int) $s->id }}">{{ $s->kategori }} = {{$s->jam_masuk->format('H:i')}} - {{$s->jam_pulang->format('H:i')}}</option>
                  @endforeach
                </select>
              </td>
              @for($d = 1; $d <= $hariDalamBulan; $d++)
                @php
                  $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                  $val = $jadwal[$p->id][$tgl] ?? '';
                @endphp
                <td class="p-0.5 text-center">
                  <select name="grid[{{ $p->id }}][{{ $tgl }}]"
                          data-user="{{ $p->id }}"
                          class="jadwal-select"
                          style="width:50px;padding:2px 1px;font-size:.72rem;border:1px solid var(--garis);border-radius:4px;background:{{ $val ? 'var(--biru-muda)' : 'var(--latar)' }}">
                    <option value="">—</option>
                    @foreach($shiftList as $s)
                      <option value="{{ (int) $s->id }}" {{ $val == $s->id ? 'selected' : '' }}>
                        {{ $s->kategori }} = {{ $s->jam_masuk->format('h:i') }} - {{ $s->jam_pulang->format('h:i') }}
                      </option>
                    @endforeach
                  </select>
                </td>
              @endfor

            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  </form>

  @elseif($subUnitId)

  <section class="kartu">
    <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Jadwal</h2></div>
    <p class="teks-redup text-center py-[30px]">Tidak ada pegawai aktif di sub unit ini.</p>
  </section>

  @endif
</section>

{{-- ══════════════════ TAB 2 : PER PEGAWAI ══════════════════ --}}
<section id="panel-pegawai" class="jadwal-panel hidden">

  {{-- Langkah 1 : pilih periode (reload agar tanggal grid sesuai) --}}
  <section class="kartu mb-4">
    <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Langkah 1 · Pilih Periode</h2></div>
    <form method="get" action="{{ url('admin/jadwal') }}" class="bilah-alat">
      <input type="hidden" name="tab" value="pegawai">
      <select name="bulan">
        @for($m = 1; $m <= 12; $m++)
          <option value="{{ $m }}" {{ $m === $bulan ? 'selected' : '' }}>{{ BULAN_ID[$m] }}</option>
        @endfor
      </select>
      <select name="tahun">
        @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
          <option value="{{ $y }}" {{ $y === $tahun ? 'selected' : '' }}>{{ $y }}</option>
        @endfor
      </select>
      <button type="submit" class="btn btn-navy btn-kecil">Tampilkan Jadwal</button>
    </form>
  </section>

  <form method="post" action="{{ route('admin.jadwal.pegawai') }}" id="jadwal-pegawai-form">
    @csrf
    <input type="hidden" name="bulan" value="{{ $bulan }}">
    <input type="hidden" name="tahun" value="{{ $tahun }}">

    <section class="kartu">
      <div class="kartu-kepala">
        <h2>{!! ikon('jam') !!}  Atur Jadwal
          <span class="badge badge-biru ms-1" id="jumlah-dipilih">0 pegawai</span>
        </h2>
        <div class="flex gap-3 items-center">
          <button type="submit" class="btn btn-primer btn-kecil">Simpan Jadwal</button>
          <button type="button" id="tombol-tambah-pegawai" class="btn btn-navy btn-kecil">+ Tambah Pegawai</button>
        </div>
      </div>
      <div class="px-3 py-1 text-sm">
        Klik <b>+ Tambah Pegawai</b> untuk memilih. Jadwal yang sudah tersimpan pada periode ini terisi otomatis.
      </div>

      <div class="tabel-bungkus overflow-x-auto">
        <table class="tabel jadwal-grid" style="min-width:{{ 180 + ($hariDalamBulan * 52) }}px">
          <thead>
            <tr>
              <th class="sticky left-0 bg-white z-[2] min-w-[160px]">Nama Pegawai</th>
              <th class="min-w-[60px] text-center" title="Isi semua tanggal dengan shift yang sama">Isi Semua</th>
              @for($d = 1; $d <= $hariDalamBulan; $d++)
                @php
                  $dt2 = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
                  $libur2 = $dt2->dayOfWeek === Carbon\Carbon::SUNDAY;
                @endphp
                <th class="angka min-w-[52px]" style="{{ $libur2 ? 'color:var(--merah)' : '' }}"
                    data-tanggal="{{ sprintf('%04d-%02d-%02d', $tahun, $bulan, $d) }}"
                    title="{{ $dt2->translatedFormat('l') }}">{{ $d }}</th>
              @endfor
            </tr>
          </thead>
          <tbody id="tubuh-tabel-pegawai">
            <tr id="baris-kosong-pegawai">
              <td colspan="{{ $hariDalamBulan + 2 }}" class="teks-redup text-center py-[30px] teks-kecil">
                Belum ada pegawai. Klik <strong>+ Tambah Pegawai</strong> untuk memilih.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    {{-- template baris baru (disalin oleh JS) --}}
    <template id="template-baris-pegawai">
      <tr class="baris-pgw" data-user="">
        <td class="sticky left-0 bg-white z-[1] whitespace-nowrap">
          <input type="hidden" name="users[]" value="" class="input-user-pgw">
          <button type="button" class="hapus-baris-pgw ms-1 text-red-800  cursor-pointer" title="Hapus baris ini">&times;</button>
          <strong class="nama-pgw"></strong>
        </td>
        <td class="p-0.5 text-center">
          <select class="isi-semua-baris w-[48px] p-0.5 text-[0.7rem] border border-garis rounded">
            <option value="">Isi…</option>
            @foreach($shiftList as $s)
              <option value="{{ (int) $s->id }}">{{ $s->kategori }} = {{ $s->jam_masuk->format('H:i') }} - {{ $s->jam_pulang->format('H:i') }}</option>
            @endforeach
          </select>
        </td>
        @for($d = 1; $d <= $hariDalamBulan; $d++)
          @php $tgl3 = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d); @endphp
          <td class="p-0.5 text-center">
            <select data-tanggal="{{ $tgl3 }}"
                    class="sel-hari"
                    style="width:50px;padding:2px 1px;font-size:.72rem;border:1px solid var(--garis);border-radius:4px;background:var(--latar)">
              <option value="">—</option>
              @foreach($shiftList as $s)
                <option value="{{ (int) $s->id }}">
                  {{ $s->kategori }} = {{ $s->jam_masuk->format('h:i') }} - {{ $s->jam_pulang->format('h:i') }}
                </option>
              @endforeach
            </select>
          </td>
        @endfor
      </tr>
    </template>
  </form>
</section>

{{-- ── POPUP PILIH PEGAWAI ── --}}
<div id="modal-pegawai" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-lg max-h-[85vh] flex flex-col">
    <div class="kartu-kepala">
      <h2>{!! ikon('pegawai') !!} Pilih Pegawai</h2>
      <button type="button" id="modal-pegawai-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <div class="p-3 flex flex-col gap-2 overflow-hidden flex-1">
      <input type="text" id="cari-pegawai-modal" placeholder="Cari nama / unit…"
             class="w-full px-3 py-1.5 text-sm border border-garis rounded-lg">
      <div id="daftar-modal-pegawai" class="overflow-y-auto border border-garis rounded-xl divide-y divide-slate-100 flex-1">
        @forelse($semuaPegawai as $pg)
          <label class="flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer baris-modal-pegawai"
                 data-cari="{{ strtolower($pg->nama_lengkap.' '.($pg->unit_nama ?? '').' '.($pg->sub_unit_nama ?? '')) }}"
                 data-id="{{ $pg->id }}">
            <input type="checkbox" class="checkbox-modal-pegawai accent-[var(--biru)]" value="{{ $pg->id }}"
                   data-nama="{{ $pg->nama_lengkap }}">
            <span class="font-medium">{{ $pg->nama_lengkap }}</span>
            <span class="teks-redup teks-kecil ms-auto">{{ $pg->unit_nama }}{{ $pg->sub_unit_nama ? ' — '.$pg->sub_unit_nama : '' }}</span>
          </label>
        @empty
          <p class="px-3 py-3 teks-redup teks-kecil">Belum ada pegawai aktif.</p>
        @endforelse
      </div>
    </div>
    <div class="px-3 pb-3 pt-1 flex justify-end gap-2">
      <button type="button" id="modal-pegawai-batal" class="btn btn-garis btn-kecil">Batal</button>
      <button type="button" id="modal-pegawai-tambahkan" class="btn btn-primer btn-kecil">Tambahkan ke Daftar</button>
    </div>
  </section>
</div>

{{-- ── POPUP FILTER PEGAWAI (DATA JADWAL) ── --}}
<div id="modal-filter-pegawai" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-lg max-h-[85vh] flex flex-col">
    <div class="kartu-kepala">
      <h2>{!! ikon('pegawai') !!} Tampilkan Pegawai</h2>
      <button type="button" id="modal-filter-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <div class="p-3 flex flex-col gap-2 overflow-hidden flex-1">
      <input type="text" id="cari-pegawai-filter" placeholder="Cari nama / unit…"
             class="w-full px-3 py-1.5 text-sm border border-garis rounded-lg">
      <div id="daftar-modal-filter" class="overflow-y-auto border border-garis rounded-xl divide-y divide-slate-100 flex-1">
        @forelse($pegawaiBertugas as $pg)
          <label class="flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 cursor-pointer baris-modal-filter"
                 data-cari="{{ strtolower($pg->nama_lengkap.' '.($pg->unit_nama ?? '').' '.($pg->sub_unit_nama ?? '')) }}">
            <input type="checkbox" class="checkbox-filter-pegawai filter-pgw accent-[var(--biru)]" value="{{ $pg->id }}" checked>
            <span class="font-medium">{{ $pg->nama_lengkap }}</span>
            <span class="teks-redup teks-kecil ms-auto">{{ $pg->unit_nama }}{{ $pg->sub_unit_nama ? ' — '.$pg->sub_unit_nama : '' }}</span>
          </label>
        @empty
          <p class="px-3 py-3 teks-redup teks-kecil">Belum ada pegawai dengan jadwal tersimpan.</p>
        @endforelse
      </div>
    </div>
    <div class="px-3 pb-3 pt-1 flex justify-between items-center gap-2">
      <div class="flex gap-2">
        <button type="button" id="filter-semua" class="btn btn-garis btn-kecil">Pilih Semua</button>
        <button type="button" id="filter-kosong" class="btn btn-garis btn-kecil">Kosongkan</button>
      </div>
      <button type="button" id="modal-filter-selesai" class="btn btn-primer btn-kecil">Selesai</button>
    </div>
  </section>
</div>

@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ── Tab switcher ──
  const tombolTab = document.querySelectorAll('.jadwal-tab');
  const panelTab  = document.querySelectorAll('.jadwal-panel');

  function bukaTab(id) {
    tombolTab.forEach(function (b) {
      const aktif = b.dataset.tab === id;
      b.classList.toggle('bg-white', aktif);
      b.classList.toggle('text-slate-900', aktif);
      b.classList.toggle('shadow-sm', aktif);
      b.classList.toggle('text-slate-500', !aktif);
    });
    panelTab.forEach(function (p) { p.classList.toggle('hidden', p.id !== 'panel-' + id); });
    if (window.history.replaceState) history.replaceState(null, '', '#' + id);
  }

  tombolTab.forEach(function (b) {
    b.addEventListener('click', function () { bukaTab(b.dataset.tab); });
  });

  const tabAwal = (location.hash || '').replace('#', '')
    || new URLSearchParams(location.search).get('tab') || '';
  bukaTab(tabAwal === 'unit' || tabAwal === 'pegawai' ? tabAwal : 'data');

  // ── Tab Data Jadwal: filter pegawai (popup) & cetak ──
  const cekFilter = document.querySelectorAll('.filter-pgw');
  const jumlahFilter = document.getElementById('jumlah-filter');
  const jumlahPilih = document.getElementById('jumlah-pilih-filter');
  const modalFilter = document.getElementById('modal-filter-pegawai');
  const cariFilter = document.getElementById('cari-pegawai-filter');

  function segarkanFilter() {
    let tampil = 0;
    document.querySelectorAll('tr.baris-data').forEach(function (tr) {
      const cek = document.querySelector('.filter-pgw[value="' + tr.getAttribute('data-user') + '"]');
      const ok = !cek || cek.checked;
      tr.style.display = ok ? '' : 'none';
      if (ok) tampil++;
    });
    if (jumlahFilter) jumlahFilter.textContent = tampil + '/' + cekFilter.length + ' pegawai';
    if (jumlahPilih) jumlahPilih.textContent = tampil + ' dipilih';
  }

  function saringFilter(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#daftar-modal-filter .baris-modal-filter').forEach(function (row) {
      row.style.display = row.dataset.cari.includes(q) ? '' : 'none';
    });
  }
  function bukaModalFilter() {
    modalFilter.classList.remove('hidden');
    modalFilter.classList.add('flex');
    cariFilter.value = '';
    saringFilter('');
  }
  function tutupModalFilter() {
    modalFilter.classList.add('hidden');
    modalFilter.classList.remove('flex');
  }

  cekFilter.forEach(function (c) { c.addEventListener('change', segarkanFilter); });
  document.getElementById('filter-semua')?.addEventListener('click', function () {
    cekFilter.forEach(function (c) { c.checked = true; }); segarkanFilter();
  });
  document.getElementById('filter-kosong')?.addEventListener('click', function () {
    cekFilter.forEach(function (c) { c.checked = false; }); segarkanFilter();
  });
  document.getElementById('tombol-cetak')?.addEventListener('click', function () { window.print(); });

  document.getElementById('tombol-filter-pegawai')?.addEventListener('click', bukaModalFilter);
  document.getElementById('modal-filter-tutup')?.addEventListener('click', tutupModalFilter);
  document.getElementById('modal-filter-selesai')?.addEventListener('click', tutupModalFilter);
  modalFilter?.addEventListener('click', function (e) { if (e.target === modalFilter) tutupModalFilter(); });
  cariFilter?.addEventListener('input', function () { saringFilter(this.value); });

  segarkanFilter();

  // ── Tab Per Unit: isi semua & warna ──
  document.querySelectorAll('.jadwal-isiSemua').forEach(function (el) {
    el.addEventListener('change', function () {
      var userId = this.getAttribute('data-user');
      var val = this.value;
      document.querySelectorAll('.jadwal-select[data-user="' + userId + '"]').forEach(function (sel) {
        sel.value = val;
        sel.style.background = val ? 'var(--biru-muda)' : 'var(--latar)';
      });
      this.value = '';
    });
  });

  document.querySelectorAll('.jadwal-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      this.style.background = this.value ? 'var(--biru-muda)' : 'var(--latar)';
    });
  });

  // ── Tab Per Pegawai: baris dinamis per pegawai ──
  const jadwalTersimpan = @json($jadwalPegawai);
  const tubuh   = document.getElementById('tubuh-tabel-pegawai');
  const template = document.getElementById('template-baris-pegawai');
  const kosong  = document.getElementById('baris-kosong-pegawai');
  const counter = document.getElementById('jumlah-dipilih');
  const formPeg = document.getElementById('jadwal-pegawai-form');
  const modal   = document.getElementById('modal-pegawai');
  const cariBox = document.getElementById('cari-pegawai-modal');

  function barisPegawai() {
    return tubuh.querySelectorAll('tr.baris-pgw');
  }

  function segarkanCounter() {
    counter.textContent = barisPegawai().length + ' pegawai';
    kosong.style.display = barisPegawai().length ? 'none' : '';
  }

  function pasangBaris(tr, id, nama) {
    tr.setAttribute('data-user', id);
    tr.querySelector('.input-user-pgw').value = id;
    tr.querySelector('.nama-pgw').textContent = nama;

    const isiLama = jadwalTersimpan[id] || {};
    tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
      sel.name = 'grid[' + id + '][' + sel.dataset.tanggal + ']';
      if (isiLama[sel.dataset.tanggal]) {
        sel.value = String(isiLama[sel.dataset.tanggal]);
        sel.style.background = 'var(--biru-muda)';
      }
    });

    tr.querySelector('.hapus-baris-pgw').addEventListener('click', function () {
      tr.remove();
      segarkanCounter();
    });

    tr.querySelector('.isi-semua-baris').addEventListener('change', function () {
      var val = this.value;
      tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
        sel.value = val;
        sel.style.background = val ? 'var(--biru-muda)' : 'var(--latar)';
      });
      this.value = '';
    });

    tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
      sel.addEventListener('change', function () {
        this.style.background = this.value ? 'var(--biru-muda)' : 'var(--latar)';
      });
    });
  }

  function bukaModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    cariBox.value = '';
    saring('');
    const ada = new Set(barisPegawai().length
      ? Array.from(barisPegawai()).map(function (tr) { return tr.getAttribute('data-user'); })
      : []);
    document.querySelectorAll('#daftar-modal-pegawai .baris-modal-pegawai').forEach(function (row) {
      row.querySelector('.checkbox-modal-pegawai').checked = ada.has(row.dataset.id);
    });
  }
  function tutupModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  document.getElementById('tombol-tambah-pegawai').addEventListener('click', bukaModal);
  document.getElementById('modal-pegawai-tutup').addEventListener('click', tutupModal);
  document.getElementById('modal-pegawai-batal').addEventListener('click', tutupModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutupModal(); });

  function saring(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('#daftar-modal-pegawai .baris-modal-pegawai').forEach(function (row) {
      row.style.display = row.dataset.cari.includes(q) ? '' : 'none';
    });
  }
  cariBox.addEventListener('input', function () { saring(this.value); });

  document.getElementById('modal-pegawai-tambahkan').addEventListener('click', function () {
    let baru = 0;
    document.querySelectorAll('#daftar-modal-pegawai .checkbox-modal-pegawai:checked').forEach(function (c) {
      const id = c.value;
      if (tubuh.querySelector('tr.baris-pgw[data-user="' + id + '"]')) return; // sudah ada
      const tr = template.content.firstElementChild.cloneNode(true);
      pasangBaris(tr, id, c.dataset.nama);
      tubuh.appendChild(tr);
      baru++;
    });
    if (baru) segarkanCounter();
    tutupModal();
  });

  formPeg?.addEventListener('submit', function (e) {
    if (barisPegawai().length === 0) {
      e.preventDefault();
      alert('Tambahkan minimal satu pegawai terlebih dahulu.');
    }
  });

  segarkanCounter();
});
</script>
@endsection
