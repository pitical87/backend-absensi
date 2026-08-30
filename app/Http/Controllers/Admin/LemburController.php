<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanLembur;
use App\Services\PengajuanLemburService;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'Semua');
        if (! in_array($status, ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'], true)) {
            $status = 'Semua';
        }

        $q = PengajuanLembur::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama', 'user.subUnit:id,nama',
            'diprosesOlehUser:id,nama_lengkap',
        ])->orderByRaw("CASE status WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 ELSE 2 END")->orderByDesc('id');

        if ($status !== 'Semua') {
            $q->where('status', $status);
        }

        $daftar   = $q->limit(200)->get()->all();
        $menunggu = PengajuanLembur::where('status', 'Menunggu')->count();

        return view('admin.lembur.index', [
            'judulHalaman' => 'Pengajuan Lembur',
            'menuAktif'    => 'lembur',
            'daftar'       => $daftar,
            'status'       => $status,
            'menunggu'     => $menunggu,
        ]);
    }

    public function proses(Request $request)
    {
        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return redirect('admin/lembur')->with('error', 'Putusan tidak valid.');
        }

        $pj = PengajuanLembur::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return redirect('admin/lembur')->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $hasil = app(PengajuanLemburService::class)->putuskan($pj, $putusan, (int) session('uid'), $catatan);

        return redirect('admin/lembur')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }
}
