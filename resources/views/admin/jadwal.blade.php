@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Pilih Sub Unit & Bulan</h2></div>
  <form method="get" action="{{ url('admin/jadwal') }}" class="bilah-alat">
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
        <option value="{{ $m }}" {{ $m === $bulan ? 'selected' : '' }}>
          {{ BULAN_ID[$m] }}
        </option>
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
      <h2>{!! ikon('jam') !!} Grid Jadwal — {{ BULAN_ID[$bulan] }} {{ $tahun }}</h2>
      <button type="submit" class="btn btn-primer btn-kecil">Simpan Semua</button>
    </div>

    <div class="tabel-bungkus" style="overflow-x:auto">
      <table class="tabel jadwal-grid" style="min-width:{{ 180 + ($hariDalamBulan * 52) }}px">
        <thead>
          <tr>
            <th style="position:sticky;left:0;background:var(--putih);z-index:2;min-width:160px">Nama Pegawai</th>
            @for($d = 1; $d <= $hariDalamBulan; $d++)
              @php
                $dt = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
                $isLibur = in_array($dt->dayOfWeek, [Carbon\Carbon::SUNDAY]);
              @endphp
              <th class="angka" style="min-width:52px;{{ $isLibur ? 'color:var(--merah)' : '' }}" title="{{ $dt->translatedFormat('l') }}">{{ $d }}</th>
            @endfor
            <th style="min-width:60px;text-align:center" title="Isi semua tanggal dengan shift yang sama">Isi Semua</th>
          </tr>
          <tr style="font-size:.72rem;color:var(--teks-redup)">
            <th style="position:sticky;left:0;background:var(--latar);z-index:2;font-size:.72rem;font-weight:400"></th>
            @for($d = 1; $d <= $hariDalamBulan; $d++)
              @php
                $dt = Carbon\Carbon::createFromDate($tahun, $bulan, $d);
              @endphp
              <th style="font-weight:400;font-size:.72rem;{{ in_array($dt->dayOfWeek, [Carbon\Carbon::SUNDAY]) ? 'color:var(--merah)' : '' }}">
                {{ substr($dt->translatedFormat('D'), 0, 2) }}
              </th>
            @endfor
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($pegawai as $p)
          <tr>
            <td style="position:sticky;left:0;background:var(--putih);z-index:1;white-space:nowrap">
              <strong>{{ $p->nama_lengkap }}</strong>
            </td>
            @for($d = 1; $d <= $hariDalamBulan; $d++)
              @php
                $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
                $val = $jadwal[$p->id][$tgl] ?? '';
              @endphp
              <td style="padding:2px;text-align:center">
                <select name="grid[{{ $p->id }}][{{ $tgl }}]"
                        data-user="{{ $p->id }}"
                        class="jadwal-select"
                        style="width:50px;padding:2px 1px;font-size:.72rem;border:1px solid var(--garis);border-radius:4px;background:{{ $val ? 'var(--biru-muda)' : 'var(--latar)' }}">
                  <option value="">—</option>
                  @foreach($shiftList as $s)
                    <option value="{{ (int) $s->id }}" {{ $val == $s->id ? 'selected' : '' }}
                      data-warna="{{ match($s->kategori) { 'Pagi' => '#178A50', 'Sore' => '#C2540A', 'Malam' => '#0B3B66', default => '#5C7189' } }}">
                      {{ substr($s->kategori, 0, 1) }}
                    </option>
                  @endforeach
                </select>
              </td>
            @endfor
            <td style="padding:2px;text-align:center">
              <select class="jadwal-isiSemua" data-user="{{ $p->id }}" style="width:48px;padding:2px;font-size:.7rem;border:1px solid var(--garis);border-radius:4px">
                <option value="">Isi…</option>
                @foreach($shiftList as $s)
                  <option value="{{ (int) $s->id }}">{{ substr($s->kategori, 0, 1) }}</option>
                @endforeach
              </select>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <p class="petunjuk" style="margin-top:12px">
      Pilihan: <strong>—</strong> = Libur / tidak ada jadwal.
      Kode: P = Pagi, S = Sore, M = Malam (warna mengikuti shift).
      Minggu ditandai merah. Gunakan "Isi Semua" untuk mengisi satu shift ke seluruh tanggal sekaligus.
    </p>
  </section>
</form>

@elseif($subUnitId)

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Grid Jadwal</h2></div>
  <p class="teks-redup" style="text-align:center;padding:30px 0">Tidak ada pegawai aktif di sub unit ini.</p>
</section>

@endif

@endsection

@section('skrip')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Isi Semua: set semua select tanggal untuk user ini
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

  // Warna latar select saat berubah
  document.querySelectorAll('.jadwal-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      this.style.background = this.value ? 'var(--biru-muda)' : 'var(--latar)';
    });
  });
});
</script>
@endsection
