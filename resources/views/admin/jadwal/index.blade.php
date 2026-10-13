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

    <div class="bilah-alat" style="border-top:1px dashed var(--warna-garis);padding-top:12px;margin-top:4px">
      <a class="btn btn-garis btn-kecil" href="{{ route('admin.jadwal.template', ['bulan' => $bulan, 'tahun' => $tahun]) }}">
        {!! ikon('unduh', 15) !!} Template Excel
      </a>
      <form method="post" action="{{ route('admin.jadwal.import') }}" enctype="multipart/form-data"
            class="flex flex-wrap items-center gap-2">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
        <button type="submit" class="btn btn-navy btn-kecil">{!! ikon('unggah', 15) !!} Import Excel</button>
        <span class="teks-kecil teks-redup">
          Format: NIP/Email/Nama + kolom tanggal 01–{{ sprintf('%02d', $hariDalamBulan) }}. Jadwal
          {{ BULAN_ID[$bulan] }} {{ $tahun }} untuk pegawai di file diganti penuh; sel kosong = libur.
        </span>
      </form>
    </div>
  </section>

  <section class="kartu" id="area-cetak-jadwal">
    {{-- kepala khusus cetak --}}
    <div class="kepala-cetak text-center mb-2">
      <h2 class="text-base font-bold">{{ pengaturan('nama_instansi', 'RSUD Merauke') }}</h2>
      <p class="text-sm">Jadwal Shift Pegawai · {{ BULAN_ID[$bulan] }} {{ $tahun }}</p>
    </div>

    <div class="kartu-kepala no-cetak">
      <h2>{!! ikon('jam') !!} Data Jadwal — {{ BULAN_ID[$bulan] }} {{ $tahun }}
      </h2>
      <div class="flex gap-2">
        <button type="button" id="tombol-cetak" class="btn btn-navy btn-kecil">Cetak</button>
      </div>
    </div>

    <div class="px-3 pb-3 no-cetak">
      <input type="search" id="cari-jadwal" autocomplete="off"
             placeholder="Cari nama pegawai / unit…"
             class="w-full max-w-md px-3 py-1.5 text-sm border border-garis rounded-lg">
    </div>

    <div id="data-jadwal-isi" aria-busy="true" aria-live="polite">
      <div class="tabel-bungkus overflow-x-auto">
        <table class="tabel jadwal-grid" style="min-width:{{ 210 + ($hariDalamBulan * 46) }}px">
          <tbody>
            @for($i = 0; $i < 8; $i++)
              <tr>
                <td><div class="skel h-3.5 w-6"></div></td>
                <td><div class="skel h-3.5 w-40"></div></td>
                @for($d = 1; $d <= $hariDalamBulan; $d++)
                  <td><div class="skel h-4 w-full"></div></td>
                @endfor
              </tr>
            @endfor
          </tbody>
        </table>
      </div>
    </div>

    <div class="px-3 py-2 teks-redup teks-kecil border-t border-garis">
      Keterangan:
      @foreach($shiftList as $s)
        <span class="ms-2 whitespace-nowrap"><b>{{ $s->kategori }}</b> {{ $s->jam_masuk->format('H:i') }}–{{ $s->jam_pulang->format('H:i') }}</span>
      @endforeach
    </div>
  </section>
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
        <table id="grid-unit" class="tabel jadwal-grid" style="min-width:{{ 180 + ($hariDalamBulan * 52) }}px">
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
                          class="jadwal-select{{ $val ? ' terisi' : '' }}"
                          style="width:50px;padding:2px 1px;font-size:.72rem;border:1px solid var(--garis);border-radius:4px">
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
    {{-- Grid dikirim sebagai satu JSON agar tidak terkena batas max_input_vars PHP
         (1000) ketika jumlah pegawai × tanggal sudah besar. --}}
    <input type="hidden" name="grid_json" id="grid-json-pegawai">
    <input type="hidden" name="users_json" id="users-json-pegawai">

    <section class="kartu">
      <div class="kartu-kepala">
        <h2>{!! ikon('jam') !!}  Atur Jadwal
          <span class="badge badge-biru ms-1" id="jumlah-dipilih">0 pegawai</span>
        </h2>
        <div class="flex gap-3 items-center">
          <button type="submit" class="btn btn-primer btn-kecil">Simpan Jadwal</button>
          <button type="button" id="tombol-semua-pegawai" class="btn btn-navy btn-kecil">Atur Semua Pegawai</button>
          <button type="button" id="tombol-tambah-pegawai" class="btn btn-navy btn-kecil">+ Tambah Pegawai</button>
        </div>
      </div>
      <div class="px-3 py-1 text-sm">
        Klik <b>+ Tambah Pegawai</b> untuk memilih. Jadwal yang sudah tersimpan pada periode ini terisi otomatis.
      </div>

      <div class="px-3 py-2 border-t border-garis flex flex-wrap items-center gap-2">
        <span class="teks-kecil teks-redup">
          Dropdown <b>⇩</b> di tiap tanggal untuk mengisi seluruh pegawai pada tanggal itu.
          Tombol <b>Atur Semua Pegawai</b> untuk mengisi semua tanggal (bisa sekaligus semua pegawai aktif).
        </span>
        <button type="button" id="toolbar-kosongkan-semua" class="btn btn-garis btn-kecil ms-auto">Kosongkan Semua</button>
      </div>

      <div class="tabel-bungkus overflow-x-auto">
        <table id="tabel-pegawai" class="tabel jadwal-grid" style="min-width:{{ 180 + ($hariDalamBulan * 52) }}px">
          <thead>
            <tr>
              <th class="sticky left-0 bg-white z-[2] min-w-[160px]">Nama Pegawai</th>
              <th class="min-w-[60px] text-center" title="Isi semua tanggal dengan shift yang sama">Isi Semua</th>
              @for($d = 1; $d <= $hariDalamBulan; $d++)
                @php
                  $dt2 = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
                  $libur2 = $dt2->dayOfWeek === Carbon\Carbon::SUNDAY;
                  $tglKolom = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                @endphp
                <th class="angka min-w-[52px] align-top" style="{{ $libur2 ? 'color:var(--merah)' : '' }}"
                    data-tanggal="{{ $tglKolom }}"
                    title="{{ $dt2->translatedFormat('l') }}">
                  <div>{{ $d }}</div>
                  <select class="isi-kolom-tanggal mt-0.5 p-0 text-[0.6rem] border border-garis rounded"
                          data-tanggal="{{ $tglKolom }}"
                          title="Isi shift ini untuk semua pegawai pada tanggal {{ $d }}"
                          style="width:46px">
                    <option value="">⇩</option>
                    <option value="">—</option>
                    @foreach($shiftList as $s)
                      <option value="{{ (int) $s->id }}">{{ $s->kategori }} | {{ $s->jam_masuk->format('h:i') }} - {{ $s->jam_pulang->format('h:i') }}</option>
                    @endforeach
                  </select>
                </th>
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
          <input type="hidden" value="" class="input-user-pgw">
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
                    style="width:50px;padding:2px 1px;font-size:.72rem;border:1px solid var(--garis);border-radius:4px">
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
      <label class="flex items-center gap-2 px-1 text-sm cursor-pointer select-none">
        <input type="checkbox" id="pilih-semua-pegawai" class="accent-[var(--biru)]">
        <span class="font-medium">Pilih semua</span>
        <span class="teks-redup teks-kecil" id="ringkas-terpilih-modal">0 dipilih</span>
      </label>
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

