<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FotoController extends Controller
{
    public function tampil(int $absensiId, string $tipe)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response('Tidak berwenang.', 401);
        }
        if (! in_array($tipe, ['datang', 'pulang'], true)) {
            return response('Tidak ditemukan.', 404);
        }

        $rec = DB::table('absensi')->where('id', $absensiId)->first();
        if (! $rec || ($u['role'] !== 'admin' && (int) $rec->user_id !== (int) $u['id'])) {
            return response('Tidak ditemukan.', 404);
        }

        $relatif = $tipe === 'datang' ? $rec->foto_masuk : $rec->foto_pulang;
        return $this->kirimBerkas($relatif);
    }

    public function lampiranIzin(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response('Tidak berwenang.', 401);
        }

        $iz = DB::table('pengajuan_izin')->where('id', $id)->first();
        if (! $iz || ($u['role'] !== 'admin' && (int) $iz->user_id !== (int) $u['id'])) {
            return response('Tidak ditemukan.', 404);
        }
        return $this->kirimBerkas($iz->lampiran);
    }

    private function kirimBerkas(?string $relatif)
    {
        if (! $relatif || str_contains($relatif, '..') || ! preg_match('#^[a-z]+/\d{6}/[\w.\-]+$#', $relatif)) {
            return response('Berkas tidak ditemukan.', 404);
        }

        $path = storage_path('app/public/' . $relatif);
        if (! is_file($path)) {
            return response('Berkas tidak ditemukan.', 404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };

        return response(file_get_contents($path), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }
        $u = DB::table('users as u')
            ->select('u.*', 'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang',
                     'j.nama AS jabatan_nama',
                     DB::raw('COALESCE(j.unit_label, ji.unit_label) AS jabatan_unit'),
                     'sp.nama AS seksi_pembina_nama', 'spi.unit_label AS bidang_pembina_label')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'u.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id')
            ->leftJoin('jabatan ji', 'ji.id', '=', 'j.induk_id')
            ->leftJoin('jabatan sp', 'sp.id', '=', 'u.seksi_pembina_id')
            ->leftJoin('jabatan spi', 'spi.id', '=', 'sp.induk_id')
            ->where('u.id', $uid)
            ->first();

        if (! $u || $u->status !== 'aktif') {
            session()->flush();
            return $cache = null;
        }
        return $cache = (array) $u;
    }
}
