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
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Pengajuan Ubah Jadwal Shift</h2>
    <span class="badge badge-amber">{{ count($tugasJadwal) }} menunggu</span>
  </div>
  <p class="petunjuk">Sebagai <strong>atasan langsung</strong>, Anda memutuskan pengajuan perubahan jadwal shift bawahan.
    Jika disetujui, jadwal pegawai otomatis diganti sesuai permintaan.</p>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Tanggal Jadwal</th><th>Perubahan</th><th>Alasan</th>
            <th class="min-w-[260px]">Tindakan</th></tr>
      </thead>
      <tbody>
        @foreach($tugasJadwal as $r)
        <tr>
          <td>
            <strong>{{ $r->user->nama_lengkap }}</strong>
            @if($r->user->nip)<br><span class="teks-kecil teks-redup">NIP {{ $r->user->nip }}</span>@endif
            <br><span class="teks-kecil teks-redup">{{ $r->user->unitKerja->nama ?? '—' }}@if(
                $r->user->subUnit->nama) — {{ $r->user->subUnit->nama }}@endif</span>
          </td>
          <td class="angka">
            {{ tgl_id($r->tanggal->format('Y-m-d'), false) }}<br>
            <span class="teks-kecil teks-redup">diajukan {{ tgl_id($r->created_at, false) }} · {{ jam_id($r->created_at) }}</span>
          </td>
          <td>{{ $r->shiftLama?->kategori ?? '—' }} → <strong>{{ $r->shiftBaru?->kategori ?? '—' }}</strong>
            <br><span class="teks-kecil teks-redup">{!! label_shift($r->shiftBaru) !!}</span></td>
          <td class="teks-kecil">{{ $r->alasan }}</td>
          <td>
            <form method="post" action="{{ url('persetujuan/jadwal') }}" class="bilah-alat m-0">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $r->id }}">
              <input type="text" name="catatan" placeholder="Catatan (opsional)…" class="min-w-[120px]">
              <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil"
                      onclick="return confirm('Setujui ubah jadwal ini? Jadwal pegawai akan langsung diganti.');">Setujui</button>
              <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                      onclick="return confirm('Tolak pengajuan ubah jadwal ini?');">Tolak</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $tugasJadwal)
        <tr><td colspan="5" class="tengah teks-redup">Tidak ada pengajuan ubah jadwal yang menunggu keputusan Anda.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('jam') !!} Pengajuan Lembur</h2>
    <span class="badge badge-amber">{{ count($tugasLembur) }} menunggu</span>
  </div>
  <p class="petunjuk">Sebagai <strong>atasan langsung</strong>, Anda memutuskan pengajuan lembur bawahan.
    Lembur hanya terhitung/absen setelah disetujui.</p>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Tanggal</th><th>Rentang Waktu</th><th>Durasi</th><th>Keterangan</th>
            <th class="min-w-[260px]">Tindakan</th></tr>
      </thead>
      <tbody>
        @foreach($tugasLembur as $r)
        <tr>
          <td>
            <strong>{{ $r->user->nama_lengkap }}</strong>
            @if($r->user->nip)<br><span class="teks-kecil teks-redup">NIP {{ $r->user->nip }}</span>@endif
            <br><span class="teks-kecil teks-redup">{{ $r->user->unitKerja->nama ?? '—' }}@if(
                $r->user->subUnit->nama) — {{ $r->user->subUnit->nama }}@endif</span>
          </td>
          <td class="angka">
            {{ tgl_id($r->tanggal->format('Y-m-d'), false) }}<br>
            <span class="teks-kecil teks-redup">diajukan {{ tgl_id($r->created_at, false) }} · {{ jam_id($r->created_at) }}</span>
          </td>
          <td class="angka">{{ jam_id($r->jam_mulai) }} — {{ jam_id($r->jam_selesai) }}</td>
          <td class="angka">{{ (float) $r->durasi_jam }} jam</td>
          <td class="teks-kecil">{{ $r->keterangan }}</td>
          <td>
            <form method="post" action="{{ url('persetujuan/lembur') }}" class="bilah-alat m-0">
              @csrf
              <input type="hidden" name="id" value="{{ (int) $r->id }}">
              <input type="text" name="catatan" placeholder="Catatan (opsional)…" class="min-w-[120px]">
              <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil"
                      onclick="return confirm('Setujui pengajuan lembur ini?');">Setujui</button>
              <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                      onclick="return confirm('Tolak pengajuan lembur ini?');">Tolak</button>
            </form>
          </td>
        </tr>
        @endforeach
        @if(! $tugasLembur)
        <tr><td colspan="6" class="tengah teks-redup">Tidak ada pengajuan lembur yang menunggu keputusan Anda.</td></tr>
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

  <h3 class="mt-6 mb-2 text-sm font-bold text-navy">Riwayat Keputusan Ubah Jadwal</h3>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Pegawai</th><th>Tanggal Jadwal</th><th>Perubahan</th><th>Putusan</th><th>Catatan</th></tr></thead>
      <tbody>
        @foreach($riwayatJadwalSaya as $r)
        <tr>
          <td class="angka teks-kecil">{{ tgl_id($r->diproses_pada, false) }} · {{ jam_id($r->diproses_pada) }}</td>
          <td>{{ $r->user->nama_lengkap }}</td>
          <td class="angka">{{ tgl_id($r->tanggal->format('Y-m-d'), false) }}</td>
          <td>{{ $r->shiftLama?->kategori ?? '—' }} → {{ $r->shiftBaru?->kategori ?? '—' }}</td>
          <td>{!! badge_tahap($r->status) !!}</td>
          <td class="teks-kecil">{{ $r->catatan_keputusan ?? '—' }}</td>
        </tr>
        @endforeach
        @if(! $riwayatJadwalSaya)
        <tr><td colspan="6" class="tengah teks-redup">Belum ada riwayat keputusan ubah jadwal.</td></tr>
        @endif
      </tbody>
    </table>
  </div>

  <h3 class="mt-6 mb-2 text-sm font-bold text-navy">Riwayat Keputusan Lembur</h3>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Waktu</th><th>Pegawai</th><th>Tanggal</th><th>Rentang Waktu</th><th>Putusan</th><th>Catatan</th></tr></thead>
      <tbody>
        @foreach($riwayatLemburSaya as $r)
        <tr>
          <td class="angka teks-kecil">{{ tgl_id($r->diproses_pada, false) }} · {{ jam_id($r->diproses_pada) }}</td>
          <td>{{ $r->user->nama_lengkap }}</td>
          <td class="angka">{{ tgl_id($r->tanggal->format('Y-m-d'), false) }}</td>
          <td class="angka">{{ jam_id($r->jam_mulai) }} — {{ jam_id($r->jam_selesai) }}</td>
          <td>{!! badge_tahap($r->status) !!}</td>
          <td class="teks-kecil">{{ $r->catatan_keputusan ?? '—' }}</td>
        </tr>
        @endforeach
        @if(! $riwayatLemburSaya)
        <tr><td colspan="6" class="tengah teks-redup">Belum ada riwayat keputusan lembur.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
