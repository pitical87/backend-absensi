{{-- Baris tabel kehadiran. Dipakai untuk render awal dan hasil endpoint
     admin.kehadiran.data, sehingga tombol pada tiap baris selalu hidup. --}}
@forelse($rows as $r)
  <tr @class(['anomali-baris' => $r->flag_anomali])>
    <td>
      <strong>{{ $r->user?->nama_lengkap }}</strong>
      @if($r->user?->nip)
        <span class="teks-kecil teks-redup">{{ $r->user->nip }}</span>
      @endif
      <br><span class="teks-kecil teks-redup">{{ $r->user?->unitKerja?->nama ?? '—' }}@if(
        $r->user?->subUnit?->nama) — {{ $r->user->subUnit->nama }}@endif</span>
    </td>
    <td>
      <div class="foto-mini">
        @if($r->foto_masuk)
          <a href="{{ url('foto/' . (int) $r->id . '/datang') }}" target="_blank"
             rel="noopener" title="Selfie datang">
            <img src="{{ url('foto/' . (int) $r->id . '/datang') }}" alt="Datang" loading="lazy"></a>
        @endif
        @if($r->foto_pulang)
          <a href="{{ url('foto/' . (int) $r->id . '/pulang') }}" target="_blank"
             rel="noopener" title="Selfie pulang">
            <img src="{{ url('foto/' . (int) $r->id . '/pulang') }}" alt="Pulang" loading="lazy"></a>
        @endif
        @if(! $r->foto_masuk && ! $r->foto_pulang)
          <span class="teks-redup teks-kecil">—</span>
        @endif
      </div>
    </td>
    <td class="teks-kecil">{{ label_shift($r->shiftHariIni) }}</td>
    <td class="angka">In : {{ jam_id($r->waktu_masuk) }}<br>Out : {{ jam_id($r->waktu_pulang) }}</td>
    <td>{!! badge_status(! $r->waktu_pulang ? 'Belum Pulang'
           : ($r->status_masuk === 'Terlambat' ? 'Terlambat' : 'Tepat Waktu'),
           (int) $r->menit_terlambat) !!}</td>
    <td class="angka">{{ $r->logLokasiDatang?->jarak_meter !== null
          ? number_format((float) $r->logLokasiDatang->jarak_meter, 0, ',', '.') . ' m' : '—' }}</td>
    <td class="angka teks-kecil">
      @if($r->lat_masuk !== null)
        <a href="https://www.google.com/maps?q={{ $r->lat_masuk }},{{ $r->lng_masuk }}"
           target="_blank" rel="noopener">
          {{ number_format((float) $r->lat_masuk, 5) }}, {{ number_format((float) $r->lng_masuk, 5) }}
        </a>
      @else —@endif
    </td>
    <td class="tengah whitespace-nowrap">
      <button type="button" class="btn btn-garis btn-kecil btn-ubah-absen"
              data-id="{{ (int) $r->id }}"
              data-user="{{ (int) $r->user_id }}"
              data-tanggal="{{ $r->tanggal->format('Y-m-d') }}"
              data-masuk="{{ $r->waktu_masuk->format('H:i') }}"
              data-pulang="{{ $r->waktu_pulang?->format('H:i') }}">Ubah</button>
      <form method="post" action="{{ route('admin.kehadiran.hapus') }}" class="inline-block"
            data-hapus>
        @csrf
        <input type="hidden" name="id" value="{{ (int) $r->id }}">
        <button type="submit" class="btn btn-bahaya btn-kecil"
                data-konfirmasi="Hapus absensi {{ $r->user?->nama_lengkap }} pada tanggal ini?">Hapus</button>
      </form>
      @if($r->flag_anomali)
        <button type="button" class="btn btn-kecil btn-bahaya text-red-800 mt-1.5"
                data-anomali-nama="{{ $r->user?->nama_lengkap }}"
                data-anomali-keterangan="{{ $r->catatan_anomali ?? 'Terindikasi anomali GPS' }}"
                title="Lihat detail anomali">{!! ikon('peringatan', 12) !!}</button>
      @endif
    </td>
  </tr>
@empty
  <tr><td colspan="8" class="tengah teks-redup">Tidak ada catatan kehadiran pada tanggal ini.</td></tr>
@endforelse