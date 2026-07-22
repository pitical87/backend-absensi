<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersetujuanController extends Controller
{
    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        if ($u['posisi'] === 'Staf') {
            return redirect('dashboard')
                ->with('flash_gagal', 'Menu ini hanya tersedia bagi pejabat dalam alur persetujuan.');
        }

        $lib = app(AlurIzinService::class);

        $kandidat = Izin::with([
            'user:id,nama_lengkap,jabatan_kategori,jabatan_id,seksi_pembina_id,posisi,unit_kerja_id,sub_unit_id,nip',
            'user.unitKerja:id,nama',
            'user.subUnit:id,nama',
        ])
            ->where('status', 'Menunggu')
            ->where('tahap_aktif', '>', 0)
            ->orderBy('tahap_aktif')
            ->orderBy('id')
            ->get();

        $tugasSaya = [];
        foreach ($kandidat as $r) {
            $pemohon = [
                'id' => $r->user_id, 'posisi' => $r->user->posisi,
                'jabatan_id' => $r->user->jabatan_id, 'seksi_pembina_id' => $r->user->seksi_pembina_id,
                'unit_kerja_id' => $r->user->unit_kerja_id, 'sub_unit_id' => $r->user->sub_unit_id,
            ];
            $pengajuanRingkas = ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif];
            $userObj =User::find($u['id']);
            if ($lib->bolehBertindak($pengajuanRingkas, $pemohon, $userObj) && $u['role'] !== 'admin') {
                $tugasSaya[] = $r;
            }
        }

        $riwayatSaya = IzinPersetujuan::with(['pengajuan:user,id,jenis,jenis_cuti,tanggal_mulai,tanggal_selesai', 'pengajuan.user:id,nama_lengkap'])
            ->where('oleh_user_id', $u['id'])
            ->orderBy('waktu', 'DESC')
            ->limit(30)
            ->get()
            ->all();

        return view('pegawai.persetujuan', [
            'u' => $u, 'tugasSaya' => $tugasSaya, 'riwayatSaya' => $riwayatSaya,
        ]);
    }

    public function proses(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        $iz = Izin::with('user:id,id,nama_lengkap')->find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return redirect('persetujuan')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $lib = app(AlurIzinService::class);
        $userObj = (object) $u;
        $pemohonArr = (array) $iz->user;
        $izArr = (array) $iz;
        if (! $lib->bolehBertindak($izArr, $pemohonArr, $userObj)) {
            return redirect('persetujuan')
                ->with('flash_gagal', 'Anda tidak berwenang memutus pengajuan ini pada tahap saat ini.');
        }

        $hasil = $lib->proses($izArr, $pemohonArr, (int) $u['id'], $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz->jenis, $iz->user->nama_lengkap . ' — tahap '
            . label_tahap_izin((int) $iz->tahap_aktif) . ' oleh ' . $u['nama_lengkap'] . ' → ' . $hasil);

        $pesan = match ($hasil) {
            'Ditolak'   => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui penuh — dokumen resmi kini tersedia untuk pemohon.',
            default     => 'Persetujuan Anda tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };
        return redirect('persetujuan')->with('flash_sukses', $pesan);
    }

    private function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }
        $u = DB::table('users as u')
            ->select('u.*', 'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang',
                     'j.nama AS jabatan_nama',
                     DB::raw('COALESCE(j.unit_label, ji.unit_label) AS jabatan_unit'),
                     'sp.nama AS seksi_pembina_nama', 'spi.unit_label AS bidang_pembina_label')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'u.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id')
            ->leftJoin('jabatan as ji', 'ji.id', '=', 'j.induk_id')
            ->leftJoin('jabatan as sp', 'sp.id', '=', 'u.seksi_pembina_id')
            ->leftJoin('jabatan as spi', 'spi.id', '=', 'sp.induk_id')
            ->where('u.id', $uid)
            ->first();      

        if (! $u || $u->status !== 'aktif') {
            session()->flush();
            return $cache = null;
        }
        return $cache = (array) $u;
    }
}
