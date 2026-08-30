<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DokumentasiController extends Controller
{
    public function index()
    {
        return view('admin.documentation.index', [
            'judulHalaman' => 'Dokumentasi API',
            'menuAktif' => 'dokumentasi',
        ]);
    }
}
