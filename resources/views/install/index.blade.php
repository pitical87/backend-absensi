@extends('layouts.auth')

@section('content')

@if(! empty($galat))
  <div class="flash flash-error">{{ $galat }}</div>
@endif

@if($tahap === 'admin')
  <div class="flash flash-success">
    Database beserta seluruh tabel dan data master berhasil disiapkan.
    Langkah terakhir: buat akun <strong>Admin</strong> pertama.
  </div>
  <form method="post" action="{{ url('install') }}" autocomplete="off">
    @csrf
    <div class="form-grup">
      <label class="wajib">Nama Lengkap Admin</label>
      <input type="text" name="nama" required autofocus placeholder="cth. Administrator Kepegawaian">
    </div>
    <div class="form-grup">
      <label class="wajib">Email (untuk masuk)</label>
      <input type="email" name="email" required placeholder="admin@rsudmerauke.go.id">
    </div>
    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Password</label>
        <input type="password" name="password" required minlength="6">
      </div>
      <div class="form-grup">
        <label class="wajib">Konfirmasi Password</label>
        <input type="password" name="password2" required minlength="6">
      </div>
    </div>
    <button type="submit" class="btn btn-primer btn-blok">{!! ikon('centang', 17) !!} Selesaikan Pemasangan</button>
  </form>
@endif
@endsection
