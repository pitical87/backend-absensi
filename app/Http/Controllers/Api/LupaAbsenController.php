<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanLupaAbsen;
use App\Models\User;
use App\Services\AtasanLangsungService;
use App\Services\LupaAbsenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LupaAbsenController extends Controller
{
    /**
     * GET /lupa-absen/cek — periksa apakah pegawai punya jadwal dan alpa dalam 1 bulan terakhir.
     */
    public function cek(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        $hasil = $svc->cekKelayakanBulanan($u);

        return response()->json(['sukses' => true] + $hasil);
    }

    /**
     * GET /lupa-absen — daftar tanggal alpa yang bisa diajukan + riwayat pengajuan sendiri.
     */
    public function daftar(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        $cek = $svc->cekKelayakanBulanan($u);

        $riwayat = PengajuanLupaAbsen::with([
            'shift:id,kategori,jam_masuk,jam_pulang',
            'diprosesOlehUser:id,nama_lengkap',
        ])
            ->where('user_id', $u->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (PengajuanLupaAbsen $r) => [
                'id' => (int) $r->id,
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'hari' => $r->tanggal->locale('id')->translatedFormat('l'),
                'shift' => $r->shift ? [
                    'id' => (int) $r->shift->id,
                    'kategori' => $r->shift->kategori,
                    'jam_masuk' => $r->shift->jam_masuk?->format('H:i'),
                    'jam_pulang' => $r->shift->jam_pulang?->format('H:i'),
                ] : null,
                'alasan' => $r->alasan,
                'lampiran' => $r->lampiran,
                'status' => $r->status,
                'catatan_keputusan' => $r->catatan_keputusan,
                'diproses_oleh' => $r->diprosesOlehUser?->nama_lengkap,
                'diproses_pada' => $r->diproses_pada?->toISOString(),
                'absensi_id' => $r->absensi_id ? (int) $r->absensi_id : null,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        $atasanNama = $svc->atasanListUntuk((int) $u->id)->pluck('nama_lengkap')->values();

        return response()->json([
            'sukses' => true,
            'atasan_langsung' => $atasanNama,
            'riwayat' => $riwayat,
        ] + $cek);
    }

    /**
     * POST /lupa-absen — ajukan lupa absen (seperti pada izin: tanggal, alasan, lampiran opsional).
     */
    public function ajukan(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        $tanggal = trim((string) $req->input('tanggal'));
        $alasan = trim((string) $req->input('alasan'));

        $galat = [];
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $galat[] = 'Tanggal lupa absen wajib dipilih.';
        }
        if ($alasan === '') {
            $galat[] = 'Alasan/keterangan lupa absen wajib diisi.';
        }

        $lampiran = null;
        $allowedExt = ['jpeg', 'jpg', 'png', 'pdf'];
        if ($req->hasFile('lampiran')) {
            $berkas = $req->file('lampiran');
            $eks = strtolower($berkas->getClientOriginalExtension() ?: '');
            if (! in_array($eks, $allowedExt, true)) {
                $galat[] = 'Lampiran hanya boleh berupa JPG, PNG, atau PDF.';
            } elseif ($berkas->getSize() > 3 * 1024 * 1024) {
                $galat[] = 'Ukuran lampiran maksimal 3 MB.';
            } else {
                $dir = 'lupaabsen/'.now()->format('Ym');
                $nama = $u->id.'_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3))
                    .'.'.($eks === 'jpeg' ? 'jpg' : $eks);
                $lampiran = $berkas->storeAs($dir, $nama, 'public');
            }
        }

        if ($galat) {
            if ($lampiran) {
                Storage::disk('public')->delete($lampiran);
            }

            return response()->json(['sukses' => false, 'pesan' => implode(' ', $galat)], 422);
        }

        $hasil = $svc->simpan($u, $tanggal, $alasan, $lampiran);
        if (! $hasil['ok'] && $lampiran) {
            Storage::disk('public')->delete($lampiran);
        }

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan' => $hasil['pesan'],
            'pengajuan_id' => $hasil['pengajuan']?->id,
        ], $hasil['ok'] ? 201 : 422);
    }

    /**
     * DELETE /lupa-absen/{id} — batalkan pengajuan sendiri yang masih Menunggu.
     */
    public function batal(Request $req, int $id): JsonResponse
    {
        $u = $req->get('user');
        $pj = PengajuanLupaAbsen::where('id', $id)
            ->where('user_id', $u->id)
            ->where('status', 'Menunggu')
            ->first();

        if (! $pj) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pengajuan lupa absen tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        if ($pj->lampiran) {
            Storage::disk('public')->delete($pj->lampiran);
        }
        $pj->delete();

        catat_aktivitas('Batalkan Lupa Absen', $u->nama_lengkap.' membatalkan pengajuan lupa absen #'.$id);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Pengajuan lupa absen berhasil dibatalkan.',
        ]);
    }

    private function cekWewenangAtasan(User $user): ?JsonResponse
    {
        if ($user->role === 'admin' || app(AtasanLangsungService::class)->punyaBawahan($user)) {
            return null;
        }

        return response()->json([
            'sukses' => false,
            'pesan' => 'Menu persetujuan lupa absen hanya tersedia bagi atasan langsung yang memiliki bawahan.',
        ], 403);
    }

    /**
     * GET /lupa-absen/total — jumlah pengajuan Menunggu untuk badge atasan langsung.
     */
    public function menungguTotal(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        $total = count($svc->tugasAtasan($u));

        return response()->json(['sukses' => true, 'total' => $total]);
    }

    /**
     * GET /lupa-absen/menunggu — daftar pengajuan Menunggu untuk atasan langsung / admin.
     */
    public function menungguDaftar(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        if ($tolak = $this->cekWewenangAtasan($u)) {
            return $tolak;
        }

        $daftar = $svc->tugasAtasan($u);

        return response()->json([
            'sukses' => true,
            'total' => count($daftar),
            'data' => array_map(fn (PengajuanLupaAbsen $r) => [
                'id' => (int) $r->id,
                'pemohon' => [
                    'id' => (int) $r->user->id,
                    'nama' => $r->user->nama_lengkap,
                    'nip' => $r->user->nip,
                    'unit' => $r->user->unitKerja?->nama,
                    'sub_unit' => $r->user->subUnit?->nama,
                ],
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'hari' => $r->tanggal->locale('id')->translatedFormat('l'),
                'shift' => $r->shift ? [
                    'id' => (int) $r->shift->id,
                    'kategori' => $r->shift->kategori,
                    'jam_masuk' => $r->shift->jam_masuk?->format('H:i'),
                    'jam_pulang' => $r->shift->jam_pulang?->format('H:i'),
                ] : null,
                'alasan' => $r->alasan,
                'lampiran' => $r->lampiran,
                'diajukan_pada' => $r->created_at?->toISOString(),
            ], $daftar),
        ]);
    }

    /**
     * POST /lupa-absen/proses — putuskan sebagai atasan langsung / admin.
     */
    public function proses(Request $req, LupaAbsenService $svc): JsonResponse
    {
        $u = $req->get('user');
        $id = (int) $req->input('id');
        $putusan = (string) $req->input('putusan');
        $catatan = trim((string) $req->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return response()->json(['sukses' => false, 'pesan' => 'Putusan harus setuju atau tolak.'], 422);
        }

        $pj = PengajuanLupaAbsen::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return response()->json(['sukses' => false, 'pesan' => 'Pengajuan tidak ditemukan atau sudah diproses.'], 404);
        }
        if ((int) $pj->user_id === (int) $u->id) {
            return response()->json(['sukses' => false, 'pesan' => 'Tidak dapat memutus pengajuan milik sendiri.'], 403);
        }
        if ($u->role !== 'admin' && ! $svc->isAtasan($u, (int) $pj->user_id)) {
            return response()->json(['sukses' => false, 'pesan' => 'Anda bukan atasan langsung pemohon ini.'], 403);
        }

        $hasil = $svc->putuskan($pj, $putusan, (int) $u->id, $catatan);

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan' => $hasil['pesan'],
            'status' => $hasil['status'],
            'absensi_id' => $hasil['absensi_id'],
        ], $hasil['ok'] ? 200 : 422);
    }

    /**
     * GET /lupa-absen/riwayat-persetujuan — riwayat keputusan lupa absen oleh user ini.
     */
    public function riwayatPersetujuan(Request $req): JsonResponse
    {
        $u = $req->get('user');
        if ($tolak = $this->cekWewenangAtasan($u)) {
            return $tolak;
        }

        $riwayat = PengajuanLupaAbsen::with([
            'user' => fn ($q) => $q->withTrashed()->select('id', 'nama_lengkap'),
            'shift:id,kategori,jam_masuk,jam_pulang',
        ])
            ->where('diproses_oleh', $u->id)
            ->where('status', '!=', 'Menunggu')
            ->orderByDesc('diproses_pada')
            ->limit(30)
            ->get()
            ->map(fn (PengajuanLupaAbsen $r) => [
                'id' => (int) $r->id,
                'waktu' => $r->diproses_pada?->toISOString(),
                'status' => $r->status,
                'catatan' => $r->catatan_keputusan,
                'pemohon' => $r->user?->nama_lengkap ?? 'Pegawai',
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'shift' => $r->shift ? [
                    'kategori' => $r->shift->kategori,
                    'jam_masuk' => $r->shift->jam_masuk?->format('H:i'),
                    'jam_pulang' => $r->shift->jam_pulang?->format('H:i'),
                ] : null,
            ]);

        return response()->json(['sukses' => true, 'riwayat' => $riwayat]);
    }
}
