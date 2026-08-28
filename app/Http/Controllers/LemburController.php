<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\PengajuanLembur;
use App\Services\AbsenLemburService;
use App\Services\PengajuanLemburService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LemburController extends Controller
{
    use HasPenggunaAktif;

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $svc = app(PengajuanLemburService::class);

        $riwayat = PengajuanLembur::with(['diprosesOlehUser:id,nama_lengkap', 'absenLembur'])
            ->where('user_id', $u['id'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->all();

        $disetujui = PengajuanLembur::with('absenLembur')
            ->where('user_id', $u['id'])
            ->whereIn('status', ['Disetujui'])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get()
            ->all();

        return view('pegawai.lembur', [
            'u'          => $u,
            'judul'      => 'Lembur',
            'riwayat'    => $riwayat,
            'disetujui'  => $disetujui,
            'batasJam'   => $svc->batasJam(),
            'maksJam'    => $svc->maksLemburPerHariJam(),
            'hariKeDepan'=> PengajuanLemburService::HARI_KE_DEPAN,
        ]);
    }

    public function ajukan(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $tanggal    = trim((string) $request->input('tanggal'));
        $jamMulai   = trim((string) $request->input('jam_mulai'));
        $jamSelesai = trim((string) $request->input('jam_selesai'));
        $keterangan = trim((string) $request->input('keterangan'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return back()->with('error', 'Tanggal tidak valid.')->withInput();
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamMulai)
            || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamSelesai)) {
            return back()->with('error', 'Jam mulai dan selesai tidak valid.')->withInput();
        }
        if ($keterangan === '') {
            return back()->with('error', 'Keterangan/keperluan lembur wajib diisi.')->withInput();
        }
        if (mb_strlen($keterangan) > 1000) {
            return back()->with('error', 'Keterangan maksimal 1000 karakter.')->withInput();
        }

        $pemohon = \App\Models\User::find($u['id']);
        $hasil = app(PengajuanLemburService::class)->simpan($pemohon, $tanggal, $jamMulai, $jamSelesai, $keterangan);

        return redirect('lembur')
            ->with($hasil['ok'] ? 'success' : 'error', $hasil['pesan']);
    }

    public function batal(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $pj = PengajuanLembur::where('id', $id)
            ->where('user_id', $u['id'])
            ->where('status', 'Menunggu')
            ->first();

        if (! $pj) {
            return redirect('lembur')
                ->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
        }

        DB::transaction(function () use ($pj, $u) {
            $pj->update([
                'status'            => 'Ditolak',
                'catatan_keputusan' => 'Dibatalkan oleh pemohon.',
                'diproses_pada'     => now(),
            ]);
            catat_aktivitas('Batalkan Lembur', $u['nama_lengkap'] . ' menarik pengajuan lembur #' . $pj->id);
        });

        return redirect('lembur')->with('success', 'Pengajuan lembur berhasil dibatalkan.');
    }

    public function prosesAbsenLembur(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response()->json(['sukses' => false, 'pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
        }

        $tipe     = (string) $request->input('tipe');
        $tanggal  = (string) $request->input('tanggal', now()->toDateString());
        $lat      = (float) $request->input('lat');
        $lng      = (float) $request->input('lng');
        $akurasi  = $request->has('akurasi') ? round((float) $request->input('akurasi'), 2) : null;
        $foto     = $request->input('foto');

        if (! in_array($tipe, ['masuk', 'pulang'], true) || ! $lat || ! $lng
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['sukses' => false, 'pesan' => 'Data absen lembur tidak lengkap.'], 422);
        }

        $wajibSelfie = pengaturan('wajib_selfie', '1') === '1';
        $fileFoto = null;
        if ($foto) {
            $fileFoto = app(\App\Services\AbsenService::class)->simpanSelfie((int) $u['id'], $tipe, (string) $foto);
            if ($fileFoto === null && $wajibSelfie) {
                return response()->json(['sukses' => false, 'pesan' => 'Foto selfie tidak valid. Ulangi pengambilan foto.']);
            }
        } elseif ($wajibSelfie) {
            return response()->json(['sukses' => false, 'pesan' => 'Foto selfie wajib disertakan saat absen lembur.']);
        }

        $svc = app(AbsenLemburService::class);
        $user = \App\Models\User::find($u['id']);

        $hasil = $tipe === 'masuk'
            ? $svc->absenMasuk($user, $lat, $lng, $akurasi, $tanggal, $fileFoto)
            : $svc->absenPulang($user, $lat, $lng, $akurasi, $tanggal, $fileFoto);

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan'  => $hasil['pesan'],
            'data'   => $hasil['data'] ?? null,
        ], $hasil['ok'] ? 200 : 422);
    }
}
