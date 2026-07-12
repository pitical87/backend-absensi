<?php

namespace App\Http\Controllers;

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

        $kandidat = DB::table('pengajuan_izin i')
            ->select('i.*', 'p.nama_lengkap', 'p.jabatan_kategori', 'p.jabatan_id', 'p.seksi_pembina_id',
                     'p.posisi AS posisi_pemohon', 'p.unit_kerja_id', 'p.sub_unit_id', 'p.nip',
                     'uk.nama AS unit_nama', 'su.nama AS sub_nama')
            ->join('users p', 'p.id', '=', 'i.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'p.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'p.sub_unit_id')
            ->where('i.status', 'Menunggu')->where('i.tahap_aktif', '>', 0)
            ->orderBy('i.tahap_aktif')->orderBy('i.id')
            ->get()->all();

        $tugasSaya = [];
        foreach ($kandidat as $r) {
            $pemohon = [
                'id' => $r->user_id, 'posisi' => $r->posisi_pemohon,
                'jabatan_id' => $r->jabatan_id, 'seksi_pembina_id' => $r->seksi_pembina_id,
                'unit_kerja_id' => $r->unit_kerja_id, 'sub_unit_id' => $r->sub_unit_id,
            ];
            $pengajuanRingkas = ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif];
            $userObj = (object) $u;
            if ($lib->bolehBertindak($pengajuanRingkas, $pemohon, $userObj) && $u['role'] !== 'admin') {
                $tugasSaya[] = $r;
            }
        }

        $riwayatSaya = DB::table('izin_persetujuan p')
            ->select('p.*', 'i.jenis', 'i.jenis_cuti', 'i.tanggal_mulai', 'i.tanggal_selesai', 'u.nama_lengkap')
            ->join('pengajuan_izin i', 'i.id', '=', 'p.pengajuan_id')
            ->join('users as u', 'u.id', '=', 'i.user_id')
            ->where('p.oleh_user_id', $u['id'])
            ->orderBy('p.waktu', 'DESC')->limit(30)->get()->all();

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

        $iz = DB::table('pengajuan_izin')->where('id', $id)->first();
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return redirect('persetujuan')
                ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $pemohon = DB::table('users')->where('id', $iz->user_id)->first();
        $lib = app(AlurIzinService::class);
        $userObj = (object) $u;
        $pemohonArr = (array) $pemohon;
        $izArr = (array) $iz;
        if (! $lib->bolehBertindak($izArr, $pemohonArr, $userObj)) {
            return redirect('persetujuan')
                ->with('flash_gagal', 'Anda tidak berwenang memutus pengajuan ini pada tahap saat ini.');
        }

        $hasil = $lib->proses($izArr, $pemohonArr, (int) $u['id'], $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz->jenis, $pemohon->nama_lengkap . ' — tahap '
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
            ->leftJoin('jabatan ji', 'ji.id', '=', 'j.induk_id')
            ->leftJoin('jabatan sp', 'sp.id', '=', 'u.seksi_pembina_id')
            ->leftJoin('jabatan spi', 'spi.id', '=', 'sp.induk_id')
            ->where('u.id', $uid)
            ->first();

        if (! $u || $u->status !== 'aktif') {
            session()->flush();
            return $cache = null;
        }
        return $cache = (array) $u;
    }
}
