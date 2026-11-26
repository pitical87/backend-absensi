<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\PengajuanJadwal;
use App\Models\PengajuanLembur;
use App\Models\User;
use App\Services\AlurIzinService;
use App\Services\AtasanLangsungService;
use App\Services\PengajuanLemburService;
use App\Services\UbahJadwalService;

class PersetujuanController extends Controller
{
    use HasPenggunaAktif;

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        $userObj = User::find($u['id']);
        $punyaBawahan = $userObj && app(AtasanLangsungService::class)->punyaBawahan($userObj);
        if (! $punyaBawahan && $u['posisi'] === 'Staf' && $u['role'] !== 'admin') {
            return redirect('dashboard')
                ->with('error', 'Menu ini hanya tersedia bagi atasan langsung.');
        }

        $lib = app(AlurIzinService::class);
        $bawahanIds = $lib->idBawahanLangsung((int) $u['id']);

        $tugasSaya = [];
        if ($bawahanIds !== [] && $u['role'] !== 'admin' && $userObj) {
            $kandidat = Izin::with([
                'user:id,nama_lengkap,jabatan_kategori,jabatan_id,seksi_pembina_id,posisi,unit_kerja_id,sub_unit_id,nip',
                'user.unitKerja:id,nama',
                'user.subUnit:id,nama',
            ])
                ->where('status', 'Menunggu')
                ->where('tahap_aktif', '>', 0)
                ->whereIn('user_id', $bawahanIds)
                ->orderBy('tahap_aktif')
                ->orderBy('id')
                ->get();

            foreach ($kandidat as $r) {
                $pemohon = ['id' => $r->user_id];
                $pengajuanRingkas = ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif];
                if ($lib->bolehBertindak($pengajuanRingkas, $pemohon, $userObj)) {
                    $tugasSaya[] = $r;
                }
            }
        }

        $riwayatSaya = IzinPersetujuan::with(['pengajuan:id,user_id,jenis,jenis_cuti,tanggal_mulai,tanggal_selesai', 'pengajuan.user:id,nama_lengkap'])
            ->where('oleh_user_id', $u['id'])
            ->orderBy('waktu', 'DESC')
            ->limit(30)
            ->get()
            ->all();

        // ── Pengajuan perubahan jadwal shift (sebagai atasan langsung) ──
        $svcJadwal   = app(UbahJadwalService::class);
        $userObjJ    = User::find($u['id']);
        $tugasJadwal = $svcJadwal->tugasAtasan($userObjJ);

        $riwayatJadwalSaya = PengajuanJadwal::with([
                'user:id,nama_lengkap', 'shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang',
            ])
            ->where('diproses_oleh', $u['id'])
            ->where('status', '!=', 'Menunggu')
            ->orderByDesc('diproses_pada')
            ->limit(30)
            ->get()
            ->all();

        // ── Pengajuan lembur (sebagai atasan langsung) ──
        $svcLembur   = app(PengajuanLemburService::class);
        $userObjL    = User::find($u['id']);
        $tugasLembur = $svcLembur->tugasAtasan($userObjL);

        $riwayatLemburSaya = PengajuanLembur::with('user:id,nama_lengkap')
            ->where('diproses_oleh', $u['id'])
            ->where('status', '!=', 'Menunggu')
            ->orderByDesc('diproses_pada')
            ->limit(30)
            ->get()
            ->all();

        return view('pegawai.persetujuan', [
            'u' => $u, 'tugasSaya' => $tugasSaya, 'riwayatSaya' => $riwayatSaya,
            'tugasJadwal' => $tugasJadwal, 'riwayatJadwalSaya' => $riwayatJadwalSaya,
            'tugasLembur' => $tugasLembur, 'riwayatLemburSaya' => $riwayatLemburSaya,
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

        $tahapLabel = IzinPersetujuan::where('pengajuan_id', $iz->id)
            ->where('tahap', (int) $iz->tahap_aktif)
            ->value('posisi_tahap') ?: 'Atasan Langsung';

        $hasil = $lib->proses($izArr, $pemohonArr, (int) $u['id'], $putusan, $catatan);
        catat_aktivitas('Persetujuan ' . $iz->jenis, $iz->user->nama_lengkap . ' — '
            . $tahapLabel . ' oleh ' . $u['nama_lengkap'] . ' → ' . $hasil);

        $pesan = match ($hasil) {
            'Ditolak'   => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui.',
            default     => 'Persetujuan Anda tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };
        return redirect('persetujuan')->with('success', $pesan);
    }

    public function prosesJadwal(\Illuminate\Http\Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return redirect('persetujuan')->with('error', 'Putusan tidak valid.');
        }

        $pj = PengajuanJadwal::with(['user:id,id,nama_lengkap', 'shiftLama:id,kategori', 'shiftBaru:id,kategori'])->find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return redirect('persetujuan')->with('error', 'Pengajuan jadwal tidak ditemukan atau sudah diproses.');
        }
        if ((int) $pj->user_id === (int) $u['id']) {
            return redirect('persetujuan')->with('error', 'Anda tidak dapat memutus pengajuan milik sendiri.');
        }

        $svc = app(UbahJadwalService::class);
        if ($u['role'] !== 'admin' && ! $svc->isAtasan(User::find($u['id']), (int) $pj->user_id)) {
            return redirect('persetujuan')->with('error', 'Anda bukan atasan langsung pemohon ini.');
        }

        $hasil = $svc->putuskan($pj, $putusan, (int) $u['id'], $catatan);

        return redirect('persetujuan')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    public function prosesLembur(\Illuminate\Http\Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $id      = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return redirect('persetujuan')->with('error', 'Putusan tidak valid.');
        }

        $pj = PengajuanLembur::with('user:id,id,nama_lengkap')->find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return redirect('persetujuan')->with('error', 'Pengajuan lembur tidak ditemukan atau sudah diproses.');
        }
        if ((int) $pj->user_id === (int) $u['id']) {
            return redirect('persetujuan')->with('error', 'Anda tidak dapat memutus pengajuan milik sendiri.');
        }

        $svc = app(PengajuanLemburService::class);
        if ($u['role'] !== 'admin' && ! $svc->isAtasan(User::find($u['id']), (int) $pj->user_id)) {
            return redirect('persetujuan')->with('error', 'Anda bukan atasan langsung pemohon ini.');
        }

        $hasil = $svc->putuskan($pj, $putusan, (int) $u['id'], $catatan);

        return redirect('persetujuan')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }
}
