<x-mail::message>
# Pengajuan {{ $izin->jenis }}

@if($tipe === 'baru')
**{{ $pemohon->nama_lengkap }}** mengajukan {{ $izin->jenis }}.
@elseif($tipe === 'disetujui')
Pengajuan {{ $izin->jenis }} Anda telah **disetujui**.
@elseif($tipe === 'ditolak')
Pengajuan {{ $izin->jenis }} Anda telah **ditolak**.
@endif

**Periode:** {{ $izin->tanggal_mulai->format('d M Y') }} s.d. {{ $izin->tanggal_selesai->format('d M Y') }}

@if($izin->keterangan)
**Keterangan:** {{ $izin->keterangan }}
@endif

@if($izin->catatan_admin)
**Catatan Admin:** {{ $izin->catatan_admin }}
@endif

@if($tipe === 'baru')
<x-mail::button :url="route('persetujuan')">
Lihat Pengajuan
</x-mail::button>
@elseif($tipe === 'disetujui')
<x-mail::button :url="route('izin.dokumen', $izin->id)">
Lihat Dokumen
</x-mail::button>
@endif

Hormat kami,<br>
{{ config('app.name') }}
</x-mail::message>
