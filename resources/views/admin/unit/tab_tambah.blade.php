<section class="kartu">
  <div class="kartu-kepala">
    <h2>{!! ikon('gedung') !!} Tambah Unit Kerja</h2>
  </div>
  <form method="post" action="{{ url('admin/unit/aksi') }}" class="bilah-alat" data-ajax="tambah_unit">
    @csrf
    <input type="hidden" name="aksi" value="tambah_unit">
    <input type="text" name="nama" placeholder="Nama unit kerja baru…" required>
    <label class="teks-kecil flex items-center gap-1.5 whitespace-nowrap">
      <input type="checkbox" name="punya_sub" value="1" class="w-auto"> Memiliki sub unit
    </label>
    <button type="submit" class="btn btn-primer btn-kecil">+ Tambah Unit</button>
  </form>
</section>