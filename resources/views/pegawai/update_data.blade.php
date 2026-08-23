@extends('layouts.pegawai')

@section('content')

{{-- ============ FORM DATA DIRI (DAPAT DIUBAH) ============ --}}
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('pegawai') !!} Data Diri Saya</h2>
    <a class="btn btn-garis btn-kecil" href="{{ route('dashboard') }}">&larr; Dasbor</a>
  </div>
  <p class="petunjuk">Perbarui data diri Anda pada formulir berikut. Data kepegawaian di bawah hanya dapat diubah oleh administrator.</p>

  <form method="post" action="{{ route('pegawai.update-data.simpan') }}">
    @csrf

    @if($errors->any())
      <div class="flash flash-error">
        @foreach($errors->all() as $pesan)<div>{{ $pesan }}</div>@endforeach
      </div>
    @endif

    <div class="form-grup">
      <label class="wajib">Nama Lengkap</label>
      <input type="text" name="nama_lengkap" required maxlength="150" value="{{ old('nama_lengkap', $u->nama_lengkap) }}">
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label class="wajib">Email Login</label>
        <input type="email" name="email" required maxlength="150" value="{{ old('email', $u->email) }}">
        <div class="petunjuk">Email digunakan untuk masuk ke aplikasi.</div>
      </div>
      <div class="form-grup">
        <label>No. HP / WhatsApp</label>
        <input type="text" name="no_hp" maxlength="30" placeholder="cth. 081234567890" value="{{ old('no_hp', $u->no_hp) }}">
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" maxlength="100" placeholder="cth. Merauke" value="{{ old('tempat_lahir', $u->tempat_lahir) }}">
      </div>
      <div class="form-grup">
        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" max="{{ date('Y-m-d') }}" value="{{ old('tanggal_lahir', optional($u->tanggal_lahir)->format('Y-m-d')) }}">
      </div>
    </div>

    <div class="form-baris">
      <div class="form-grup">
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin">
          <option value="">— Pilih —</option>
          @foreach(['Laki-Laki', 'Perempuan'] as $jk)
            <option value="{{ $jk }}" {{ old('jenis_kelamin', $u->jenis_kelamin) === $jk ? 'selected' : '' }}>{{ $jk }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-grup">
        <label>Agama</label>
        <select name="agama">
          <option value="">— Pilih —</option>
          @foreach($agamaList as $ag)
            <option value="{{ $ag }}" {{ old('agama', $u->agama) === $ag ? 'selected' : '' }}>{{ $ag }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primer">{!! ikon('centang', 17) !!} Simpan Perubahan</button>
  </form>
</section>

{{-- ============ MAPPING AKUN SIMRS ============ --}}
<section class="bg-white border border-slate-200/80 rounded-2xl sm:rounded-[20px] shadow-[0_10px_30px_rgba(0,40,90,0.04)] p-5 sm:p-6 mb-5 transition-all">
  <div class="flex items-center justify-between gap-3 flex-wrap mb-4 pb-3 border-b border-slate-100">
    <h2 class="m-0 flex items-center gap-2.5 text-base sm:text-lg font-bold text-navy">
      <span class="text-blue-600">{!! ikon('perisai', 20) !!}</span> Integrasi Akun SIMRS
    </h2>
    <div class="flex items-center gap-2">
      <button type="button" id="btn-test-simrs"
              class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-xl text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-all active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-wait">
        {!! ikon('centang', 14) !!} Tes Koneksi
      </button>
      <button type="button" id="btn-mapping-manual"
              class="inline-flex items-center gap-1.5 py-1.5 px-3 rounded-xl text-xs font-semibold text-white bg-gradient-to-tr from-[#0062d1] to-[#007afc] hover:brightness-110 border border-transparent shadow-sm transition-all active:scale-95 cursor-pointer">
        {!! ikon('atur', 14) !!} {{ $u->mappingSimrs ? 'Ubah Mapping' : 'Mapping Manual' }}
      </button>
    </div>
  </div>

  @if($u->mappingSimrs)
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-emerald-50 border border-emerald-200/60 p-4">
      <span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200 whitespace-nowrap">TerMapping</span>
      <div class="min-w-0 flex-1">
        <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-emerald-600/80 font-medium">ID Akun SIMRS</span>
        <strong class="block text-sm font-bold text-slate-800 mt-0.5 truncate">{{ $u->mappingSimrs->simrs_user_id }}</strong>
      </div>
    </div>
    <p class="text-[0.75rem] text-slate-500 mt-3 mb-0">Akun absensi Anda telah tersambung dengan akun SIMRS. </p>
  @else
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 border border-amber-200/60 p-4">
      <span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-amber-100 text-amber-800 border border-amber-200 whitespace-nowrap">Belum di Mapping</span>
      <p class="text-sm text-slate-600 m-0 flex-1 min-w-0">Akun SIMRS Anda belum terhubung dengan aplikasi absensi.</p>
    </div>
    <p class="text-[0.75rem] text-slate-500 mt-3 mb-0">Silakan hubungi administrator untuk melakukan mapping ID akun SIMRS.</p>
  @endif

  <div id="hasil-test-simrs" class="hidden mt-3 text-sm"></div>
</section>

{{-- ============ MODAL MAPPING MANUAL ============ --}}
<div id="modal-mapping-manual" class="modal-tirai">
  <div class="modal-kamera max-w-md w-full">
    <header class="flex items-center justify-between">
      <span class="flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
        </svg> Mapping Manual SIMRS
      </span>
      <button type="button" class="btn-tutup-modal text-white/80 hover:text-white bg-transparent border-0 cursor-pointer text-lg leading-none">&times;</button>
    </header>
    <div class="isi">
      <form action="{{ route('pegawai.update-data.mapping') }}" method="POST" class="space-y-3.5">
        @csrf
        <div class="form-grup mb-0">
          <label class="wajib">ID Akun SIMRS</label>
          <div class="flex items-start gap-2">
            <input type="text" id="input-simrs-id" name="simrs_user_id" required maxlength="100"
                   placeholder="Masukkan NIK sesuai database SIMRS" autocomplete="off"
                   value="{{ old('simrs_user_id', $u->mappingSimrs->simrs_user_id ?? '') }}" class="flex-1">
            <button type="button" id="btn-check-simrs"
                    class="btn btn-garis btn-kecil whitespace-nowrap shrink-0 disabled:opacity-60 disabled:cursor-wait">Check</button>
          </div>
          <div class="petunjuk">Klik <strong>Check</strong> untuk memverifikasi ID dan menampilkan nama Anda di SIMRS sebelum menyimpan.</div>
        </div>

        <div id="hasil-check-simrs"></div>

        <p class="text-[0.75rem] text-slate-500 mb-0">Tombol simpan hanya aktif setelah pengecekan berhasil.</p>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button type="button" class="btn btn-garis btn-kecil btn-tutup-modal">Batal</button>
          <button type="submit" id="btn-simpan-mapping" class="btn btn-primer btn-kecil" disabled>Simpan Mapping</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ============ INFORMASI KEPEGAWAIAN (HANYA LIHAT) ============ --}}
