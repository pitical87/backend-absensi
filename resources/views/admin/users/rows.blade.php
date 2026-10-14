@php $mulai = (int) ($users->firstItem() ?? 1); @endphp
@foreach($users as $u)
@php
  $terakhir = collect([$u->terakhir_login, $u->terakhir_mobile])
      ->filter()
      ->map(fn ($t) => \Illuminate\Support\Carbon::parse($t))
      ->max();
@endphp
<tr>
  <td class="angka">{{ $mulai + $loop->index }}</td>
  <td>
    <strong>{{ $u->nama_lengkap }}</strong>
    <br><span class="teks-kecil teks-redup">{{ $u->email }}</span>
  </td>
  <td class="angka">{{ $u->created_at ? tgl_id($u->created_at, false) : '—' }}</td>
  <td class="angka">
    @if($tab === 'deletes')
      @if($u->deleted_at)
        {{ tgl_id($u->deleted_at->toDateTimeString(), false) }}
        <br><span class="teks-kecil teks-redup">{{ jam_id($u->deleted_at->toDateTimeString()) }}</span>
      @else
        —
      @endif
      @if(! empty($u->catatan_hapus))
        <div class="mt-1">
          <button type="button" class="btn btn-garis btn-kecil btn-catatan"
                  data-nama="{{ $u->nama_lengkap }}"
                  data-email="{{ $u->email }}"
                  data-catatan="{{ $u->catatan_hapus }}"
                  data-oleh="{{ $u->catatan_oleh }}"
                  data-dihapus="{{ $u->deleted_at?->toDateTimeString() }}">
            {!! ikon('log', 13) !!} Catatan
          </button>
        </div>
      @endif
    @elseif($terakhir)
      {{ tgl_id($terakhir->toDateTimeString(), false) }}
      <br><span class="teks-kecil teks-redup">{{ jam_id($terakhir->toDateTimeString()) }}</span>
    @else
      <span class="teks-redup">Belum pernah</span>
    @endif
  </td>
  <td class="tengah">
    <div class="aksi-baris">
      @if($tab === 'deletes')
        <form method="post" action="{{ route('admin.users.pulihkan') }}" class="form-pulihkan"
              data-nama="{{ $u->nama_lengkap }}">
          @csrf
          <input type="hidden" name="id" value="{{ (int) $u->id }}">
          <button type="submit" class="btn btn-primer btn-kecil">Pulihkan</button>
        </form>

        <form method="post" action="{{ route('admin.users.hapus_permanen') }}" class="form-hapus-permanen"
              data-nama="{{ $u->nama_lengkap }}">
          @csrf
          <input type="hidden" name="id" value="{{ (int) $u->id }}">
          <button type="submit" class="btn btn-bahaya btn-kecil">{!! ikon('hapus', 14) !!} Hapus Permanen</button>
        </form>
      @else
        <a class="btn btn-garis btn-kecil" href="{{ route('admin.users.form', (int) $u->id) }}">
          {!! ikon('log', 14) !!} Update
        </a>

        <button type="button" class="btn btn-garis btn-kecil btn-ganti-password"
                data-id="{{ (int) $u->id }}" data-nama="{{ $u->nama_lengkap }}">
          {!! ikon('kunci', 14) !!} Password
        </button>

        @if((int) $u->id !== (int) session('uid'))
          <form method="post" action="{{ route('admin.users.status') }}" class="form-status"
                data-nama="{{ $u->nama_lengkap }}">
            @csrf
            <input type="hidden" name="id" value="{{ (int) $u->id }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <button type="submit" class="btn btn-kecil {{ $u->status === 'aktif' ? 'btn-garis' : 'btn-primer' }}">
              {{ $u->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
          </form>

          <button type="button" class="btn btn-bahaya btn-kecil btn-hapus"
                  data-id="{{ (int) $u->id }}" data-nama="{{ $u->nama_lengkap }}">
            {!! ikon('hapus', 14) !!} Hapus
          </button>
        @endif
      @endif
    </div>
  </td>
</tr>
@endforeach
@if($users->isEmpty())
<tr><td colspan="5" class="tengah teks-redup">Tidak ada data user.</td></tr>
@endif
