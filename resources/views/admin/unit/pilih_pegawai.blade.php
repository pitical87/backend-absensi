{{-- Combo box pencarian nama pegawai.
     Tanpa JavaScript tetap berupa <select> biasa. Setelah JS aktif, select
     disembunyikan dan digantikan kotak cari + daftar hasil filter. --}}
@php
    $daftar = $daftar ?? ($pegawaiPilihan ?? collect());
    $terpilih = (int) ($terpilih ?? 0);
    $labelKosong = $labelKosong ?? '— Atasan —';
    $idOpsi = $idOpsi ?? 'cari-pegawai-' . \Illuminate\Support\Str::random(6);
@endphp

<div class="pilih-pegawai" data-pilih-pegawai>
  <select name="{{ $namaField }}"
          @if (! empty($idSelect)) id="{{ $idSelect }}" @endif
          class="{{ $kelasSelect ?? 'w-auto min-w-[190px] text-sm' }}"
          title="{{ $judul ?? 'Pilih nama pegawai' }}">
    <option value="">{{ $labelKosong }}</option>
    @foreach($daftar as $opt)
      <option value="{{ (int) $opt->id }}"
              data-cari="{{ \Illuminate\Support\Str::lower(trim($opt->nama_lengkap.' '.(isset($opt->nip) ? (string) $opt->nip : '').' '.(isset($opt->email) ? (string) $opt->email : ''))) }}"
              @selected($terpilih === (int) $opt->id)>{{ $opt->nama_lengkap }}</option>
    @endforeach
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