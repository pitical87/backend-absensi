<div class="flex flex-wrap gap-2 p-1">
  <button type="button" data-tab="semua" class="tab-unit {{ $mode === 'semua' ? 'aktif' : '' }}">
    Semua Unit Kerja
    <span>{{ $unitList->count() }}</span>
  </button>

  @foreach($unitList as $uk)
    <button type="button" data-tab="{{ (int) $uk->id }}"
            class="tab-unit {{ $mode === 'unit' && $unitAktif && (int) $unitAktif->id === (int) $uk->id ? 'aktif' : '' }}">
      {{ $uk->nama }}
      <span>{{ (int) $uk->jml_sub }}</span>
    </button>
  @endforeach

  <button type="button" data-tab="tambah" data-buka-modal="modal-tambah"
          class="tab-unit tambah {{ $mode === 'tambah' ? 'aktif' : '' }}">
    {!! ikon('tambah', 14) !!} Tambah Unit
  </button>
</div>