{{-- ── POPUP ATUR SEMUA PEGAWAI ── --}}
<div id="modal-semua-pegawai" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <section class="kartu w-full max-w-md flex flex-col">
    <div class="kartu-kepala">
      <h2>{!! ikon('jam') !!} Atur Semua Pegawai Sekaligus</h2>
      <button type="button" id="modal-semua-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <div class="p-4 flex flex-col gap-3">
      <p class="teks-kecil teks-redup">
        Pilih satu shift untuk <b>semua tanggal</b> pada periode
        <b id="modal-semus-periode"></b>. Jadwal yang sudah terisi akan ditimpa.
      </p>
      <label class="flex flex-col gap-1">
        <span class="teks-kecil font-medium">Shift untuk semua tanggal</span>
        <select id="modal-semua-shift"
                class="px-3 py-2 text-sm border border-garis rounded-lg">
          <option value="">— Pilih shift —</option>
          @foreach($shiftList as $s)
            <option value="{{ (int) $s->id }}">
              {{ $s->kategori }} = {{ $s->jam_masuk->format('H:i') }} - {{ $s->jam_pulang->format('H:i') }}
            </option>
          @endforeach
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" id="modal-semua-terpilih" class="accent-[var(--biru)]" checked>
        <span>Hanya untuk <b id="modal-semua-jumlah">0</b> pegawai yang dipilih</span>
      </label>
      <p class="teks-kecil teks-redup">
        Kosongkan centang <b>Hanya untuk</b> bila ingin menerapkan ke seluruh pegawai aktif.
      </p>
    </div>
    <div class="px-4 pb-4 pt-1 flex justify-end gap-2">
      <button type="button" id="modal-semua-batal" class="btn btn-garis btn-kecil">Batal</button>
      <button type="button" id="modal-semua-terapkan" class="btn btn-primer btn-kecil">Terapkan</button>
    </div>
  </section>
