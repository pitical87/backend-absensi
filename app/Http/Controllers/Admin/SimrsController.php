<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SimrsService;

class SimrsController extends Controller
{
    public function koneksi(SimrsService $simrs)
    {
        return view('admin.simrs_koneksi', [
            'judulHalaman' => 'Cek Koneksi SIMRS',
            'menuAktif' => 'cek_simrs',
            'hasil' => $simrs->cekKoneksi(),
            'timeout' => (int) config('simrs.timeout', 5),
        ]);
    }
}
