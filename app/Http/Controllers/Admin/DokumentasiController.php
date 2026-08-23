<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DokumentasiController extends Controller
{
    public function index()
    {
        return view('admin.documentation', [
            'judulHalaman' => 'Dokumentasi API',
            'menuAktif' => 'dokumentasi',
        ]);
    }
}
