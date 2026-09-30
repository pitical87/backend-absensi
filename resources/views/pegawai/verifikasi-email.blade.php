@extends('layouts.pegawai')

@section('content')

@php $masaBerlaku = \App\Services\VerifikasiEmailService::MASA_BERLAKU_MENIT; @endphp

<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('perisai') !!} Verifikasi Email</h2>
    <a class="btn btn-garis btn-kecil" href="{{ route('dashboard') }}">&larr; Dasbor</a>
  </div>
  <p class="petunjuk">
    Verifikasi email memastikan alamat email yang tercatat pada akun Anda benar-benar dapat
    Anda akses. Email yang belum diverifikasi tidak menghalangi Anda masuk maupun melakukan absensi.
  </p>

  @if ($status['terverifikasi'])
    <div class="flash flash-success">
      Email <strong>{{ $status['email'] }}</strong> sudah terverifikasi pada
      {{ tgl_id($u->email_verified_at) }}. Verifikasi tidak perlu diulang.
    </div>
  @else
    <div class="flash flash-info">
      Email <strong>{{ $status['email'] }}</strong> belum diverifikasi. Tautan verifikasi berlaku
      {{ $masaBerlaku }} menit sejak dikirim.
    </div>
  @endif

  @if ($status['menunggu'])
    <div class="flash flash-info">
      Tautan verifikasi sudah dikirim ke <strong>{{ $status['email'] }}</strong> dan masih menunggu
      Anda klik.@if ($status['sisa_detik'] > 0) Belum menerima emailnya? Tunggu {{ $status['sisa_detik'] }} detik lagi sebelum mengirim ulang.@endif
    </div>
  @endif

  <form method="post" action="{{ route('verifikasi-email.kirim') }}">
    @csrf

    @if (! $status['terverifikasi'])
      <div class="form-grup">
        <label class="wajib">Alamat Email Akun</label>
        <input
          type="email"
          name="email"
          required
          maxlength="150"
          value="{{ old('email', $u->email) }}"
          placeholder="nama@rsudmerauke.id"
        >
        <div class="petunjuk">
          Harus sama dengan email akun Anda. Untuk mengganti email, ubah terlebih dahulu pada halaman
          <a href="{{ route('pegawai.update-data') }}" class="underline">Update Data</a>.
        </div>
      </div>
    @endif

    <div class="form-baris">
      <div class="form-grup">
        <label>Status</label>
        <div class="py-2.5 text-sm font-semibold {{ $status['terverifikasi'] ? 'text-emerald-700' : 'text-slate-700' }}">
          {{ $status['terverifikasi'] ? 'Terverifikasi' : 'Belum diverifikasi' }}
        </div>
      </div>
      <div class="form-grup">
        <label>Diverifikasi pada</label>
        <div class="py-2.5 text-sm text-slate-700">
          {{ $u->email_verified_at ? tgl_id($u->email_verified_at) : '—' }}
        </div>
      </div>
    </div>

    @if (! $status['terverifikasi'])
      <div class="pt-2">
        <button type="submit" class="btn btn-navy">
          Kirim Tautan Verifikasi
        </button>
      </div>
    @endif
  </form>
</section>

@endsection
