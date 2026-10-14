@extends('layouts.admin')

@section('content')
<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('atur') !!} Tambah Jabatan Baru</h2>
  </div>

  <form method="post" action="{{ route('admin.jabatan.store') }}" class="grid gap-4" style="max-width: 600px;">
    @csrf

    <div class="form-grup">
      <label class="wajib">Nama Jabatan</label>
      <input type="text" name="nama" required maxlength="100" placeholder="Contoh: Kepala Bidang Pelayanan Medik"
             class="{{ $errors->has('nama') ? 'input-error' : '' }}">
      @error('nama')
        <div class="error-teks">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-grup">
      <label class="wajib">Kategori</label>
      <select name="kategori" required
              class="{{ $errors->has('kategori') ? 'input-error' : '' }}">
        <option value="">Pilih Kategori</option>
        @foreach($kategoriList as $k)
          <option value="{{ $k }}">{{ $k }}</option>
        @endforeach
      </select>
      @error('kategori')
        <div class="error-teks">{{ $message }}</div>
      @enderror
    </div>

    <div class="form-grup">
      <label>Atasan Langsung (Induk Jabatan)</label>
      <select name="induk_id"
              class="{{ $errors->has('induk_id') ? 'input-error' : '' }}">
        <option value="">— Tidak ada (jabatan tertinggi) —</option>
        @foreach($jabatans as $j)
          <option value="{{ (int) $j->id }}">{{ $j->kategori }} — {{ $j->nama }}</option>
        @endforeach
      </select>
      @error('induk_id')
        <div class="error-teks">{{ $message }}</div>
      @enderror
      <p class="petunjuk">Kosongkan jika jabatan ini tidak memiliki atasan (mis. Kepala RS).</p>
    </div>

    <div class="form-grup">
      <label>Label Unit (Opsional)</label>
      <input type="text" name="unit_label" maxlength="100" placeholder="Contoh: Bidang Pelayanan Medik"
             class="{{ $errors->has('unit_label') ? 'input-error' : '' }}">
      @error('unit_label')
        <div class="error-teks">{{ $message }}</div>
      @enderror
      <p class="petunjuk">Hanya diisi untuk kategori Kepala Bidang / Kepala Bagian.</p>
    </div>

    <div class="form-grup">
      <label>Urutan Tampil</label>
      <input type="number" name="urutan" min="0" value="99"
             class="{{ $errors->has('urutan') ? 'input-error' : '' }}">
      @error('urutan')
        <div class="error-teks">{{ $message }}</div>
      @enderror
      <p class="petunjuk">Jabatan dengan urutan kecil tampil lebih atas.</p>
    </div>

    <div class="aksi-baris justify-end">
      <a href="{{ route('admin.jabatan.index') }}" class="btn btn-garis">Batal</a>
      <button type="submit" class="btn btn-primer">{!! ikon('simpan', 15) !!} Simpan Jabatan</button>
    </div>
  </form>
</section>
@endsection