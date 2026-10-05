{{-- Tab status; tanpa JavaScript tetap berupa tautan biasa. --}}
<div class="chips" id="tab-izin">
  @foreach($daftarStatus as $st)
    <a class="chip {{ $status === $st ? 'aktif' : '' }}"
       data-status="{{ $st }}"
       href="{{ url('admin/izin?status=' . urlencode($st)) }}">
      {{ $st }}
      @if($st !== 'Semua')<span class="jml">{{ (int) ($jumlah[$st] ?? 0) }}</span>@endif
    </a>
  @endforeach
</div>