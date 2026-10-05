@foreach($shiftList as $s)
<tr data-id="{{ (int) $s->id }}"
    data-kategori="{{ $s->kategori }}"
    data-masuk="{{ $s->jam_masuk->format('H:i') }}"
    data-pulang="{{ $s->jam_pulang->format('H:i') }}"
    data-lintas="{{ $s->lintas_hari ? 1 : 0 }}"
    data-aktif="{{ $s->aktif ? 1 : 0 }}">
  <td><strong>{{ $s->kategori }}</strong></td>
  <td class="angka">{{ $s->jam_masuk->format('H:i') }}</td>
  <td class="angka">{{ $s->jam_pulang->format('H:i') }}</td>
  <td>{{ $s->lintas_hari ? 'Ya (pulang keesokan hari)' : '—' }}</td>
  <td>{!! $s->aktif
        ? '<span class="badge badge-hijau">Aktif</span>'
        : '<span class="badge badge-abu">Nonaktif</span>' !!}</td>
  <td>
    <div class="aksi-baris">
      <button type="button"
              class="btn btn-garis btn-kecil tombol-ubah"
              data-id="{{ (int) $s->id }}"
              data-kategori="{{ $s->kategori }}"
              data-masuk="{{ $s->jam_masuk->format('H:i') }}"
              data-pulang="{{ $s->jam_pulang->format('H:i') }}"
              data-lintas="{{ $s->lintas_hari ? 1 : 0 }}"
              data-aktif="{{ $s->aktif ? 1 : 0 }}">
        Ubah
      </button>
      <button type="button"
              class="btn btn-garis btn-kecil tombol-toggle"
              data-id="{{ (int) $s->id }}">
        {{ $s->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
      </button>
      <button type="button"
              class="btn btn-bahaya btn-kecil tombol-hapus"
              data-id="{{ (int) $s->id }}">
        Hapus
      </button>
    </div>
  </td>
</tr>
@endforeach