<section class="bg-white border border-slate-200/80 rounded-2xl sm:rounded-[20px] shadow-[0_10px_30px_rgba(0,40,90,0.04)] p-5 sm:p-6 mb-5 transition-all">
  <h2 class="m-0 flex items-center gap-2.5 text-base sm:text-lg font-bold text-navy mb-4 pb-3 border-b border-slate-100">
    <span class="text-blue-600">{!! ikon('log', 20) !!}</span> Informasi Kepegawaian
  </h2>

  <div class="grid grid-cols-[repeat(auto-fit,_minmax(150px,_1fr))] gap-2.5">
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">NIP</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->nip ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">NIK</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->nik ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Status Pegawai</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->status_pegawai ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Unit Kerja</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->unitKerja?->nama ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Sub Unit</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->subUnit?->nama ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Profesi</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->profesi?->nama ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Jabatan</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->jabatan?->nama ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Kategori Jabatan</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->jabatan_kategori ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Posisi Aplikasi</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ $u->posisi ?? '—' }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Shift</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{!! label_shift($u->shift) !!}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Status Akun</span>
      <strong class="block text-sm font-semibold {{ ($u->status ?? '') === 'aktif' ? 'text-emerald-700' : 'text-red-700' }} mt-0.5 truncate">{{ ucfirst((string) ($u->status ?? '')) }}</strong>
    </div>
    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100">
      <span class="block text-[0.68rem] uppercase tracking-[0.08em] text-slate-400 font-medium">Terdaftar Sejak</span>
      <strong class="block text-sm font-semibold text-slate-800 mt-0.5 truncate">{{ tgl_id(optional($u->created_at)->format('Y-m-d'), false) }}</strong>
    </div>
  </div>
</section>

@endsection

