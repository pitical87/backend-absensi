<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\User;
use App\Services\ParafQrService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VerifikasiController extends Controller
{
    public function index(Request $request, ?string $kode = null)
    {
        $kode = strtoupper(trim((string) ($kode ?: $request->get('kode'))));
        $hasil = null;

        if ($kode !== '') {
            $hasil = Izin::select('nomor_surat', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai',
                         'lama_hari', 'status', 'ttd_digital', 'ttd_waktu', 'user_id', 'ttd_oleh')
                ->with('user:id,nama_lengkap,nip')
                ->with('ttdOleh:id,nama_lengkap')
                ->where('kode_verifikasi', $kode)
                ->first();
        }

        return view('publik.verifikasi', ['kode' => $kode, 'hasil' => $hasil]);
    }

    public function pegawai(int $id, string $sig, ParafQrService $parafQr): Response
    {
        $valid = $parafQr->validasi($id, $sig);
        $pegawai = $valid
            ? User::with(['unitKerja', 'subUnit', 'jabatan', 'profesi'])->find($id)
            : null;

        if (! $pegawai) {
            return response()->view('publik.pegawai', [
                'valid' => false,
                'pegawai' => null,
                'jabatan' => null,
                'unitKerja' => null,
                'instansi' => (string) pengaturan('nama_instansi', 'RSUD Merauke'),
            ], 404);
        }

        $jabatan = (string) ($pegawai->jabatan?->nama
            ?: ($pegawai->jabatan_kategori ?: ($pegawai->posisi ?: '—')));

        $unitNama = $pegawai->unitKerja?->nama;
        $subUnitNama = $pegawai->subUnit?->nama;
        $unitKerja = $unitNama
            ? ($subUnitNama ? "{$unitNama} — {$subUnitNama}" : $unitNama)
            : (string) pengaturan('nama_instansi', 'RSUD Merauke');

        return response()->view('publik.pegawai', [
            'valid' => true,
            'pegawai' => $pegawai,
            'jabatan' => $jabatan,
            'unitKerja' => $unitKerja,
            'instansi' => (string) pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }
}
