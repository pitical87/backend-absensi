{{-- Combo box pencarian nama pegawai.
     Tanpa JavaScript tetap berupa <select> biasa. Setelah JS aktif, select
     disembunyikan dan digantikan kotak cari + daftar hasil filter. --}}
@php
    $opsiLengkap = $opsiLengkap ?? false;
    $daftar = $daftar ?? ($pegawaiPilihan ?? collect());
    $terpilih = (int) ($terpilih ?? 0);
    $labelKosong = $labelKosong ?? '— Atasan —';
    $idOpsi = $idOpsi ?? 'cari-pegawai-' . \Illuminate\Support\Str::random(6);
    $pilih = $terpilih > 0 ? $daftar->firstWhere('id', $terpilih) : null;
@endphp

<div class="pilih-pegawai" data-pilih-pegawai>
  <select name="{{ $namaField }}"
          @if (! empty($idSelect)) id="{{ $idSelect }}" @endif
          class="{{ $kelasSelect ?? 'w-auto min-w-[190px] text-sm' }}"
          title="{{ $judul ?? 'Pilih nama pegawai' }}">
    <option value="">{{ $labelKosong }}</option>
    @if($opsiLengkap)
      {{-- Daftar lengkap (dipakai satu-satunya di modal ubah unit supaya nilai
           terpilih bisa diatur sebelum opsi JS dirender). --}}
      @foreach($daftar as $opt)
        <option value="{{ (int) $opt->id }}"
                @selected($terpilih === (int) $opt->id)>{{ $opt->nama_lengkap }}</option>
      @endforeach
    @else
      {{-- Daftar lengkap pegawai diisi lewat JS dari window.PEGAWAI_UNIT agar
           tidak diduplikasi pada setiap combo (menjaga ukuran halaman kecil). --}}
      @if($pilih)
        <option value="{{ (int) $pilih->id }}" selected>{{ $pilih->nama_lengkap }}</option>
      @endif
    @endif
  </select>

  <div class="pilih-pegawai-kotak">
    <div class="pilih-pegawai-baris">
      <input type="text" id="{{ $idOpsi }}"
             class="pilih-pegawai-cari w-full min-w-[150px] text-sm"
             placeholder="{{ $placeholderCari ?? 'Cari nama / NIP…' }}"
             autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list">
      <span class="pilih-pegawai-terpilih"></span>
    </div>
    <ul class="pilih-pegawai-daftar hidden" role="listbox"></ul>
  </div>
</div>