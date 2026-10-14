@if($users->hasPages() || ($users->total() ?? 0) > 0)
<div class="paginasi">
    <span class="info">
        Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}
        dari {{ number_format($users->total(), 0, ',', '.') }} user
    </span>
    @if($users->hasPages())
        @php
            $hal = (int) $users->currentPage();
            $totalHal = (int) $users->lastPage();
        @endphp
        @if($hal > 1)
            <a href="#" data-page="{{ $hal - 1 }}">«</a>
        @endif
        @for($h = max(1, $hal - 3); $h <= min($totalHal, $hal + 3); $h++)
            @if($h === $hal)
                <span class="aktif">{{ $h }}</span>
            @else
                <a href="#" data-page="{{ $h }}">{{ $h }}</a>
            @endif
        @endfor
        @if($hal < $totalHal)
            <a href="#" data-page="{{ $hal + 1 }}">»</a>
        @endif
    @endif
</div>
@endif
