<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AktivitasController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per     = 50;

        $b = DB::table('aktivitas_log as l')
            ->select('l.*', 'u.nama_lengkap')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('l.aksi', 'like', "%{$q}%")
                     ->orWhere('l.detail', 'like', "%{$q}%")
                     ->orWhere('u.nama_lengkap', 'like', "%{$q}%");
            });
        }
        $total  = $b->count();
        $daftar = $b->orderBy('l.id', 'DESC')
                    ->skip(($halaman - 1) * $per)->take($per)->get()->all();

        return view('admin.aktivitas', [
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
