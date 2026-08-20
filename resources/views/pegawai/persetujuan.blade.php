@extends('layouts.pegawai')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('centang') !!} Menunggu Persetujuan Saya</h2>
    <span class="badge badge-amber">{{ count($tugasSaya) }} pengajuan</span>
  </div>
  <p class="petunjuk">Anda melihat halaman ini karena posisi Anda (<strong>{{ $u['posisi'] }}</strong>)
    berperan dalam alur persetujuan Izin/Cuti pegawai.</p>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Lama</th>
            <th>Alamat</th><th>Alasan</th>            <th class="min-w-[260px]">Tindakan</th></tr>
      </thead>
      <tbody>
        @foreach($tugasSaya as $r)
        <tr>
          <td>
            <strong>{{ $r->user->nama_lengkap }}</strong>
            @if($r->user->nip)<br><span class="teks-kecil teks-redup">NIP {{ $r->user->nip }}</span>@endif
            <br><span class="teks-kecil teks-redup">{{ $r->user->unitKerja->nama ?? '—' }}@if(
                $r->user->subUnit->nama) — {{ $r->user->subUnit->nama }}@endif</span>
          </td>
          <td><strong>{{ $r->jenis_cuti ?: $r->jenis }}</strong></td>
          <td class="angka">{{ tgl_id($r->tanggal_mulai, false) }}@if(
              $r->tanggal_selesai !== $r->tanggal_mulai)<br>s.d. {{ tgl_id($r->tanggal_selesai, false) }}@endif</td>
          <td class="angka">{{ $r->lama_hari }} hr kerja</td>
          <td class="teks-kecil">{{ $r->alamat_izin ?? '—' }}</td>
          <td class="teks-kecil">
            {{ $r->keterangan }}
            @if($r->lampiran)
              <br><a href="{{ url('lampiran-izin/' . (int) $r->id) }}" target="_blank"
                     rel="noopener">Lihat lampiran</a>
            @endif
          </td>
          <td>
            <form method="post" action="{{ url('persetujuan/proses') }}" class="bilah-alat m-0">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $r->id }}">
              <input type="text" name="catatan" placeholder="Catatan (opsional)…" class="min-w-[120px]">
              <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil">Setujui</button>
              <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                      onclick="return confirm('Tolak pengajuan ini? Seluruh tahap berikutnya akan dibatalkan.');">Tolak</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $tugasSaya)
        <tr><td colspan="7" class="tengah teks-redup">Tidak ada pengajuan yang menunggu keputusan Anda saat ini.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('log') !!} Riwayat Keputusan Saya</h2></div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Putusan</th><th>Catatan</th></tr></thead>
      <tbody>
        @foreach($riwayatSaya as $r)
        <tr>
          <td class="angka teks-kecil">{{ tgl_id($r->waktu, false) }} · {{ jam_id($r->waktu) }}</td>
          <td>{{ $r->pengajuan->user->nama_lengkap }}</td>
          <td>{{ $r->pengajuan->jenis_cuti ?: $r->pengajuan->jenis }}</td>
          <td class="angka">{{ tgl_id($r->pengajuan->tanggal_mulai, false) }}</td>
          <td>{!! badge_tahap($r->status) !!}</td>
          <td class="teks-kecil">{{ $r->catatan ?? '—' }}</td>
        </tr>
        @endforeach
        @if(! $riwayatSaya)
        <tr><td colspan="6" class="tengah teks-redup">Belum ada riwayat keputusan.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
