<div class="flex flex-wrap gap-2 p-1">
  <a href="{{ url('admin/unit') }}" data-tab="semua" class="tab-unit {{ $mode === 'semua' ? 'aktif' : '' }}">
    Semua Unit Kerja
    <span>{{ $unitList->count() }}</span>
  </a>

  @foreach($unitList as $uk)
    <a href="{{ url('admin/unit') }}?tab={{ (int) $uk->id }}" data-tab="{{ (int) $uk->id }}"
       class="tab-unit {{ $mode === 'unit' && $unitAktif && (int) $unitAktif->id === (int) $uk->id ? 'aktif' : '' }}">
      {{ $uk->nama }}
      <span>{{ (int) $uk->jml_sub }}</span>
    </a>
  @endforeach

  <a href="{{ url('admin/unit') }}?tab=tambah" data-buka-modal="modal-tambah"
     class="tab-unit tambah {{ $mode === 'tambah' ? 'aktif' : '' }}">
    {!! ikon('tambah', 14) !!} Tambah Unit
  </a>
</div>