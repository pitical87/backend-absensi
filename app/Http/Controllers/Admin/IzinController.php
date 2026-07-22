<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlurIzinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IzinController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) ($request->get('status') ?: 'Menunggu');
        if (! in_array($status, ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'], true)) {
            $status = 'Menunggu';
        }

        $b = DB::table('pengajuan_izin as i')
            ->select('i.*', 'u.nama_lengkap', 'u.posisi AS posisi_pemohon',
                     'uk.nama AS unit_nama', 'su.nama AS sub_nama',
                     'adm.nama_lengkap AS admin_nama')
            ->join('users as u', 'u.id', '=', 'i.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('users as adm', 'adm.id', '=', 'i.diproses_oleh');
        if ($status !== 'Semua') {
            $b->where('i.status', $status);
        }
        $daftar = $b->orderBy('i.id', 'DESC')->limit(200)->get()->all();

        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($daftar,
            static fn ($r) => in_array($r->jenis, ['Izin', 'Cuti'], true)), 'id');
        if ($idBerjenjang) {
            foreach (DB::table('izin_persetujuan as p')
                         ->select('p.*', 'o.nama_lengkap AS oleh_nama')
                         ->leftJoin('users as o', 'o.id', '=', 'p.oleh_user_id')
                         ->whereIn('pengajuan_id', $idBerjenjang)
                         ->orderBy('tahap')->get() as $p) {
                $tahapPer[(int) $p->pengajuan_id][] = $p;
            }
        }

        $jumlah = [];
        foreach (DB::table('pengajuan_izin')
                     ->select(DB::raw('status, COUNT(*) AS jml'))->groupBy('status')
                     ->get() as $r) {
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

        $iz = DB::table('pengajuan_izin as i')
            ->select('i.*', 'u.nama_lengkap')->join('users as u', 'u.id', '=', 'i.user_id')
            ->where('i.id', $id)->first();

        if (! $iz || $iz->status !== 'Menunggu' || in_array($iz->jenis, ['Izin', 'Cuti'], true)) {
            return redirect('admin/izin')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan, sudah diproses, atau memakai alur berjenjang.');
        }

        $statusBaru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';
        DB::table('pengajuan_izin')->where('id', $id)->update([
            'status'        => $statusBaru,
            'diproses_oleh' => session('uid'),
            'catatan_admin' => $catatan,
            'processed_at'  => now(),
        ]);
        catat_aktivitas('Proses Izin', $iz->nama_lengkap . ' — ' . $iz->jenis . ' ('
            . $iz->tanggal_mulai . ' s.d. ' . $iz->tanggal_selesai . ') → ' . $statusBaru);

        return redirect('admin/izin')->with('flash_sukses',
            'Pengajuan ' . $iz->jenis . ' atas nama ' . $iz->nama_lengkap . ' telah ' . strtolower($statusBaru) . '.');
    }

    public function ambilAlih(Request $request)
    {
        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: 'Diambil alih oleh admin.';

        $iz = DB::table('pengajuan_izin')->where('id', $id)->first();
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return redirect('admin/izin')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah selesai diproses.');
        }
        $pemohon = DB::table('users')->where('id', $iz->user_id)->first();

        $hasil = app(AlurIzinService::class)->proses((array) $iz, (array) $pemohon, (int) session('uid'), $putusan, $catatan);
        catat_aktivitas('Ambil Alih Persetujuan', $pemohon->nama_lengkap . ' — ' . $iz->jenis
            . ' tahap ' . label_tahap_izin((int) $iz->tahap_aktif) . ' → ' . $hasil);

        return redirect('admin/izin')->with('flash_sukses',
            'Tahap ' . label_tahap_izin((int) $iz->tahap_aktif) . ' untuk ' . $pemohon->nama_lengkap
            . ' telah diproses admin (' . $hasil . ').');
    }
}
