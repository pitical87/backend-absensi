<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanJadwal;
use App\Services\UbahJadwalService;
use Illuminate\Http\Request;

class PengajuanJadwalController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'Semua');
        if (! in_array($status, ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'], true)) {
            $status = 'Semua';
        }

        $q = PengajuanJadwal::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama', 'user.subUnit:id,nama',
            'shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang',
            'diprosesOlehUser:id,nama_lengkap',
        ])->orderByRaw("CASE status WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 ELSE 2 END")->orderByDesc('id');

        if ($status !== 'Semua') {
            $q->where('status', $status);
        }

        $daftar   = $q->limit(200)->get()->all();
        $menunggu = PengajuanJadwal::where('status', 'Menunggu')->count();

        return view('admin.jadwal_pengajuan', [
            'judulHalaman' => 'Pengajuan Perubahan Jadwal',
            'menuAktif'    => 'jadwal_pengajuan',
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
            return redirect('admin/jadwal_pengajuan')->with('error', 'Putusan tidak valid.');
        }

        $pj = PengajuanJadwal::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return redirect('admin/jadwal_pengajuan')->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $hasil = app(UbahJadwalService::class)->putuskan($pj, $putusan, (int) session('uid'), $catatan);

        return redirect('admin/jadwal_pengajuan')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }
}
