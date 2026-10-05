<div class="tabel-bungkus">
  <table class="tabel">
    <thead>
      <tr><th>Pegawai</th><th>Tanggal Jadwal</th><th>Perubahan</th><th>Alasan</th>
          <th>Status</th><th class="min-w-[260px]">Tindakan / Catatan</th></tr>
    </thead>
    <tbody>
      @forelse($daftar as $r)
      <tr>
        <td>
          <strong>{{ $r->user->nama_lengkap }}</strong>
          @if($r->user->nip)<br><span class="teks-kecil teks-redup">NIP {{ $r->user->nip }}</span>@endif
          <br><span class="teks-kecil teks-redup">{{ $r->user->unitKerja->nama ?? '—' }}@if(
            $r->user->subUnit?->nama) — {{ $r->user->subUnit->nama }}@endif</span>
        </td>
        <td class="angka">
          {{ tgl_id($r->tanggal->format('Y-m-d'), false) }}
          <br><span class="teks-kecil teks-redup">diajukan {{ tgl_id($r->created_at, false) }} · {{ jam_id($r->created_at) }}</span>
        </td>
        <td>{{ $r->shiftLama?->kategori ?? '—' }} → <strong>{{ $r->shiftBaru?->kategori ?? '—' }}</strong>
          <br><span class="teks-kecil teks-redup">{!! label_shift($r->shiftBaru) !!}</span></td>
        <td class="teks-kecil">{{ $r->alasan }}</td>
        <td>{!! badge_tahap($r->status) !!}</td>
        <td>
          @if($r->status === 'Menunggu')
            <form method="post" action="{{ route('admin.jadwal_pengajuan.proses') }}"
                  class="bilah-alat m-0" data-aksi-form>
              @csrf
              <input type="hidden" name="id" value="{{ (int) $r->id }}">
              <input type="text" name="catatan" placeholder="Catatan (opsional)…" class="min-w-[120px]">
              <button type="submit" name="putusan" value="setuju" data-putusan="setuju" class="btn btn-primer btn-kecil"
                      data-konfirmasi="Setujui? Jadwal pegawai akan langsung diganti.">Setujui</button>
              <button type="submit" name="putusan" value="tolak" data-putusan="tolak" class="btn btn-bahaya btn-kecil"
                      data-konfirmasi="Tolak pengajuan ini?">Tolak</button>
            </form>
          @else
            <span class="teks-kecil">
              {{ $r->catatan_keputusan ?? '—' }}
              @if($r->diprosesOlehUser)
                <br><span class="teks-redup">oleh {{ $r->diprosesOlehUser->nama_lengkap }} ·
                  {{ tgl_id($r->diproses_pada, false) }}</span>
              @endif
            </span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" class="tengah teks-redup">
          @if($q !== '')Tidak ada pengajuan berstatus {{ $status }} yang cocok dengan “{{ $q }}”.
          @else Tidak ada pengajuan berstatus {{ $status }}.@endif
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@include('admin.bersama.paginasi', ['rows' => $daftar, 'satuan' => 'pengajuan'])