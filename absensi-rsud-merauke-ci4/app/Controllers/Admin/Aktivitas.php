<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/** Log aktivitas penting (audit trail). */
class Aktivitas extends BaseController
{
    public function index()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $halaman = max(1, (int) $this->request->getGet('hal'));
        $per     = 50;

        $b = $this->db->table('aktivitas_log l')
            ->select('l.*, u.nama_lengkap')
            ->join('users as u', 'u.id = l.user_id', 'left');
        if ($q !== '') {
            $b->groupStart()
              ->like('l.aksi', $q)->orLike('l.detail', $q)->orLike('u.nama_lengkap', $q)
              ->groupEnd();
        }
        $total  = (clone $b)->countAllResults(false);
        $daftar = $b->orderBy('l.id', 'DESC')
                    ->get($per, ($halaman - 1) * $per)->getResultArray();

        return view('admin/aktivitas', [
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
