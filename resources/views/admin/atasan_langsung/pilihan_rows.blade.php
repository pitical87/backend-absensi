@forelse($opsi as $o)
  <label class="baris-pilihan-atasan flex items-center gap-2.5 py-2 px-3 cursor-pointer hover:bg-slate-50">
    <input type="checkbox" name="atasan[]" value="{{ (int) $o->id }}" class="chk-atasan w-auto"
           data-nama="{{ $o->nama_lengkap }}">
    <span class="text-sm">{{ $o->nama_lengkap }}</span>
    @if($o->email)
      <span class="teks-redup text-xs truncate">{{ $o->email }}</span>
    @endif
  </label>
@empty
  <div class="py-6 tengah teks-redup text-sm">Kandidat atasan tidak ditemukan.</div>
@endforelse