</div>

{{-- ── POPUP FILTER dihapus: Data Jadwal kini pakai pencarian + pagination ── --}}

@endsection

@section('script')
<style>
  /* Sel jadwal yang terisi shift ditandai dengan kelas, bukan style inline,
     supaya pengisian hundreds sel tidak memicu hitung ulang style berulang. */
  select.sel-hari.terisi,
  select.jadwal-select.terisi {
    background-color: var(--biru-muda);
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ── Indikator proses pada tombol ──
  // Tombol submit yang dipakai berubah jadi spinner + teks proses lalu
  // dinonaktifkan, supaya jelas permintaan berjalan dan tidak terkirim dua kali.
  const SPINNER_PROSES = '<svg class="animate-spin h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="animation-duration:0.7s">'
    + '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3"></circle>'
    + '<path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>'
    + '</svg>';

  function labelProses(aksi) {
    if (/import/i.test(aksi)) return 'Mengimpor…';
    if (/aksi-pegawai/.test(aksi)) return 'Menyimpan…';
    if (/jadwal\/aksi/.test(aksi)) return 'Menyimpan…';
    if (/struktur|shift/.test(aksi)) return 'Menyimpan…';
    if (/jadwal/.test(aksi)) return 'Memuat…';
    return 'Memproses…';
  }

  function nyalakanProses(form, tombol) {
    if (!tombol) return;
    tombol.dataset.teksProses = labelProses(form.getAttribute('action') || '');
    tombol.innerHTML = SPINNER_PROSES + '<span>' + tombol.dataset.teksProses + '</span>';
    tombol.disabled = true;
  }

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      // Ditunda satu tick supaya handler validasi lain yang menolak submit
      // sempat memutuskan lebih dulu; tombol tidak berubah kalau batal.
      setTimeout(function () {
        if (e.defaultPrevented) return;
        nyalakanProses(form, e.submitter || form.querySelector('button[type="submit"]'));
      }, 0);
    });
  });

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

  // ── Tab Data Jadwal: lazy load + pencarian + pagination ──
  const URL_DATA_JADWAL = @json(route('admin.jadwal.data'));
  const BULAN_JADWAL = @json((int) $bulan);
  const TAHUN_JADWAL = @json((int) $tahun);
  const isiData = document.getElementById('data-jadwal-isi');
  const cariJadwal = document.getElementById('cari-jadwal');
  const tombolCetak = document.getElementById('tombol-cetak');

  let halData = 1;
  let cariData = '';
  let mintaData = 0;

  // per = undefined (halaman biasa) atau 'all' (untuk cetak seluruh hasil).
  function ambilData(per) {
    const params = new URLSearchParams({
      bulan: BULAN_JADWAL,
      tahun: TAHUN_JADWAL,
      cari: cariData,
      page: halData
    });
    if (per) params.set('per', per);

    const idPermintaan = ++mintaData;
    isiData.setAttribute('aria-busy', 'true');

    return fetch(URL_DATA_JADWAL + '?' + params.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        // Abaikan respons usang bila sudah ada permintaan yang lebih baru.
        return idPermintaan === mintaData ? html : null;
      });
  }

  function muatData() {
    return ambilData()
      .then(function (html) {
        if (html === null) return;
        isiData.innerHTML = html;
        isiData.removeAttribute('aria-busy');
      })
      .catch(function () {
        isiData.removeAttribute('aria-busy');
        isiData.innerHTML = '<p class="teks-redup tengah py-[30px]">Gagal memuat data jadwal. ' +
          '<a class="tautan" href="">Coba lagi</a>.</p>';
      });
  }

  // Pencarian ditunda sedikit supaya mengetik tidak memicu request berkali-kali.
  let jedaCariJadwal = null;
  cariJadwal?.addEventListener('input', function () {
    const nilai = this.value;
    clearTimeout(jedaCariJadwal);
    jedaCariJadwal = setTimeout(function () {
      cariData = nilai.trim();
      halData = 1;
      muatData();
    }, 300);
  });

  // Pagination: delegasi karena isi diganti ulang tiap request.
  isiData?.addEventListener('click', function (e) {
    const tombol = e.target.closest('[data-hal]');
    if (!tombol || tombol.disabled) return;
    const tujuan = parseInt(tombol.getAttribute('data-hal'), 10);
    if (!tujuan || tujuan === halData) return;
    halData = tujuan;
    muatData();
  });

  // Cetak: muat seluruh hasil pencarian (tanpa pagination), cetak, lalu pulihkan.
  tombolCetak?.addEventListener('click', function () {
    const simpan = isiData.innerHTML;
    ambilData('all').then(function (html) {
      if (html === null) return;
      isiData.innerHTML = html;
      let dipulihkan = false;
      const pulihkan = function () {
        if (dipulihkan) return;
        dipulihkan = true;
        isiData.innerHTML = simpan;
      };
      window.addEventListener('afterprint', pulihkan, { once: true });
      setTimeout(function () {
        window.print();
        // Pengaman bila afterprint tidak dipanggil (mis. Safari).
        setTimeout(pulihkan, 800);
      }, 50);
    });
  });

  if (isiData) muatData();

  // ── Tab Per Unit: isi semua & warna ──
  // Delegasi di level tabel agar tidak memasang listener per select (ratusan
  // select per unit) yang bikin halaman lambat dibuka.
  const gridUnit = document.getElementById('grid-unit');

  gridUnit?.addEventListener('change', function (e) {
    const isiSemua = e.target.closest('.jadwal-isiSemua');
    if (isiSemua) {
      const userId = isiSemua.getAttribute('data-user');
      const val = isiSemua.value;
      gridUnit.querySelectorAll('.jadwal-select[data-user="' + userId + '"]').forEach(function (sel) {
        sel.value = val;
        sel.classList.toggle('terisi', Boolean(val));
      });
      isiSemua.value = '';
      return;
    }

    const sel = e.target.closest('.jadwal-select');
    if (sel) sel.classList.toggle('terisi', Boolean(sel.value));
  });

  // ── Tab Per Pegawai: baris dinamis per pegawai ──
  const URL_JADWAL_PEGAWAI = @json(route('admin.jadwal.pegawai-jadwal'));
  const tubuh   = document.getElementById('tubuh-tabel-pegawai');
  const template = document.getElementById('template-baris-pegawai');
  const kosong  = document.getElementById('baris-kosong-pegawai');
  const counter = document.getElementById('jumlah-dipilih');
  const formPeg = document.getElementById('jadwal-pegawai-form');
  const modal   = document.getElementById('modal-pegawai');
  const cariBox = document.getElementById('cari-pegawai-modal');

  // Id yang sudah punya baris. Disimpan sebagai Set supaya pengecekan "sudah ada"
  // tetap:O(1) dan tidak memindai tabel untuk tiap pegawai yang ditambahkan.
  const idTerpasang = new Set(
    Array.from(tubuh.querySelectorAll('tr.baris-pgw')).map(function (tr) { return tr.getAttribute('data-user'); })
  );

  function barisPegawai() {
    return tubuh.querySelectorAll('tr.baris-pgw');
  }

  function segarkanCounter() {
    counter.textContent = idTerpasang.size + ' pegawai';
    kosong.style.display = idTerpasang.size ? 'none' : '';
  }

  function pasangBaris(tr, id, nama) {
    tr.setAttribute('data-user', id);
    tr.querySelector('.input-user-pgw').value = id;
    tr.querySelector('.nama-pgw').textContent = nama;

    // Jadwal tersimpan diambil saat baris ditambahkan (lazy) -- bukan seluruh
    // pegawai sekaligus ketika halaman dibuka.
    const params = new URLSearchParams({ user_id: id, bulan: BULAN_JADWAL, tahun: TAHUN_JADWAL });
    return fetch(URL_JADWAL_PEGAWAI + '?' + params.toString(), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { return r.ok ? r.json() : {}; })
      .then(function (isiLama) {
        if (!document.body.contains(tr)) return;
        tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
          // Jangan timpa nilai yang sudah diisi pengguna (mis. lewat Atur Semua).
          if (sel.value) return;
          if (isiLama[sel.dataset.tanggal]) {
            sel.value = String(isiLama[sel.dataset.tanggal]);
            sel.classList.add('terisi');
          }
        });
      })
      .catch(function () {});
  }

  function tambahBaris(id, nama) {
    if (idTerpasang.has(id)) return false;
    const tr = template.content.firstElementChild.cloneNode(true);
    pasangBaris(tr, id, nama);
    tubuh.appendChild(tr);
    idTerpasang.add(id);
    return true;
  }

  function isiBaris(tr, idShift) {
    let terisi = 0;
    tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
      sel.value = idShift;
      sel.classList.toggle('terisi', Boolean(idShift));
      terisi++;
    });
    return terisi;
  }

  // Satu listener untuk seluruh tabel, bukan satu listener per select. Versi
  // sebelumnya memasang 31 listener × jumlah baris sehingga halaman berat.
  tubuh.addEventListener('click', function (e) {
    const hapus = e.target.closest('.hapus-baris-pgw');
    if (!hapus) return;
    const tr = hapus.closest('tr.baris-pgw');
    if (!tr) return;
    idTerpasang.delete(tr.getAttribute('data-user'));
    tr.remove();
    segarkanCounter();
  });

  tubuh.addEventListener('change', function (e) {
    const kolomSemua = e.target.closest('.isi-semua-baris');
    if (kolomSemua) {
      isiBaris(kolomSemua.closest('tr'), kolomSemua.value);
      kolomSemua.value = '';
      return;
    }
    const sel = e.target.closest('select.sel-hari');
    if (sel) sel.classList.toggle('terisi', Boolean(sel.value));
  });

  // ── Isi seluruh pegawai pada satu tanggal (dropdown di header kolom) ──
  const tabelPegawai = document.getElementById('tabel-pegawai');

  tabelPegawai?.addEventListener('change', function (e) {
    const isiKolom = e.target.closest('.isi-kolom-tanggal');
    if (!isiKolom) return;
    const tanggal = isiKolom.dataset.tanggal;
    const val = isiKolom.value;
    tubuh.querySelectorAll('select.sel-hari[data-tanggal="' + tanggal + '"]').forEach(function (sel) {
      sel.value = val;
      sel.classList.toggle('terisi', Boolean(val));
    });
    isiKolom.value = '';
  });

  // ── Toolbar: kosongkan seluruh jadwal pegawai terdaftar ──
  document.getElementById('toolbar-kosongkan-semua')?.addEventListener('click', function () {
    const target = Array.from(barisPegawai());
    if (!target.length) {
      alert('Belum ada pegawai. Tambahkan pegawai terlebih dahulu.');
      return;
    }
    return jalanBertahap(this, function (i, total) {
      return 'Mengosongkan ' + Math.min(i, total) + '/' + total + ' pegawai…';
    }, target.map(function (tr) {
      return function () { isiBaris(tr, ''); };
    }), 30);
  });

  function bukaModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    cariBox.value = '';
    saring('');
    semuaBarisModal.forEach(function (row) {
      row.querySelector('.checkbox-modal-pegawai').checked = idTerpasang.has(row.dataset.id);
    });
    segarkanRingkasModal();
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
    semuaBarisModal.forEach(function (row) {
      row.style.display = row.dataset.cari.includes(q) ? '' : 'none';
    });
    segarkanRingkasModal();
  }

  // Pencarian ditunda sedikit supaya mengetik tidak memicu saring berkali-kali.
  let jedaCari = null;
  cariBox.addEventListener('input', function () {
    const nilai = this.value;
    clearTimeout(jedaCari);
    jedaCari = setTimeout(function () { saring(nilai); }, 150);
  });

  const pilihSemua = document.getElementById('pilih-semua-pegawai');
  const ringkasModal = document.getElementById('ringkas-terpilih-modal');
  const semuaBarisModal = Array.from(document.querySelectorAll('#daftar-modal-pegawai .baris-modal-pegawai'));

  function barisTersaring() {
    return semuaBarisModal.filter(function (row) { return row.style.display !== 'none'; });
  }

  function segarkanRingkasModal() {
    const tampil = barisTersaring();
    const nTerpilih = tampil.filter(function (row) { return row.querySelector('.checkbox-modal-pegawai').checked; }).length;
    ringkasModal.textContent = nTerpilih + ' dipilih' + (tampil.length !== semuaBarisModal.length
      ? ' dari ' + tampil.length + ' yang tampil' : '');
    pilihSemua.checked = tampil.length > 0 && nTerpilih === tampil.length;
  }

  pilihSemua.addEventListener('change', function () {
    const nyala = this.checked;
    barisTersaring().forEach(function (row) { row.querySelector('.checkbox-modal-pegawai').checked = nyala; });
    segarkanRingkasModal();
  });

  // Delegasi di daftar, bukan satu listener per checkbox pegawai.
  document.getElementById('daftar-modal-pegawai')?.addEventListener('change', function (e) {
    if (e.target.classList.contains('checkbox-modal-pegawai')) segarkanRingkasModal();
  });

  // ── Proses berat: tampilkan spinner lalu kerjakan per potongan ──
  // Satu potongan per frame supaya browser sempat menggambar indikator proses
  // dan halaman tetap responsif saat ratusan baris ditambahkan atau diisi.
  // formatLabel(i, total) menyisipkan angka progres ke teks tombol.
  function jalanBertahap(tombol, formatLabel, tugas, perPotongan) {
    if (!tugas.length) return Promise.resolve();

    const isiAsli = tombol.innerHTML;
    const total = tugas.length;
    let i = 0;

    tombol.innerHTML = SPINNER_PROSES + '<span>' + formatLabel(0, total) + '</span>';
    tombol.disabled = true;

    return new Promise(function (selesai) {
      function lanjut() {
        const akhir = Math.min(i + perPotongan, total);
        for (; i < akhir; i++) tugas[i]();

        if (i < total) {
          // Progres diperbarui tiap potongan supaya terlihat bergerak.
          tombol.innerHTML = SPINNER_PROSES + '<span>' + formatLabel(i, total) + '</span>';
          requestAnimationFrame(lanjut);
        } else {
          tombol.innerHTML = isiAsli;
          tombol.disabled = false;
          selesai();
        }
      }
      requestAnimationFrame(lanjut);
    });
  }

  const tombolTambah = document.getElementById('tombol-tambah-pegawai');
  document.getElementById('modal-pegawai-tambahkan').addEventListener('click', function () {
    const terpilih = Array.from(
      document.querySelectorAll('#daftar-modal-pegawai .checkbox-modal-pegawai:checked')
    ).filter(function (c) { return !idTerpasang.has(c.value); });

    if (!terpilih.length) {
      tutupModal();
      return;
    }

    tutupModal();
    jalanBertahap(tombolTambah, function (i, total) {
      return 'Menambah ' + Math.min(i, total) + '/' + total + ' pegawai…';
    }, terpilih.map(function (c) {
      return function () { tambahBaris(c.value, c.dataset.nama); };
    }), 25).then(function () { segarkanCounter(); });
  });

  // ── Atur semua pegawai sekaligus ──
  const modalSemua = document.getElementById('modal-semua-pegawai');
  const shiftSemua = document.getElementById('modal-semua-shift');
  const hanyaDipilih = document.getElementById('modal-semua-terpilih');
  const jumlahSemua = document.getElementById('modal-semua-jumlah');

  function bukaModalSemua() {
    if (!modalSemua) return;
    jumlahSemua.textContent = idTerpasang.size;
    document.getElementById('modal-semus-periode').textContent = @json($bulan.' '.$tahun);
    shiftSemua.value = '';
    hanyaDipilih.checked = idTerpasang.size > 0;
    modalSemua.classList.remove('hidden');
    modalSemua.classList.add('flex');
  }
  function tutupModalSemua() {
    modalSemua.classList.add('hidden');
    modalSemua.classList.remove('flex');
  }

  const tombolSemua = document.getElementById('tombol-semua-pegawai');
  tombolSemua.addEventListener('click', bukaModalSemua);
  document.getElementById('modal-semua-tutup').addEventListener('click', tutupModalSemua);
  document.getElementById('modal-semua-batal').addEventListener('click', tutupModalSemua);
  modalSemua.addEventListener('click', function (e) { if (e.target === modalSemua) tutupModalSemua(); });

  document.getElementById('modal-semua-terapkan').addEventListener('click', function () {
    const idShift = shiftSemua.value;

    if (!idShift) {
      alert('Pilih shift terlebih dahulu.');
      return;
    }

    tutupModalSemua();

    // Baris untuk seluruh pegawai aktif dibuat dari template bila belum ada,
    // supaya yang disimpan tetap mencakup seluruh pegawai aktif.
    const tambah = hanyaDipilih.checked ? [] : semuaBarisModal
      .filter(function (row) { return !idTerpasang.has(row.dataset.id); })
      .map(function (row) {
        return function () {
          tambahBaris(row.dataset.id, row.querySelector('.checkbox-modal-pegawai').dataset.nama);
        };
      });

    return jalanBertahap(tombolSemua, function (i, total) {
      return 'Menyusun ' + Math.min(i, total) + '/' + total + ' pegawai…';
    }, tambah, 25)
      .then(function () {
        segarkanCounter();
        const target = Array.from(barisPegawai());

        if (target.length === 0) {
          alert('Belum ada pegawai aktif. Tambahkan pegawai terlebih dahulu.');
          return;
        }

        let terisi = 0;
        return jalanBertahap(tombolSemua, function (i, total) {
          return 'Mengisi ' + Math.min(i, total) + '/' + total + ' pegawai…';
        }, target.map(function (tr) {
          return function () { terisi += isiBaris(tr, idShift); };
        }), 20).then(function () {
          alert('Jadwal ' + terisi + ' sel sudah diisi untuk ' + target.length + ' pegawai. Klik "Simpan Jadwal" untuk menyimpan.');
        });
      });
  });

  formPeg?.addEventListener('submit', function (e) {
    if (idTerpasang.size === 0) {
      e.preventDefault();
      alert('Tambahkan minimal satu pegawai terlebih dahulu.');
      return;
    }

    // Kumpulkan baris jadi JSON sebelum form dikirim.
    const users = [];
    const grid = {};
    barisPegawai().forEach(function (tr) {
      const id = tr.getAttribute('data-user');
      if (!id) return;
      users.push(id);
      const selisih = {};
      tr.querySelectorAll('select.sel-hari').forEach(function (sel) {
        if (sel.value) selisih[sel.dataset.tanggal] = sel.value;
      });
      grid[id] = selisih;
    });

    formPeg.querySelector('#users-json-pegawai').value = JSON.stringify(users);
    formPeg.querySelector('#grid-json-pegawai').value = JSON.stringify(grid);
  });

  segarkanCounter();
});
</script>
@endsection
