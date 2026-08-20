<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use Illuminate\Http\Request;

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
}
