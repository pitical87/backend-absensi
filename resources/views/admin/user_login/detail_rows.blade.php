@foreach($sesi as $s)
<tr>
  <td>
    <strong>{{ $s->namaPerangkat() }}</strong>
    @if($s->user_agent)
      <br><span class="teks-redup teks-kecil break-all">{{ $s->user_agent }}</span>
    @endif
  </td>
  <td class="angka">{{ $s->ip ?: '—' }}</td>
  <td class="angka">{{ tgl_id($s->created_at, false) }} · {{ jam_id($s->created_at) }}</td>
  <td class="angka">{{ $s->last_aktivitas ? tgl_id($s->last_aktivitas, false).' · '.jam_id($s->last_aktivitas) : '—' }}</td>
  <td class="angka">{{ tgl_id($s->expires_at, false) }} · {{ jam_id($s->expires_at) }}</td>
  <td class="tengah">
    @if($s->expires_at->lte(now()->addDays(2)))
      <span class="badge badge-amber">Segera kedaluwarsa</span>
    @else
      <span class="badge badge-hijau">Aktif</span>
    @endif
  </td>
  <td class="tengah">
    <form method="post" action="{{ route('admin.user_login.logout') }}" class="form-logout">
      @csrf
      <input type="hidden" name="token_id" value="{{ $s->id }}">
      <button type="submit" class="btn btn-bahaya btn-kecil">Logout</button>
    </form>
  </td>
</tr>
@endforeach
@if($sesi->isEmpty())
<tr><td colspan="7" class="tengah teks-redup">Tidak ada sesi aktif untuk pengguna ini.</td></tr>
@endif