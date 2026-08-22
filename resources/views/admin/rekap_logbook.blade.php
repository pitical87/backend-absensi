@extends('layouts.admin')

@section('content')

@php
  $namaBulan = [1 => 'Januari','Februari','Maret','April','Mei','Juni',
                'Juli','Agustus','September','Oktober','November','Desember'];
  $tahunKini = now()->year;
@endphp

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('log') !!} Rekap Logbook per Pegawai</h2>
    <span class="badge badge-biru">{{ number_format($total, 0, ',', '.') }} pegawai</span>
  </div>

  <form method="get" action="{{ url('admin/rekap_logbook') }}" class="bilah-alat">
    <select name="bulan">
      @foreach($namaBulan as $i => $nm)
        <option value="{{ $i }}" {{ $bulan === $i ? 'selected' : '' }}>{{ $nm }}</option>
      @endforeach
    </select>
    <select name="tahun">
      @for($y = $tahunKini + 1; $y >= $tahunKini - 5; $y--)
        <option value="{{ $y }}" {{ $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
      @endfor
    </select>
    <input type="text" name="q" placeholder="Cari nama pegawai…" value="{{ $q }}">
    <button type="submit" class="btn btn-navy btn-kecil">Tampilkan</button>
    <a href="{{ route('admin.rekap_logbook.cetak', array_filter(['bulan' => $bulan, 'tahun' => $tahun, 'q' => $q ?: null])) }}"
       target="_blank" rel="noopener" class="btn btn-garis btn-kecil">{!! ikon('cetak', 13) !!} Cetak</a>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Nama Pegawai</th><th>Unit / Bidang</th><th>Jumlah Hari Kerja</th><th class="tengah">Aksi</th></tr></thead>
      <tbody>
        @foreach($daftar as $r)
        <tr>
          <td>
            <strong>{{ $r->nama_lengkap }}</strong>
            @if($r->status !== 'aktif')
              <span class="badge badge-merah ml-1">nonaktif</span>
            @endif
            @if($r->nip)
              <br><span class="teks-kecil teks-redup">NIP {{ $r->nip }}</span>
            @endif
          </td>
          <td>{{ $r->unit_nama }}@if($r->sub_nama) — {{ $r->sub_nama }}@endif</td>
          <td class="angka">
            <span class="badge {{ $r->jumlah_hari > 0 ? 'badge-hijau' : 'badge-amber' }}">
              {{ (int) $r->jumlah_hari }} hari
            </span>
          </td>
          <td class="tengah">
            <button type="button" class="btn btn-garis btn-kecil tombol-detail-rekap"
                    data-id="{{ $r->id }}" data-nama="{{ $r->nama_lengkap }}">Detail</button>
          </td>
        </tr>
        @endforeach
        @if(! $daftar)
        <tr><td colspan="4" class="tengah teks-redup">
          Tidak ada pegawai yang cocok dengan filter.
        </td></tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($totalHal > 1)
  <div class="paginasi">
    @php
      $dasar = 'admin/rekap_logbook?' . http_build_query([
        'bulan' => $bulan, 'tahun' => $tahun, 'q' => $q ?: null,
      ]);
      $pisah = str_contains($dasar, '?') && ! str_ends_with($dasar, '?') ? '&' : '';
    @endphp
    @for($h = max(1, $halaman - 3); $h <= min($totalHal, $halaman + 3); $h++)
      @if($h === $halaman)
        <span class="aktif">{{ $h }}</span>
      @else
        <a href="{{ url($dasar . $pisah . 'hal=' . $h) }}">{{ $h }}</a>
      @endif
    @endfor
    <span class="info">hal. {{ $halaman }} / {{ $totalHal }}</span>
  </div>
  @endif
</section>

{{-- Modal Detail Logbook Pegawai --}}
<div id="modal-detail-rekap" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
  <div class="kartu w-full max-w-2xl">
    <div class="kartu-kepala">
      <h2>{!! ikon('log') !!} Detail Logbook</h2>
      <button type="button" id="detail-tutup" class="btn btn-garis btn-kecil">&times;</button>
    </div>
    <div class="px-4 pt-3">
      <strong id="detail-nama">—</strong>
      <br><span class="teks-redup teks-kecil" id="detail-subjudul">&nbsp;</span>
    </div>
    <div class="px-4 pt-3 pb-1 max-h-[60vh] overflow-y-auto" id="detail-isi"></div>
    <p class="teks-redup teks-kecil px-4 pb-2" id="detail-pesan"></p>
    <div class="flex justify-end gap-2 px-4 py-3">
      <button type="button" id="detail-cetak" class="btn btn-navy btn-kecil" disabled>{!! ikon('cetak', 13) !!} Cetak</button>
      
    </div>
  </div>
</div>

@endsection

@section('script')
<script>
(function () {
  const modal   = document.getElementById('modal-detail-rekap');
  const isiD    = document.getElementById('detail-isi');
  const namaD   = document.getElementById('detail-nama');
  const subD    = document.getElementById('detail-subjudul');
  const pesanD  = document.getElementById('detail-pesan');

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  const bulanNama = ['Januari','Februari','Maret','April','Mei','Juni',
                     'Juli','Agustus','September','Oktober','November','Desember'];

  function tanggalIndo(tgl) {
    const p = tgl.split('-');
    return p[2] + ' ' + bulanNama[parseInt(p[1], 10) - 1] + ' ' + p[0];
  }

  let detailTerakhir = null;

  function buka(id, nama) {
    namaD.textContent = nama;
    subD.innerHTML = '&nbsp;';
    pesanD.textContent = '';
    isiD.innerHTML = '<p class="teks-redup text-sm">Memuat…</p>';
    document.getElementById('detail-cetak').disabled = true;
    detailTerakhir = null;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    const params = new URLSearchParams({
      user_id: String(id),
      bulan: '{{ $bulan }}',
      tahun: '{{ $tahun }}',
    });

    fetch('{{ url("admin/rekap_logbook/detail") }}?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
    })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          pesanD.textContent = h.pesan || 'Gagal memuat detail.';
          isiD.innerHTML = '';
          return;
        }
        subD.textContent = h.unit + ' · ' + bulanNama[{{ $bulan }} - 1] + ' {{ $tahun }}'
                        + ' · ' + h.total_hari + ' hari, ' + h.total_entri + ' entri';
        if (! h.total_entri) {
          isiD.innerHTML = '<p class="teks-redup text-sm tengah">Belum ada logbook pada periode ini.</p>';
          return;
        }
        detailTerakhir = h;
        document.getElementById('detail-cetak').disabled = false;
        let html = '<table class="tabel">'
                 + '<thead><tr><th class="w-20">Jam</th><th>Isi</th></tr></thead><tbody>';
        Object.keys(h.data).forEach(function (tgl) {
          html += '<tr class="bg-slate-50"><td colspan="3" class="font-semibold">'
                + esc(tanggalIndo(tgl)) + '</td></tr>';
          h.data[tgl].forEach(function (e) {
            html += '<tr>'
                  + '<td class="whitespace-nowrap font-semibold">' + esc(e.jam) + '</td>'
                  + '<td>' + esc(e.isi).replace(/\n/g, '<br>') + '</td>'
                  + '</tr>';
          });
        });
        html += '</tbody></table>';
        isiD.innerHTML = html;
      })
      .catch(function () {
        pesanD.textContent = 'Terjadi kesalahan jaringan.';
        isiD.innerHTML = '';
      });
  }

  function tutup() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  function cetak() {
    const d = detailTerakhir;
    if (! d) return;

    let baris = '';
    Object.keys(d.data).forEach(function (tgl) {
      baris += '<tr class="baris-tanggal"><td colspan="3">' + tanggalIndo(tgl) + '</td></tr>';
      d.data[tgl].forEach(function (e) {
        baris += '<tr>'
               + '<td class="kolom-jam">' + esc(e.jam) + '</td>'
               + '<td>' + esc(e.isi).replace(/\n/g, '<br>') + '</td>'
               + '</tr>';
      });
    });

    const periode = bulanNama[{{ $bulan }} - 1] + ' {{ $tahun }}';
    const kini = new Date();
    const dicetak = ('0' + kini.getDate()).slice(-2) + ' ' + bulanNama[kini.getMonth()] + ' ' + kini.getFullYear();

    const html = '<!doctype html><html><head><meta charset="utf-8"><title>Detail Logbook — '
      + esc(d.nama) + '</title><style>'
      + 'body{font-family:Georgia,"Times New Roman",serif;font-size:12pt;color:#000;margin:0}'
      + '.kop{display:flex;align-items:center;gap:14px;border-bottom:3px double #000;padding-bottom:10px}'
      + '.kop img{height:64px;width:64px;object-fit:contain}'
      + '.kop .teks{flex:1;text-align:center;line-height:1.35}'
      + '.kop .l1{font-size:13pt;font-weight:bold;text-transform:uppercase}'
      + '.kop .l2{font-size:16pt;font-weight:bold;text-transform:uppercase;letter-spacing:.5px}'
      + '.kop .l3{font-size:9pt}'
      + '.judul{text-align:center;font-weight:bold;text-transform:uppercase;text-decoration:underline;margin:18px 0 2px;font-size:13pt}'
      + '.subjudul{text-align:center;margin-bottom:14px;font-size:10pt}'
      + '.identitas{width:auto;margin:0 auto 14px;font-size:11pt;border-collapse:collapse}'
      + '.identitas td{padding:1px 6px 1px 0;vertical-align:top}'
      + 'table.data{width:100%;border-collapse:collapse;font-size:11pt}'
      + 'table.data th,table.data td{border:1px solid #000;padding:5px 8px;text-align:left;vertical-align:top}'
      + 'table.data th{background:#e8eef4;text-transform:uppercase;font-size:10pt}'
      + 'tr.baris-tanggal td{background:#f2f2f2;font-weight:bold}'
      + 'td.kolom-jam{white-space:nowrap;width:60px}'
      + '.footer{margin-top:16px;font-size:9pt;color:#444;display:flex;justify-content:space-between}'
      + '@page{size:A4;margin:15mm}'
      + '</style></head><body>'
      + '<div class="kop"><img src="{{ asset("assets/img/logo.svg") }}">'
      + '<div class="teks">'
      + '<div class="l1">Pemerintah Kabupaten Merauke</div>'
      + '<div class="l2">{{ strtoupper($namaInstansi) }}</div>'
      + '<div class="l3">Jalan Raya Merauke–Kurik, Kabupaten Merauke, Papua Selatan</div>'
      + '</div><div style="width:64px"></div></div>'
      + '<div class="judul">Detail Logbook Pegawai</div>'
      + '<div class="subjudul">Periode ' + periode + '</div>'
      + '<table class="identitas">'
      + '<tr><td>Nama Pegawai</td><td>:</td><td><strong>' + esc(d.nama) + '</strong></td></tr>'
      + '<tr><td>Unit / Bidang</td><td>:</td><td>' + esc(d.unit) + '</td></tr>'
      + '<tr><td>Jumlah Hari Kerja</td><td>:</td><td>' + d.total_hari + ' hari</td></tr>'
      + '<tr><td>Jumlah Entri</td><td>:</td><td>' + d.total_entri + ' entri</td></tr>'
      + '</table>'
      + '<table class="data"><thead><tr><th style="width:60px">Jam</th><th>Isi Aktivitas</th></tr></thead>'
      + '<tbody>' + baris + '</tbody></table>'
      + '<div class="footer"><span>Dicetak pada ' + dicetak + '</span>'
      + '<span>Sistem Absensi {{ strtolower($namaInstansi) }}</span></div>'
      + '</body></html>';

    const f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(f);
    const doc = f.contentDocument;
    doc.open();
    doc.write(html);
    doc.close();
    setTimeout(function () {
      f.contentWindow.focus();
      f.contentWindow.print();
      setTimeout(function () { f.remove(); }, 1000);
    }, 300);
  }

  document.getElementById('detail-cetak').addEventListener('click', cetak);

  document.querySelectorAll('.tombol-detail-rekap').forEach(function (t) {
    t.addEventListener('click', function () { buka(t.dataset.id, t.dataset.nama); });
  });
  document.getElementById('detail-tutup').addEventListener('click', tutup);
  document.getElementById('detail-batal').addEventListener('click', tutup);
  modal.addEventListener('click', function (e) { if (e.target === modal) tutup(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ! modal.classList.contains('hidden')) tutup();
  });
})();
</script>
@endsection
