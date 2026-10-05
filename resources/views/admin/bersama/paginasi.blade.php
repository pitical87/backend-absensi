@if($rows->total() > 0)
<div class="paginasi">
    <span class="info">
        Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }}
        dari {{ number_format($rows->total(), 0, ',', '.') }} {{ $satuan }}
    </span>
    @if($rows->hasPages())
        @php
            $hal = (int) $rows->currentPage();
            $totalHal = (int) $rows->lastPage();
        @endphp
        @if($hal > 1)
            <a href="{{ $rows->url($hal - 1) }}" data-hal="{{ $hal - 1 }}">«</a>
        @endif
        @for($h = max(1, $hal - 3); $h <= min($totalHal, $hal + 3); $h++)
            @if($h === $hal)
                <span class="aktif">{{ $h }}</span>
            @else
                <a href="{{ $rows->url($h) }}" data-hal="{{ $h }}">{{ $h }}</a>
            @endif
        @endfor
        @if($hal < $totalHal)
            <a href="{{ $rows->url($hal + 1) }}" data-hal="{{ $hal + 1 }}">»</a>
        @endif
    @endif
</div>
@endif