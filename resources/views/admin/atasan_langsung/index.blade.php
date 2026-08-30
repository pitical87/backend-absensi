@extends('layouts.admin')

@section('content')

{{-- ===== HEADER ===== --}}
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('struktur') !!} Atasan Langsung</h2>
  </div>
  <p class="teks-redup text-sm">
    Satu pegawai dapat memiliki beberapa atasan. Pegawai baru otomatis mewarisi atasan dari
    <strong>sub unit</strong> (bila terisi) atau <strong>unit kerja</strong> — atur di menu
    <a href="{{ url('admin/unit') }}" class="text-[#007afc] underline">Data Unit Kerja</a>.
    Pengaturan manual di halaman ini tidak akan ditimpa otomatis.
  </p>
</section>

{{-- ===== DAFTAR PEGAWAI ===== --}}
@php $namaOpt = $pilihan->pluck('nama_lengkap', 'id'); @endphp
<section class="kartu">
  <div class="kartu-kepala">
    <h2>Daftar Pegawai</h2>
    <span class="badge badge-biru">{{ count($pegawai) }} Pegawai</span>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Pegawai</th>
          <th>Unit / Sub Unit</th>
          <th>Atasan Langsung</th>
          <th class="w-[110px]">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pegawai as $p)
          @php $listAtasan = ($relasi[$p->id] ?? collect())->pluck('atasan_id')->all(); @endphp
          <tr>
            <td>
              <div class="font-medium">{{ $p->nama_lengkap }}</div>
              <div class="teks-redup text-xs">{{ $p->email }}</div>
            </td>
            <td>
              @if($p->unit_nama || $p->sub_nama)
                {{ trim(($p->unit_nama ?? '').($p->unit_nama && $p->sub_nama ? ' / ' : '').($p->sub_nama ?? '')) }}
              @else
                <span class="teks-redup">—</span>
              @endif
            </td>
            <td>
              <div class="flex flex-wrap gap-1">
                @forelse($listAtasan as $idA)
                  <span class="badge badge-hijau">{{ $namaOpt[$idA] ?? '#'.$idA }}</span>
                @empty
                  <span class="teks-redup text-sm">Belum diatur</span>
                @endforelse
              </div>
            </td>
            <td>
              <button type="button" class="btn btn-navy btn-kecil"
                      onclick="bukaModal({{ (int) $p->id }})">
                Atur
              </button>
            </td>
          </tr>
        @endforeach
        @if(count($pegawai) === 0)
          <tr><td colspan="4" class="tengah teks-redup py-6">Belum ada pegawai.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>

{{-- ===== MODAL ATUR ATASAN ===== --}}
<div id="modal-atasan" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
     onclick="if(event.target===this)tutupModal()">
  <div class="kartu w-full max-w-lg max-h-[85vh] overflow-y-auto" onclick="event.stopPropagation()">
    <div class="flex items-start justify-between gap-3 mb-3">
      <h3 class="text-base font-bold text-navy">Atur Atasan — <span id="judul-modal"></span></h3>
      <button type="button" class="btn btn-abu btn-kecil" onclick="tutupModal()">×</button>
    </div>

    <form method="post" action="{{ url('admin/atasan_langsung/aksi') }}">
      @csrf
      <input type="hidden" name="user_id" id="modal-user-id">

      <input type="text" id="cari-atasan" placeholder="Cari nama pegawai…"
             class="w-full mb-2" oninput="saringAtasan(this.value)">

      <div id="daftar-atasan" class="max-h-[45vh] overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 mb-4">
        @foreach($pilihan as $opt)
          <label class="baris-modal-atasan flex items-center gap-2.5 py-2 px-3 cursor-pointer hover:bg-slate-50"
                 data-nama="{{ strtolower($opt->nama_lengkap) }}">
            <input type="checkbox" name="atasan[]" value="{{ (int) $opt->id }}" class="chk-atasan w-auto">
            <span class="text-sm">{{ $opt->nama_lengkap }}</span>
          </label>
        @endforeach
        @if(count($pilihan) === 0)
          <div class="py-6 tengah teks-redup text-sm">Belum ada kandidat atasan.</div>
        @endif
      </div>

      <div class="flex justify-end gap-2">
        <button type="button" class="btn btn-abu" onclick="tutupModal()">Batal</button>
        <button type="submit" class="btn btn-primer">Simpan Atasan</button>
      </div>
    </form>
  </div>
</div>

@endsection

@section('script')
@php
    $dataPegawai = $pegawai->map(fn ($p) => [
        'id'     => (int) $p->id,
        'nama'   => $p->nama_lengkap,
        'atasan' => array_map('intval', ($relasi[$p->id] ?? collect())->pluck('atasan_id')->all()),
    ])->values();
@endphp
<script>
const DATA_PEGAWAI = @json($dataPegawai);

function bukaModal(id) {
  const p = DATA_PEGAWAI.find(x => x.id === Number(id));
  if (!p) return;

  document.getElementById('modal-user-id').value = p.id;
  document.getElementById('judul-modal').textContent = p.nama;

  const kotak = document.getElementById('cari-atasan');
  kotak.value = '';
  saringAtasan('');

  const dipilih = new Set(p.atasan);
  document.querySelectorAll('.chk-atasan').forEach(c => { c.checked = dipilih.has(Number(c.value)); });

  const modal = document.getElementById('modal-atasan');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function tutupModal() {
  const modal = document.getElementById('modal-atasan');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

function saringAtasan(kata) {
  kata = kata.toLowerCase();
  document.querySelectorAll('#daftar-atasan .baris-modal-atasan').forEach(b => {
    b.style.display = b.dataset.nama.includes(kata) ? '' : 'none';
  });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });
</script>
@endsection
