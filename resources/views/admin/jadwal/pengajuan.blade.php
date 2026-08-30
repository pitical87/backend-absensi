@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Pengajuan Perubahan Jadwal Shift</h2>
    <span class="badge badge-amber">{{ $menunggu }} menunggu</span>
  </div>

  <div class="chips">
    @foreach(['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st)
      <a class="chip {{ $status === $st ? 'aktif' : '' }}"
         href="{{ url('admin/jadwal_pengajuan?status=' . $st) }}">{{ $st }}</a>
    @endforeach
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Tanggal Jadwal</th><th>Perubahan</th><th>Alasan</th>
            <th>Status</th><th class="min-w-[260px]">Tindakan / Catatan</th></tr>
      </thead>
      <tbody>
        @foreach($daftar as $r)
        <tr>
          <td>
            <strong>{{ $r->user->nama_lengkap }}</strong>
            @if($r->user->nip)<br><span class="teks-kecil teks-redup">NIP {{ $r->user->nip }}</span>@endif
            <br><span class="teks-kecil teks-redup">{{ $r->user->unitKerja->nama ?? '—' }}@if(
              $r->user->subUnit->nama) — {{ $r->user->subUnit->nama }}@endif</span>
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
              <form method="post" action="{{ url('admin/jadwal_pengajuan/proses') }}" class="bilah-alat m-0">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $r->id }}">
                <input type="text" name="catatan" placeholder="Catatan (opsional)…" class="min-w-[120px]">
                <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil"
                        onclick="return confirm('Setujui? Jadwal pegawai akan langsung diganti.');">Setujui</button>
                <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                        onclick="return confirm('Tolak pengajuan ini?');">Tolak</button>
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
        @endforeach
        @if(! $daftar)
        <tr><td colspan="6" class="tengah teks-redup">Tidak ada pengajuan berstatus {{ $status }}.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <p class="petunjuk">Pengajuan ubah jadwal diputus atasan langsung pegawai di halaman Persetujuan mereka.
    Halaman ini untuk monitoring dan penanganan cadangan bila atasan langsung tidak tersedia.
    Menyetujui di sini juga langsung mengganti jadwal shift pegawai pada tanggal terkait.</p>
</section>

@endsection
