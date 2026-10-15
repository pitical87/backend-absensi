@php $adaPegawai = $pegawai->total() > 0; @endphp
<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr>
        <th>#</th>
        <th>Nama Pegawai</th>
        <th>Unit</th>
        <th>Hari Efektif</th>
        <th>Hadir</th>
        <th>Tepat</th>
        <th>Telat</th>
        <th>Alpa</th>
        <th>Izin</th>
        <th>Sakit</th>
        <th>Cuti</th>
        <th>Dinas</th>
        <th>Libur</th>
        <th>Total Jam</th>
        <th>%</th>
        <th title="Rata-rata bintang ketepatan waktu (pegawai teladan = bintang 5)">Bintang</th>
      </tr>
    </thead>
    <tbody>
      @forelse($pegawai as $p)
      @php $r = $rekapPer[(int) $p->id]; @endphp
      <tr>
        <td>{{ $pegawai->firstItem() + $loop->index }}</td>
        <td><strong>{{ $p->nama_lengkap }}</strong><br>
            <span class="teks-kecil teks-redup">{{ $p->jabatan_nama
                  ?? ($p->jabatan_kategori ?? '') }}@if(
                  $p->profesi_nama) · {{ $p->profesi_nama }}@endif</span></td>
        <td>{{ $p->unit_nama ?? '—' }}@if($p->sub_nama) — {{ $p->sub_nama }}@endif</td>
        <td class="angka">{{ $r['hari_efektif'] }}</td>
        <td class="angka">{{ $r['hadir'] }}</td>
        <td class="angka"><span class="badge badge-hijau">{{ $r['tepat'] }}</span></td>
        <td class="angka"><span class="badge badge-amber">{{ $r['terlambat'] }}</span></td>
        <td class="angka"><span class="badge badge-merah">{{ $r['alpa'] }}</span></td>
        <td class="angka">{{ $r['izin'] }}</td>
        <td class="angka">{{ $r['sakit'] }}</td>
        <td class="angka">{{ $r['cuti'] }}</td>
        <td class="angka">{{ $r['dinas_luar'] }}</td>
        <td class="angka"><span class="badge badge-abu">{{ $r['libur'] }}</span></td>
        <td class="angka">{{ menit_ke_teks($r['total_menit']) }}</td>
        <td class="angka"><strong>{{ $r['persen'] }}%</strong></td>
        <td class="angka">
          @if($r['bintang_bulanan'] === null)
            <span class="teks-redup">—</span>
          @else
            @php $bi = app(\App\Services\BintangService::class); @endphp
            <span title="{{ number_format((float) $r['bintang_bulanan'], 1) }} dari 5"
                  class="{{ (float) $r['bintang_bulanan'] >= 4.5 ? 'teks-bintang' : 'teks-redup' }}">
              {{ $bi->simbol((int) round((float) $r['bintang_bulanan'], 0, PHP_ROUND_HALF_UP)) }}
            </span>
            @if((float) $r['bintang_bulanan'] >= 4.5)
              <span class="badge badge-emas">Teladan</span>
            @endif
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="16" class="tengah teks-redup">
          @if(($q ?? '') !== '')
            Tidak ada pegawai yang cocok dengan “{{ $q }}”.
          @else
            Tidak ada pegawai pada filter ini.
          @endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $pegawai, 'satuan' => 'pegawai'])
