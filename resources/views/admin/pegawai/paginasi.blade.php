@if($pegawai->hasPages() || ($pegawai->total() ?? 0) > 0)
<div class="paginasi">
    <span class="info">
        Menampilkan {{ $pegawai->firstItem() ?? 0 }}–{{ $pegawai->lastItem() ?? 0 }}
        dari {{ number_format($pegawai->total(), 0, ',', '.') }} pegawai
    </span>
    @if($pegawai->hasPages())
        @php
            $hal = (int) $pegawai->currentPage();
            $totalHal = (int) $pegawai->lastPage();
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
