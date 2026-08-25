@extends('layouts.pegawai')

@section('content')

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('kalender') !!} Ubah Jadwal Shift</h2>
    <span class="badge badge-abu">batas pengajuan {{ $batasJam }} jam sebelum shift mulai</span>
  </div>
  <p class="petunjuk">Pilih tanggal pada daftar jadwal mendatang, tentukan shift tujuan, lalu tuliskan alasan.
    Pengajuan diteruskan ke atasan langsung Anda dan jadwal otomatis diganti setelah disetujui.</p>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Tanggal</th><th>Jadwal Saat Ini</th><th>Ajukan Perubahan</th></tr>
      </thead>
      <tbody>
        @foreach($jadwalList as $baris)
          @php $j = $baris['jadwal']; @endphp
        <tr>
          <td>
            <strong>{{ tgl_id(\Carbon\Carbon::parse($j->tanggal_berlaku)->format('Y-m-d'), false) }}</strong><br>
            <span class="teks-kecil teks-redup">{{ \Carbon\Carbon::parse($j->tanggal_berlaku)->locale('id')->translatedFormat('l') }}</span>
          </td>
          <td>{!! label_shift($j->shift) !!}</td>
          <td>
            @if($baris['pengajuan'])
              <div class="flex items-center gap-2 flex-wrap">
                {!! badge_tahap($baris['pengajuan']->status) !!}
                <span class="teks-kecil teks-redup">
                  → {{ $baris['pengajuan']->shiftBaru?->kategori ?? '—' }}
                  @if($baris['pengajuan']->status === 'Menunggu')
                    <form method="post" action="{{ url('ubah-jadwal/batal/' . (int) $baris['pengajuan']->id) }}" class="inline"
                          onsubmit="return confirm('Batalkan pengajuan ini?');">
                      @csrf
                      <button type="submit" class="btn btn-bahaya btn-kecil">Batal</button>
                    </form>
                  @endif
                </span>
              </div>
            @elseif(! $baris['bisa'])
              <span class="badge badge-abu" title="{{ $baris['alasanBlok'] }}">Tidak dapat diajukan</span>
              <div class="catatan-anomali">{{ $baris['alasanBlok'] }}</div>
            @else
              <form method="post" action="{{ url('ubah-jadwal') }}" class="space-y-2">
                @csrf
                <input type="hidden" name="tanggal" value="{{ \Carbon\Carbon::parse($j->tanggal_berlaku)->format('Y-m-d') }}">
                <select name="shift_baru_id" required>
                  <option value="">— Pilih shift tujuan —</option>
                  @foreach($shiftList as $s)
                    @if((int) $s->id !== (int) $j->shift_id)
                      <option value="{{ $s->id }}">{{ $s->label() }}</option>
                    @endif
                  @endforeach
                </select>
                <input type="text" name="alasan" required maxlength="500" placeholder="Alasan pengajuan…">
                <button type="submit" class="btn btn-primer btn-kecil w-full">{!! ikon('surat', 14) !!} Ajukan</button>
              </form>
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $jadwalList)
        <tr><td colspan="3" class="tengah teks-redup">Tidak ada jadwal shift mendatang dalam 30 hari ke depan.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('log') !!} Riwayat Pengajuan Saya</h2></div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Diajukan</th><th>Tanggal Jadwal</th><th>Perubahan</th><th>Status</th><th>Catatan Keputusan</th></tr></thead>
      <tbody>
        @foreach($riwayat as $r)
        <tr>
          <td class="angka teks-kecil">{{ tgl_id($r->created_at, false) }} · {{ jam_id($r->created_at) }}</td>
          <td class="angka">{{ tgl_id($r->tanggal->format('Y-m-d'), false) }}</td>
          <td>{{ $r->shiftLama?->kategori ?? '—' }} → <strong>{{ $r->shiftBaru?->kategori ?? '—' }}</strong></td>
          <td>{!! badge_tahap($r->status === 'Ditolak' && str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon') ? 'Menunggu' : $r->status) !!}
            @if($r->status === 'Ditolak' && str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon'))
              <span class="badge badge-abu">Dibatalkan</span>
            @endif
          </td>
          <td class="teks-kecil">
            {{ $r->catatan_keputusan ?? '—' }}
            @if($r->diprosesOlehUser && ! str_contains((string) $r->catatan_keputusan, 'Dibatalkan oleh pemohon'))
              — {{ $r->diprosesOlehUser->nama_lengkap }}
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $riwayat)
        <tr><td colspan="5" class="tengah teks-redup">Belum ada pengajuan perubahan jadwal.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection
