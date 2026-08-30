@foreach($pegawai as $p)
<tr>
  <td><strong>{{ $p->nama_lengkap }}</strong>
    <br><span class="teks-kecil teks-redup">{{ $p->jabatan_nama
          ?? ($p->jabatan_kategori ?? 'Staf/Pelaksana') }}@if(
          ! empty($p->nip)) · NIP {{ $p->nip }}@endif</span>
    @if(! empty($p->posisi) && $p->posisi !== 'Staf')
      <br><span class="badge badge-ungu teks-kecil">{{ $p->posisi }}</span>
    @endif
    @if(($p->status_pegawai ?? '') === 'PNS')
      <span class="badge badge-teal teks-kecil">PNS</span>
    @endif</td>
  <td>{{ $p->email }}</td>

<td>
    {{ $p->unit_nama ?? '—' }}
    @if($p->sub_nama)
        — {{ $p->sub_nama }}
    @endif
    <br><span class="badge badge-hijau">{{ $p->profesi_nama ?? '—' }}</span>
</td>

<td>
    {!! $p->status === 'aktif'
        ? '<span class="badge badge-hijau">Aktif</span>'
        : '<span class="badge badge-abu">Nonaktif</span>' !!}
</td>

<td>
    <div class="aksi-baris">
        <a class="btn btn-garis btn-kecil"
           href="{{ url('admin/pegawai/form/' . (int) $p->id) }}">
            Ubah
        </a>

        @if((int) $p->id !== (int) session('uid'))

            <form method="post" action="{{ url('admin/pegawai/status') }}">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $p->id }}">

                <button type="submit" class="btn btn-garis btn-kecil">
                    {{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
            </form>

            <form method="post"
                  action="{{ url('admin/pegawai/hapus') }}"
                  onsubmit="return confirm('Hapus permanen {{ $p->nama_lengkap }}?\nSeluruh data absensinya ikut terhapus dan tidak dapat dikembalikan.');">
                @csrf
                <input type="hidden" name="id" value="{{ (int) $p->id }}">

                <button type="submit" class="btn btn-bahaya btn-kecil">
                    Hapus
                </button>
            </form>

        @endif
    </div>
</td>
</tr>
@endforeach
@if($pegawai->isEmpty())
<tr><td colspan="5" class="tengah teks-redup">Tidak ada data pegawai.</td></tr>
@endif
