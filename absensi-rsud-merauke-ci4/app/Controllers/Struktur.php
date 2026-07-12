<?php

namespace App\Controllers;

use App\Libraries\Struktur as StrukturLib;

/** Bagan organisasi — dapat dilihat seluruh pegawai. */
class Struktur extends BaseController
{
    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }

        return view('pegawai/struktur', [
            'pohon' => (new StrukturLib())->pohon(),
        ]);
    }
}
