@extends('layouts.pegawai')

@section('content')

@if($cuti)
<section class="kartu">
  <div class="kartu-kepala"><h2>{!! ikon('kalender') !!} Hak Cuti Tahun {{ date('Y') }} (PNS)</h2></div>
  <div class="identitas-grid">
    <div class="item"><span>Hak Cuti Tahun Berjalan</span><strong>{{ $cuti['hak'] }} hari kerja</strong></div>
    <div class="item"><span>Cuti/Izin yang Telah Diambil</span><strong>{{ $cuti['terpakai'] }} hari kerja</strong></div>
    <div class="item"><span>Sisa Hak Cuti</span><strong>{{ $cuti['sisa'] }} hari kerja</strong></div>
  </div>
  <p class="petunjuk">Dihitung dari pengajuan <strong>Izin</strong> dan <strong>Cuti Tahunan</strong> yang telah
    disetujui penuh pada tahun berjalan (hari Minggu &amp; hari libur tidak dihitung). Cuti Sakit, Melahirkan,
    Alasan Penting, Besar, dan di Luar Tanggungan Negara memiliki ketentuan tersendiri dan tidak memotong jatah ini.</p>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('surat') !!} Ajukan Izin / Sakit / Cuti / Dinas Luar</h2>
    <a class="btn btn-garis btn-kecil" href="{{ route('dashboard') }}">&larr; Dasbor</a>
  </div>

  <form method="post" action="{{ route('izin') }}" enctype="multipart/form-data">
    @csrf

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Jenis Pengajuan</label>
        <select name="jenis" id="jenis" required>
          @foreach($jenisList as $j)
            <option>{{ $j }}</option>
          @endforeach
        </select>
        @if(!is_pns((object)$u))
          <div class="petunjuk">Cuti hanya dapat diajukan oleh pegawai berstatus PNS.</div>
        @endif
      </div>
      <div class="form-grup" id="grup-jenis-cuti" hidden>
        <label class="wajib">Jenis Cuti</label>
        <select name="jenis_cuti" id="jenis_cuti">
          <option value="">— Pilih —</option>
          @foreach($jenisCutiList as $jc)
            <option>{{ $jc }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Tanggal Mulai</label>
        <input type="date" name="tanggal_mulai" id="tanggal_mulai" required value="{{ date('Y-m-d') }}">
      </div>
      <div class="form-grup">
        <label>Tanggal Selesai</label>
        <input type="date" name="tanggal_selesai" id="tanggal_selesai">
        <div class="petunjuk" id="ket-lama">Kosongkan bila hanya satu hari.</div>
      </div>
    </div>

    <div class="form-grup" id="grup-alamat" hidden>
      <label class="wajib">Alamat Selama Izin/Cuti</label>
      <input type="text" name="alamat_izin" placeholder="cth. Jl. Trikora No. 5, Merauke">
    </div>

    <div class="form-grup">
      <label class="wajib">Alasan / Keperluan</label>
      <textarea name="keterangan" rows="3" required
        placeholder="cth. Sakit demam, surat keterangan dokter terlampir"></textarea>
    </div>

    <div class="form-grup">
      <label>Lampiran (opsional)</label>
      <input type="file" name="lampiran" accept=".jpg,.jpeg,.png,.pdf">
      <div class="petunjuk">Foto surat sakit / surat tugas / surat keterangan — JPG, PNG, atau PDF maks 3 MB.</div>
    </div>

    <button type="submit" class="btn btn-primer">{!! ikon('centang', 17) !!} Kirim Pengajuan</button>
  </form>
</section>

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('log') !!} Riwayat Pengajuan</h2>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr><th>Jenis</th><th>Tanggal</th><th>Lama</th><th>Keterangan</th><th>Alur Persetujuan</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($riwayat as $r)
        <tr>
          <td><strong>{{ $r->jenis_cuti ?: $r->jenis }}</strong></td>
          <td class="angka">{{ tgl_id($r->tanggal_mulai, false) }}@if($r->tanggal_selesai !== $r->tanggal_mulai)<br>s.d. {{ tgl_id($r->tanggal_selesai, false) }}@endif</td>
          <td class="angka">{{ $r->lama_hari ? $r->lama_hari . ' hr kerja' : '—' }}</td>
          <td>
            {{ $r->keterangan }}
            @if($r->alamat_izin)<br><span class="teks-kecil teks-redup">Alamat: {{ $r->alamat_izin }}</span>@endif
            @if($r->lampiran)
              <br><a class="teks-kecil" href="{{ url('lampiran-izin/' . (int) $r->id) }}"
                     target="_blank" rel="noopener">Lihat lampiran</a>
            @endif
          </td>
          <td class="teks-kecil">
            @if(! empty($tahapPer[$r->id]))
              @foreach($tahapPer[$r->id] as $t)
                {!! label_tahap_izin((int) $t->tahap) !!} {!! badge_tahap($t->status) !!}
                @if($t->oleh_nama)<span class="teks-redup">— {{ $t->oleh_nama }}</span>@endif
                <br>
              @endforeach
            @elseif($r->diproses_oleh)
              Diproses admin
            @else
              <span class="teks-redup">—</span>
            @endif
          </td>
          <td>{!! badge_izin($r->status) !!}</td>
          <td>
            @if($r->status === 'Menunggu')
              <form method="post" action="{{ url('izin/batal/' . (int) $r->id) }}"
                    onsubmit="return confirm('Batalkan pengajuan ini?');">
                @csrf
                <button type="submit" class="btn btn-bahaya btn-kecil">Batalkan</button>
              </form>
            @elseif($r->status === 'Disetujui' && in_array($r->jenis, ['Izin', 'Cuti'], true))
              <a class="btn btn-navy btn-kecil" href="{{ url('izin/dokumen/' . (int) $r->id) }}"
                 target="_blank" rel="noopener">{!! ikon('cetak', 14) !!} Dokumen</a>
            @endif
          </td>
        </tr>
        @endforeach
        @if(! $riwayat)
        <tr><td colspan="7" class="tengah teks-redup">Belum ada pengajuan.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

@endsection

@section('skrip')
<script>
(function () {
  var jenis   = document.getElementById('jenis');
  var jcGrup  = document.getElementById('grup-jenis-cuti');
  var jcSel   = document.getElementById('jenis_cuti');
  var alamat  = document.getElementById('grup-alamat');
  var BERJENJANG = ['Izin', 'Cuti'];
  function segarkan() {
    var isCuti = jenis.value === 'Cuti';
    jcGrup.hidden = ! isCuti;
    jcSel.required = isCuti;
    alamat.hidden = BERJENJANG.indexOf(jenis.value) === -1;
    alamat.querySelector('input').required = ! alamat.hidden;
  }
  jenis.addEventListener('change', segarkan);
  segarkan();
})();
</script>
@endsection
