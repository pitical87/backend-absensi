{{-- Baris tabel percobaan absen yang ditolak (di luar radius). --}}
@forelse($ditolak as $d)
  <tr>
    <td class="angka">{{ jam_id($d->waktu) }}</td>
    <td>{{ $d->user?->nama_lengkap }}</td>
    <td>Absen {{ ucfirst((string) $d->tipe) }}</td>
    <td class="angka">{{ number_format((float) $d->jarak_meter, 0, ',', '.') }} m</td>
    <td class="angka teks-kecil">
      <a href="https://www.google.com/maps?q={{ $d->latitude }},{{ $d->longitude }}"
         target="_blank" rel="noopener">
        {{ number_format((float) $d->latitude, 5) }}, {{ number_format((float) $d->longitude, 5) }}</a>
    </td>
  </tr>
@empty
  <tr><td colspan="5" class="tengah teks-redup">Tidak ada percobaan absen yang ditolak pada tanggal ini.</td></tr>
@endforelse