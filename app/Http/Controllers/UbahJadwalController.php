<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\PengajuanJadwal;
use App\Models\Shift;
use App\Services\UbahJadwalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UbahJadwalController extends Controller
{
    use HasPenggunaAktif;

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $svc = app(UbahJadwalService::class);

        $shiftList = Shift::where('aktif', 1)->orderBy('jam_masuk')->get();

        $jadwal = array_map(function ($j) use ($svc, $u, $shiftList) {
            $cek = $svc->kelayakan($j, (int) $u['id']);

            return [
                'jadwal'   => $j,
                'bisa'     => $cek['ok'],
                'alasanBlok' => $cek['pesan'],
                'pengajuan' => PengajuanJadwal::where('user_id', $u['id'])
                    ->where('tanggal', \Carbon\Carbon::parse($j->tanggal_berlaku)->format('Y-m-d'))
                    ->whereIn('status', ['Menunggu', 'Disetujui'])
                    ->first(),
            ];
        }, $svc->jadwalMendatang((int) $u['id']));

        $riwayat = PengajuanJadwal::with(['shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang', 'diprosesOlehUser:id,nama_lengkap'])
            ->where('user_id', $u['id'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->all();

        return view('pegawai.ubah_jadwal', [
            'u'          => $u,
            'judul'      => 'Ubah Jadwal Shift',
            'jadwalList' => $jadwal,
            'shiftList'  => $shiftList,
            'riwayat'    => $riwayat,
            'batasJam'   => $svc->batasJam(),
        ]);
    }

    public function ajukan(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $tanggal     = trim((string) $request->input('tanggal'));
        $shiftBaruId = (int) $request->input('shift_baru_id');
        $alasan      = trim((string) $request->input('alasan'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return back()->with('error', 'Tanggal tidak valid.')->withInput();
        }
        if ($tanggal < now()->toDateString() || $tanggal > now()->copy()->addDays(30)->toDateString()) {
            return back()->with('error', 'Tanggal harus dalam rentang hari ini s.d. 30 hari ke depan.')->withInput();
        }
        if ($shiftBaruId < 1) {
            return back()->with('error', 'Pilih shift tujuan terlebih dahulu.')->withInput();
        }
        if ($alasan === '') {
            return back()->with('error', 'Alasan pengajuan wajib diisi.')->withInput();
        }
        if (mb_strlen($alasan) > 500) {
            return back()->with('error', 'Alasan maksimal 500 karakter.')->withInput();
        }

        $pemohon = \App\Models\User::find($u['id']);
        $hasil = app(UbahJadwalService::class)->simpan($pemohon, $tanggal, $shiftBaruId, $alasan);

        return redirect('ubah-jadwal')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    public function batal(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $pj = PengajuanJadwal::where('id', $id)
            ->where('user_id', $u['id'])
            ->where('status', 'Menunggu')
            ->first();

        if (! $pj) {
            return redirect('ubah-jadwal')
                ->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        DB::transaction(function () use ($pj, $u) {
            $pj->update([
                'status'            => 'Ditolak',
                'catatan_keputusan' => 'Dibatalkan oleh pemohon.',
                'diproses_pada'     => now(),
            ]);
            catat_aktivitas('Batalkan Ubah Jadwal', $u['nama_lengkap'] . ' menarik pengajuan ubah jadwal #' . $pj->id);
        });

        return redirect('ubah-jadwal')->with('success', 'Pengajuan berhasil dibatalkan.');
    }
}
