<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AktivitasLog;
use Illuminate\Http\Request;

class AktivitasController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per     = 50;

        $b = AktivitasLog::select('aktivitas_log.*', 'u.nama_lengkap')
            ->leftJoin('users as u', 'u.id', '=', 'aktivitas_log.user_id');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('aktivitas_log.aksi', 'like', "%{$q}%")
                     ->orWhere('aktivitas_log.detail', 'like', "%{$q}%")
                     ->orWhere('u.nama_lengkap', 'like', "%{$q}%");
            });
        }
        $total  = $b->count();
        $daftar = $b->orderBy('aktivitas_log.id', 'DESC')
                    ->skip(($halaman - 1) * $per)->take($per)->get()->all();

        return view('admin.aktivitas.index', [
            'judulHalaman' => 'Log Aktivitas',
            'menuAktif'    => 'aktivitas',
            'daftar'       => $daftar,
            'q'            => $q,
            'halaman'      => $halaman,
            'totalHal'     => max(1, (int) ceil($total / $per)),
            'total'        => $total,
        ]);
    }
}
