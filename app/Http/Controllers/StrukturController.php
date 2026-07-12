<?php

namespace App\Http\Controllers;

use App\Services\StrukturService;

class StrukturController extends Controller
{
    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        return view('pegawai.struktur', [
            'pohon' => app(StrukturService::class)->pohon(),
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