@section('script')
<script>
(function () {
  const btn = document.getElementById('btn-test-simrs');
  const hasil = document.getElementById('hasil-test-simrs');
  if (! btn || ! hasil) return;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  btn.addEventListener('click', function () {
    btn.disabled = true;
    hasil.classList.remove('hidden');
    hasil.innerHTML = '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-slate-100 text-slate-600 border border-slate-200">Memeriksa koneksi ke SIMRS&hellip;</span>';

    fetch('{{ route("pegawai.update-data.cek-simrs") }}', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          hasil.innerHTML = '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-red-50 text-red-700 border border-red-200">' + esc(h.pesan || 'Gagal terhubung ke database SIMRS.') + '</span>';
          return;
        }
        if (! h.data) {
          hasil.innerHTML = '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-amber-50 text-amber-800 border border-amber-200">ID SIMRS tidak ditemukan pada database SIMRS</span>';
          return;
        }
        hasil.innerHTML = '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Berhasil, data pada SIMRS : ' + esc(h.data.nik) + ' &mdash; ' + esc(h.data.nama) + '</span>';
      })
      .catch(function () {
        hasil.innerHTML = '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-red-50 text-red-700 border border-red-200">Terjadi kesalahan jaringan.</span>';
      })
      .finally(function () {
        btn.disabled = false;
      });
  });

  // ── Modal Mapping Manual ──
  const btnManual = document.getElementById('btn-mapping-manual');
  const modalManual = document.getElementById('modal-mapping-manual');
  const btnCheck = document.getElementById('btn-check-simrs');
  const inputKode = document.getElementById('input-simrs-id');
  const hasilCheck = document.getElementById('hasil-check-simrs');
  const btnSimpan = document.getElementById('btn-simpan-mapping');

  function bukaModal() { if (modalManual) modalManual.classList.add('terbuka'); }
  function tutupModal() {
    if (modalManual) modalManual.classList.remove('terbuka');
    hasil.classList.add('hidden');
    hasil.innerHTML = '';
    btn.disabled = false;
  }

  function badge(kelas, teks) {
    return '<span class="inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold ' + kelas + '">' + teks + '</span>';
  }

  function resetCheck() {
    if (btnSimpan) btnSimpan.disabled = true;
    if (hasilCheck) hasilCheck.innerHTML = '';
  }

  function jalankanCheck() {
    if (! btnCheck || ! inputKode) return;
    const kode = inputKode.value.trim();
    hasilCheck.classList.remove('hidden');

    if (kode === '') {
      hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-red-50 text-red-700 border border-red-200', 'Masukkan ID SIMRS terlebih dahulu.');
      if (btnSimpan) btnSimpan.disabled = true;
      return;
    }

    btnCheck.disabled = true;
    hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-slate-100 text-slate-600 border border-slate-200', 'Memeriksa ID di database SIMRS&hellip;');

    fetch('{{ route("pegawai.update-data.check-simrs") }}?id=' + encodeURIComponent(kode), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (h) {
        if (! h.sukses) {
          hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-red-50 text-red-700 border border-red-200', esc(h.pesan || 'Gagal terhubung ke database SIMRS.'));
          return;
        }
        if (! h.data) {
          hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-amber-50 text-amber-800 border border-amber-200', 'ID tidak ditemukan pada database SIMRS');
          return;
        }
        hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200', esc(h.data.nik) + ' &mdash; <strong>' + esc(h.data.nama) + '</strong>');
        if (btnSimpan) btnSimpan.disabled = false;
      })
      .catch(function () {
        hasilCheck.innerHTML = badge('inline-flex items-center py-1 px-3 rounded-full text-[0.72rem] font-semibold bg-red-50 text-red-700 border border-red-200', 'Terjadi kesalahan jaringan.');
      })
      .finally(function () {
        btnCheck.disabled = false;
      });
  }

  if (btnManual) {
    btnManual.addEventListener('click', function () {
      bukaModal();
      resetCheck();
    });
  }

  if (btnCheck) btnCheck.addEventListener('click', jalankanCheck);
  if (inputKode) {
    inputKode.addEventListener('input', resetCheck);
    inputKode.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        jalankanCheck();
      }
    });
  }

  document.querySelectorAll('.btn-tutup-modal').forEach(function (b) {
    b.addEventListener('click', tutupModal);
  });

  if (modalManual) {
    modalManual.addEventListener('click', function (e) {
      if (e.target === modalManual) tutupModal();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') tutupModal();
  });
})();
</script>
@endsection
