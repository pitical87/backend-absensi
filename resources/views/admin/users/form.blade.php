@php $v = fn ($k, $def = '') => old($k, $edit->$k ?? $def); @endphp
@extends('layouts.admin')

@section('content')

@if ($errors->any())
<section class="kartu border-red-300 mb-4">
  <div class="flex items-start gap-3 text-red-700">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
      <p class="font-semibold">Periksa kembali isian formulir:</p>
      <ul class="list-disc list-inside mt-1">
        @foreach ($errors->all() as $galat)
          <li>{{ $galat }}</li>
        @endforeach
      </ul>
    </div>
  </div>
</section>
@endif

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Ubah User: {{ $edit->nama_lengkap }}</h2>
    <a class="btn btn-garis btn-kecil" href="{{ url('admin/users?tab=' . $tab) }}">&larr; Kembali</a>
  </div>

  <div class="mb-4 flex flex-wrap items-center gap-2">
    <span class="badge {{ $edit->role === 'admin' ? 'badge-ungu' : 'badge-biru' }}">
      {{ $edit->role === 'admin' ? 'Administrator' : 'Pegawai' }}
    </span>
    {!! $edit->status === 'aktif'
        ? '<span class="badge badge-hijau">Aktif</span>'
        : '<span class="badge badge-abu">Nonaktif</span>' !!}
  </div>

  <form method="post" action="{{ route('admin.users.simpan') }}">
    @csrf
    <input type="hidden" name="id" value="{{ (int) $edit->id }}">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <div class="form-grup">
      <label class="wajib">Nama Lengkap</label>
      <input type="text" name="nama_lengkap" required value="{{ $v('nama_lengkap') }}">
    </div>

    <div class="form-grup">
      <label class="wajib">Email</label>
      <input type="email" name="email" required value="{{ $v('email') }}">
    </div>

    <div class="form-grup">
      <label>Status Akun</label>
      <select name="status">
        <option value="aktif" {{ old('status', $edit->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
        <option value="nonaktif" {{ old('status', $edit->status ?? '') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
      </select>
      <div class="petunjuk">Akun nonaktif tidak dapat login ke aplikasi.</div>
    </div>

    <div class="aksi-baris">
      <button type="submit" class="btn btn-primer">{!! ikon('centang', 17) !!} Simpan</button>
      <a href="{{ url('admin/users?tab=' . $tab) }}" class="btn btn-garis">Batal</a>
    </div>
  </form>
</section>

@endsection
