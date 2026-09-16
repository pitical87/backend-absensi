@extends('layouts.admin')

@section('content')

<div class="stat-admin mb-4">
  <div class="stat"><span>Pengguna Aktif Login</span><strong>{{ $totalPengguna }}</strong></div>
  <div class="stat hijau"><span>Perangkat / Sesi Aktif</span><strong>{{ $totalPerangkat }}</strong></div>
</div>

@foreach($perUser as $userId => $sesi)
<section class="kartu mb-4">
  <div class="kartu-kepala">
    <div>
      <h2 class="flex flex-wrap items-center gap-2">
        {!! ikon('pegawai') !!} {{ $sesi->first()->nama_lengkap }}
        <span class="badge badge-biru">{{ count($sesi) }} perangkat</span>
      </h2>
      @php
        $pg = $sesi->first();
      @endphp
      <p class="teks-redup teks-kecil mt-1">
        @if($pg->unit_nama){{ $pg->unit_nama }}@endif
        @if($pg->sub_unit_nama) — {{ $pg->sub_unit_nama }}@endif
      </p>
    </div>
    @if(count($sesi) > 1)
    <form method="post" action="{{ route('admin.user_login.logout_semua') }}"
          onsubmit="return confirm('Putus semua {{ count($sesi) }} sesi mobile milik {{ $sesi->first()->nama_lengkap }}?');">
      @csrf
      <input type="hidden" name="user_id" value="{{ $userId }}">
      <button type="submit" class="btn btn-bahaya btn-kecil">Logout Semua</button>
    </form>
    @endif
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead>
        <tr>
          <th>Perangkat</th>
          <th>IP</th>
          <th>Login sejak</th>
          <th>Terakhir aktif</th>
          <th>Kedaluwarsa</th>
          <th class="tengah">Status</th>
          <th class="tengah">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sesi as $s)
        <tr>
          <td>
            <strong>{{ $s->namaPerangkat() }}</strong>
            @if($s->user_agent)
              <br><span class="teks-redup teks-kecil">{{ $s->user_agent }}</span>
            @endif
          </td>
          <td class="angka">{{ $s->ip ?: '—' }}</td>
          <td class="angka">{{ tgl_id($s->created_at, false) }} · {{ jam_id($s->created_at) }}</td>
          <td class="angka">{{ $s->last_aktivitas ? tgl_id($s->last_aktivitas, false).' · '.jam_id($s->last_aktivitas) : '—' }}</td>
          <td class="angka">{{ tgl_id($s->expires_at, false) }} · {{ jam_id($s->expires_at) }}</td>
          <td class="tengah">
            @if($s->expires_at->lte(now()->addDays(2)))
              <span class="badge badge-amber">Segera kadaluwarsa</span>
            @else
              <span class="badge badge-hijau">Aktif</span>
            @endif
          </td>
          <td class="tengah">
            <form method="post" action="{{ route('admin.user_login.logout') }}"
                  onsubmit="return confirm('Putus sesi {{ $s->namaPerangkat() }} milik {{ $sesi->first()->nama_lengkap }} ini?');">
              @csrf
              <input type="hidden" name="token_id" value="{{ $s->id }}">
              <button type="submit" class="btn btn-garis btn-kecil">Logout</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endforeach

@if($perUser->isEmpty())
<section class="kartu">
  <p class="teks-redup text-center py-[30px]">
    Belum ada pengguna yang login melalui aplikasi mobile.
  </p>
</section>
@endif

@endsection