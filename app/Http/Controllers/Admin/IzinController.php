<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;
use Illuminate\Http\Request;

class IzinController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) ($request->get('status') ?: 'Menunggu');
        if (! in_array($status, ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'], true)) {
            $status = 'Menunggu';
        }

        $b = Izin::select('pengajuan_izin.*', 'u.nama_lengkap', 'u.posisi AS posisi_pemohon',
                     'uk.nama AS unit_nama', 'su.nama AS sub_nama',
                     'adm.nama_lengkap AS admin_nama')
            ->join('users as u', 'u.id', '=', 'pengajuan_izin.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('users as adm', 'adm.id', '=', 'pengajuan_izin.diproses_oleh');
        if ($status !== 'Semua') {
            $b->where('pengajuan_izin.status', $status);
        }
        $daftar = $b->orderBy('pengajuan_izin.id', 'DESC')->limit(200)->get()->all();

        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($daftar,
            static fn ($r) => in_array($r->jenis, ['Izin', 'Cuti'], true)), 'id');
        if ($idBerjenjang) {
            $persetujuan = IzinPersetujuan::with('user:id,nama_lengkap')
                ->whereIn('pengajuan_id', $idBerjenjang)
                ->orderBy('tahap')
                ->get();
            foreach ($persetujuan as $p) {
                $tahapPer[(int) $p->pengajuan_id][] = $p;
            }
        }

        $jumlah = [];
        foreach (Izin::select('status', \DB::raw('COUNT(*) AS jml'))->groupBy('status')->get() as $r) {
            $jumlah[$r->status] = (int) $r->jml;
        }

        return view('admin.izin', [
            'judulHalaman' => 'Persetujuan Izin & Cuti',
            'menuAktif'    => 'izin',
            'daftar'       => $daftar,
            'tahapPer'     => $tahapPer,
            'status'       => $status,
            'jumlah'       => $jumlah,
        ]);
    }

    public function proses(Request $request)
    {
        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        $iz = Izin::select('pengajuan_izin.*', 'u.nama_lengkap')
            ->join('users as u', 'u.id', '=', 'pengajuan_izin.user_id')
            ->where('pengajuan_izin.id', $id)
            ->first();

        if (! $iz || $iz->status !== 'Menunggu' || in_array($iz->jenis, ['Izin', 'Cuti'], true)) {
            return redirect('admin/izin')
                ->with('error', 'Pengajuan tidak ditemukan, sudah diproses, atau memakai alur berjenjang.');
        }

        $statusBaru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';
        $iz->update([
            'status'        => $statusBaru,
            'diproses_oleh' => session('uid'),
            'catatan_admin' => $catatan,
            'processed_at'  => now(),
        ]);
        catat_aktivitas('Proses Izin', $iz->nama_lengkap . ' — ' . $iz->jenis . ' ('
            . $iz->tanggal_mulai . ' s.d. ' . $iz->tanggal_selesai . ') → ' . $statusBaru);

        return redirect('admin/izin')->with('success',
            'Pengajuan ' . $iz->jenis . ' atas nama ' . $iz->nama_lengkap . ' telah ' . strtolower($statusBaru) . '.');
    }

    public function ambilAlih(Request $request)
    {
        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: 'Diambil alih oleh admin.';

        $iz = Izin::find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return redirect('admin/izin')
                ->with('error', 'Pengajuan tidak ditemukan atau sudah selesai diproses.');
        }
        $pemohon = User::find($iz->user_id);

        $hasil = app(AlurIzinService::class)->proses((array) $iz, (array) $pemohon, (int) session('uid'), $putusan, $catatan);
        catat_aktivitas('Ambil Alih Persetujuan', $pemohon->nama_lengkap . ' — ' . $iz->jenis
            . ' tahap ' . label_tahap_izin((int) $iz->tahap_aktif) . ' → ' . $hasil);

        return redirect('admin/izin')->with('success',
            'Tahap ' . label_tahap_izin((int) $iz->tahap_aktif) . ' untuk ' . $pemohon->nama_lengkap
            . ' telah diproses admin (' . $hasil . ').');
    }
}
