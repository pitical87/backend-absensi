<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;

class PersetujuanController extends Controller
{
    use HasPenggunaAktif;

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        if ($u['posisi'] === 'Staf') {
            return redirect('dashboard')
                ->with('error', 'Menu ini hanya tersedia bagi pejabat dalam alur persetujuan.');
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
        $userObj = User::find($u['id']);
        foreach ($kandidat as $r) {
            $pemohon = [
                'id' => $r->user_id, 'posisi' => $r->user->posisi,
                'jabatan_id' => $r->user->jabatan_id, 'seksi_pembina_id' => $r->user->seksi_pembina_id,
                'unit_kerja_id' => $r->user->unit_kerja_id, 'sub_unit_id' => $r->user->sub_unit_id,
            ];
            $pengajuanRingkas = ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif];
            if ($lib->bolehBertindak($pengajuanRingkas, $pemohon, $userObj) && $u['role'] !== 'admin') {
                $tugasSaya[] = $r;
            }
        }

        $riwayatSaya = IzinPersetujuan::with(['pengajuan:id,user_id,jenis,jenis_cuti,tanggal_mulai,tanggal_selesai', 'pengajuan.user:id,nama_lengkap'])
            ->where('oleh_user_id', $u['id'])
            ->orderBy('waktu', 'DESC')
            ->limit(30)
            ->get()
            ->all();

        return view('pegawai.persetujuan', [
            'u' => $u, 'tugasSaya' => $tugasSaya, 'riwayatSaya' => $riwayatSaya,
        ]);
    }

    public function proses(\Illuminate\Http\Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        $iz = Izin::with('user:id,id,nama_lengkap,unit_kerja_id,sub_unit_id,jabatan_id,seksi_pembina_id,posisi')->find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return redirect('persetujuan')
                ->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        $lib = app(AlurIzinService::class);
        $pemohonArr = $iz->user->toArray();
        $izArr = $iz->toArray();
        $userObj = User::find($u['id']);
        if (! $lib->bolehBertindak($izArr, $pemohonArr, $userObj)) {
            return redirect('persetujuan')
                ->with('error', 'Anda tidak berwenang memutus pengajuan ini pada tahap saat ini.');
        }

        $hasil = $lib->proses($izArr, $pemohonArr, (int) $u['id'], $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz->jenis, $iz->user->nama_lengkap . ' — tahap '
            . label_tahap_izin((int) $iz->tahap_aktif) . ' oleh ' . $u['nama_lengkap'] . ' → ' . $hasil);

        $pesan = match ($hasil) {
            'Ditolak'   => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui penuh — dokumen resmi kini tersedia untuk pemohon.',
            default     => 'Persetujuan Anda tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };
        return redirect('persetujuan')->with('success', $pesan);
    }
}
