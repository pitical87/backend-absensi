<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifikasiController extends Controller
{
    public function index(?string $kode = null, Request $request)
    {
        $kode = strtoupper(trim((string) ($kode ?: $request->get('kode'))));
        $hasil = null;

        if ($kode !== '') {
            $hasil = DB::table('pengajuan_izin i')
                ->select('i.nomor_surat', 'i.jenis', 'i.jenis_cuti', 'i.tanggal_mulai', 'i.tanggal_selesai',
                         'i.lama_hari', 'i.status', 'i.ttd_digital', 'i.ttd_waktu',
                         'u.nama_lengkap', 'u.nip', 'td.nama_lengkap AS ttd_nama')
                ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
                ->leftJoin('users td', 'td.id', '=', 'i.ttd_oleh')
                ->where('i.kode_verifikasi', $kode)
                ->first();
        }

        return view('publik.verifikasi', ['kode' => $kode, 'hasil' => $hasil]);
    }
}
