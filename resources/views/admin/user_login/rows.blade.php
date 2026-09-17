@foreach($rows as $r)
<tr>
  <td>
    <strong>{{ $r->nama_lengkap }}</strong>
    <br><span class="teks-kecil teks-redup">{{ $r->email }}</span>
  </td>
  <td>
    {{ $r->unit_nama ?? '—' }}
    @if($r->sub_unit_nama)
      <br><span class="teks-kecil teks-redup">{{ $r->sub_unit_nama }}</span>
    @endif
  </td>
  <td class="angka">
    <span class="badge badge-biru">{{ (int) $r->jumlah_perangkat }} perangkat</span>
  </td>
  <td class="angka">
    @if($r->terakhir_aktif)
      {{ tgl_id($r->terakhir_aktif, false) }} · {{ jam_id($r->terakhir_aktif) }}
    @else
      —
    @endif
  </td>
  <td class="tengah">
    <div class="aksi-baris inline-flex">
      <button type="button" class="btn btn-navy btn-kecil btn-detail" data-user-id="{{ (int) $r->user_id }}">
        {!! ikon('info', 14) !!} Detail
      </button>
      @if((int) $r->jumlah_perangkat > 1)
        <form method="post" action="{{ route('admin.user_login.logout_semua') }}" class="form-logout-semua"
              data-nama="{{ $r->nama_lengkap }}">
          @csrf
          <input type="hidden" name="user_id" value="{{ (int) $r->user_id }}">
          <button type="submit" class="btn btn-bahaya btn-kecil">Logout Semua</button>
        </form>
      @endif
    </div>
  </td>
</tr>
@endforeach
@if($rows->isEmpty())
<tr><td colspan="5" class="tengah teks-redup">Tidak ada pengguna dengan sesi aktif.</td></tr>
@endif