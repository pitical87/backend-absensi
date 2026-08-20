<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Services\StrukturService;

class StrukturController extends Controller
{
    use HasPenggunaAktif;

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
}
