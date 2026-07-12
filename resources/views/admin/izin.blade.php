@extends('layouts.admin')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('surat') !!} Pengajuan Izin / Sakit / Cuti / Dinas Luar</h2>
  </div>

  <div class="chips">
    @foreach(['Menunggu', 'Disetujui', 'Ditolak', 'Semua'] as $st)
      <a class="chip {{ $status === $st ? 'aktif' : '' }}"
         href="{{ url('admin/izin?status=' . $st) }}">
        {{ $st }}
        @if($st !== 'Semua')<span class="jml">{{ (int) ($jumlah[$st] ?? 0) }}</span>@endif
      </a>
    @endforeach
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Pegawai</th><th>Jenis</th><th>Tanggal</th><th>Keterangan</th>
            <th>Alur / Tahap</th><th>Status</th><th style="min-width:260px">Tindakan / Catatan</th></tr>
      </thead>
      <tbody>
        @foreach($daftar as $r) @php $berjenjang = in_array($r->jenis, ['Izin', 'Cuti'], true); @endphp
        <tr>
          <td>
            <strong>{{ $r->nama_lengkap }}</strong>
            <br><span class="teks-kecil teks-redup">{{ $r->unit_nama ?? '—' }}@if(
              $r->sub_nama) — {{ $r->sub_nama }}@endif</span>
            @if($berjenjang)
              <br><span class="badge badge-ungu teks-kecil">{{ $r->posisi_pemohon }}</span>
            @endif
          </td>
          <td><strong>{{ $r->jenis_cuti ?: $r->jenis }}</strong>
            @if($r->lama_hari)<br><span class="teks-kecil teks-redup">{{ (int) $r->lama_hari }} hr kerja</span>@endif</td>
          <td class="angka">{{ tgl_id($r->tanggal_mulai, false) }}@if(
              $r->tanggal_selesai !== $r->tanggal_mulai)<br>s.d. {{ tgl_id($r->tanggal_selesai, false) }}@endif</td>
          <td class="teks-kecil">
            {{ $r->keterangan }}
            @if($r->alamat_izin)<br>Alamat: {{ $r->alamat_izin }}@endif
            @if($r->lampiran)
              <br><a href="{{ url('lampiran-izin/' . (int) $r->id) }}" target="_blank"
                     rel="noopener">Lihat lampiran</a>
            @endif
          </td>
          <td class="teks-kecil">
            @if($berjenjang && ! empty($tahapPer[$r->id]))
              @foreach($tahapPer[$r->id] as $t)
                @if($t->status === 'Dilewati' && ! $t->oleh_nama)
                  <span class="teks-redup">{!! label_tahap_izin((int) $t->tahap) !!}: dilewati</span><br>
                @else
                  {!! label_tahap_izin((int) $t->tahap) !!} {!! badge_tahap($t->status) !!}
                  @if($t->oleh_nama)<span class="teks-redup">({{ $t->oleh_nama }})</span>@endif<br>
                @endif
              @endforeach
            @elseif(! $berjenjang)
              <span class="teks-redup">Satu tahap (admin)</span>
            @endif
          </td>
          <td>{!! badge_izin($r->status) !!}</td>
          <td>
            @if(! $berjenjang && $r->status === 'Menunggu')
              <form method="post" action="{{ url('admin/izin/proses') }}" class="bilah-alat" style="margin:0">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $r->id }}">
                <input type="text" name="catatan" placeholder="Catatan (opsional)…" style="min-width:120px">
                <button type="submit" name="putusan" value="setuju" class="btn btn-primer btn-kecil">Setujui</button>
                <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                        onclick="return confirm('Tolak pengajuan ini?');">Tolak</button>
              </form>
            @elseif($berjenjang && $r->status === 'Menunggu')
              <form method="post" action="{{ url('admin/izin/ambil-alih') }}" class="bilah-alat" style="margin:0">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $r->id }}">
                <input type="text" name="catatan" placeholder="Catatan (opsional)…" style="min-width:120px">
                <button type="submit" name="putusan" value="setuju" class="btn btn-garis btn-kecil">Ambil Alih: Setujui</button>
                <button type="submit" name="putusan" value="tolak" class="btn btn-bahaya btn-kecil"
                        onclick="return confirm('Tolak pengajuan ini pada tahap saat ini?');">Ambil Alih: Tolak</button>
              </form>
              <div class="petunjuk">Sedang menunggu {!! label_tahap_izin((int) $r->tahap_aktif) !!}.
                Gunakan tombol ini hanya bila pejabat terkait belum terdaftar/berhalangan.</div>
            @else
              <span class="teks-kecil">
                {{ $r->catatan_admin ? $r->catatan_admin : ($r->nomor_surat ? 'No. ' . $r->nomor_surat : '—') }}
                @if($r->admin_nama)
                  <br><span class="teks-redup">oleh {{ $r->admin_nama }} ·
                    {{ tgl_id($r->processed_at, false) }}</span>
                @endif
              </span>
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $daftar)
        <tr><td colspan="7" class="tengah teks-redup">Tidak ada pengajuan berstatus {{ $status }}.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
  <p class="petunjuk">Izin/Sakit/Cuti yang <strong>disetujui</strong> otomatis tidak dihitung sebagai
    Alpa dan tidak menurunkan persentase kehadiran; <strong>Dinas Luar</strong> dihitung sebagai hadir.
    <strong>Izin</strong> dan <strong>Cuti</strong> berjalan melalui alur berjenjang (Koordinator → Kepala
    Seksi/Sub Bagian → Kepala Bidang/Bagian → HRD) yang diputus pejabat terkait di menu Persetujuan mereka;
    kolom "Ambil Alih" di sini hanya untuk keadaan darurat.</p>
</section>

@endsection
