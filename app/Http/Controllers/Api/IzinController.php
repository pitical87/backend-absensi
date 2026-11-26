<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AtasanLangsung;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;
use App\Services\CutiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IzinController extends Controller
{
    public function getTodayIzin(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $izin = Izin::where('user_id', $u->id)->where('status', 'Disetujui')->whereDate('tanggal_mulai', Carbon::today())->first();
        if (! $izin) {
            return response()->json([
                'sukses' => true,
                'hasLeave' => false,
                'izin' => null,
            ]);
        }

        return response()->json([
            'sukses' => true,
            'hasLeave' => true,
            'izin' => $izin,
        ]);
    }

    public function getIzinMenungguTotal(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $total = $this->tugasSaya($user)->count();

        return response()->json([
            'sukses' => true,
            'total' => $total,
        ]);
    }

    public function getDetailIzinMenunggu(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $daftar = $this->tugasSaya($user);

        return response()->json([
            'sukses' => true,
            'izin' => $daftar,
        ]);
    }

    private function tugasSaya(User $user): Collection
    {
        $lib = app(AlurIzinService::class);

        $query = Izin::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id,jabatan_id,seksi_pembina_id,posisi',
            'user.unitKerja:id,nama',
            'user.subUnit:id,nama',
            'persetujuan',
        ])
            ->where('status', 'Menunggu')
            ->where('tahap_aktif', '>', 0)
            ->orderBy('tahap_aktif')
            ->orderBy('id');

        if ($user->role !== 'admin') {
            $bawahanIds = $lib->idBawahanLangsung((int) $user->id);
            if ($bawahanIds === []) {
                return collect();
            }
            $query->whereIn('user_id', $bawahanIds);
        }

        return $query->get()->filter(function ($r) use ($lib, $user) {
            if (! $r->user) {
                return false;
            }
            $pemohon = ['id' => $r->user_id];

            return $lib->bolehBertindak(
                ['id' => $r->id, 'tahap_aktif' => $r->tahap_aktif],
                $pemohon,
                $user
            );
        })->map(function ($r) {
            $tahapAktif = $r->persetujuan->firstWhere('tahap', (int) $r->tahap_aktif);
            $r->setAttribute('posisi_tahap', $tahapAktif?->posisi_tahap ?: 'Atasan Langsung');

            return $r;
        })->values();
    }

    public function getRiwayatPersetujuan(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $riwayat = IzinPersetujuan::with(['pengajuan' => function ($q) {
            $q->select('id', 'user_id', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai')
                ->with(['user' => function ($q2) {
                    $q2->withTrashed()->select('id', 'nama_lengkap');
                }]);
        }])
            ->where('oleh_user_id', $user->id)
            ->orderBy('waktu', 'DESC')
            ->limit(30)
            ->get()
            ->filter(fn ($r) => $r->pengajuan !== null)
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'waktu' => $r->waktu?->toISOString(),
                'catatan' => $r->catatan,
                'status' => $r->status,
                'pengajuan' => [
                    'id' => $r->pengajuan->id,
                    'jenis' => $r->pengajuan->jenis,
                    'jenis_cuti' => $r->pengajuan->jenis_cuti,
                    'tanggal_mulai' => $r->pengajuan->tanggal_mulai->format('Y-m-d'),
                    'tanggal_selesai' => $r->pengajuan->tanggal_selesai->format('Y-m-d'),
                    'nama_pemohon' => $r->pengajuan->user?->nama_lengkap ?? 'Pegawai',
                ],
            ]);

        return response()->json([
            'sukses' => true,
            'riwayat' => $riwayat,
        ]);
    }

    public function riwayatIzin(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $alur = app(AlurIzinService::class);
        $punyaAtasan = $alur->atasanLangsungUntuk((int) $u->id)->isNotEmpty();
        $labelDefault = $punyaAtasan ? 'Atasan Langsung' : 'Admin';

        $izin = Izin::with(['diprosesOleh:id,nama_lengkap', 'persetujuan.user:id,nama_lengkap'])
            ->where('user_id', $u->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function ($i) use ($labelDefault) {
                // Tampilkan hanya tahap aktif/diputuskan (hilangkan tahap struktur lama yang Dilewati)
                $tahapAktif = $i->persetujuan
                    ->filter(fn ($p) => $p->status !== 'Dilewati')
                    ->values();

                if ($i->status === 'Menunggu') {
                    $persetujuan = [[
                        'tahap' => 1,
                        'posisi_tahap' => $labelDefault,
                        'status' => 'Menunggu',
                        'oleh_nama' => null,
                        'waktu' => null,
                    ]];
                } elseif ($tahapAktif->isNotEmpty()) {
                    $terakhir = $tahapAktif->last();
                    $persetujuan = [[
                        'tahap' => $terakhir->tahap,
                        'posisi_tahap' => in_array($terakhir->posisi_tahap, ['Atasan Langsung', 'Admin'], true)
                            ? $terakhir->posisi_tahap
                            : $labelDefault,
                        'status' => $terakhir->status,
                        'oleh_nama' => $terakhir->user?->nama_lengkap ?? $i->diprosesOleh?->nama_lengkap,
                        'waktu' => $terakhir->waktu?->toISOString(),
                    ]];
                } else {
                    $persetujuan = [[
                        'tahap' => 1,
                        'posisi_tahap' => 'Admin',
                        'status' => $i->status,
                        'oleh_nama' => $i->diprosesOleh?->nama_lengkap,
                        'waktu' => $i->processed_at?->toISOString(),
                    ]];
                }

                return [
                    'id' => $i->id,
                    'jenis' => $i->jenis,
                    'jenis_cuti' => $i->jenis_cuti,
                    'tanggal_mulai' => $i->tanggal_mulai->format('Y-m-d'),
                    'tanggal_selesai' => $i->tanggal_selesai->format('Y-m-d'),
                    'lama_hari' => $i->lama_hari,
                    'keterangan' => $i->keterangan,
                    'alamat_izin' => $i->alamat_izin,
                    'lampiran' => $i->lampiran,
                    'status' => $i->status,
                    'tahap_aktif' => $i->tahap_aktif,
                    'nomor_surat' => $i->nomor_surat,
                    'ttd_digital' => $i->ttd_digital,
                    'created_at' => $i->created_at?->toISOString(),
                    'persetujuan' => $persetujuan,
                ];
            });
        if ($izin->isEmpty()) {
            return response()->json([
                'sukses' => true,
                'message' => 'belum ada pengajuan izin',
                'izin' => [],
            ]);
        }

        return response()->json([
            'sukses' => true,
            'izin' => $izin,
        ]);
    }

    public function pengajuanIzin(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $jenis = (string) $req->get('jenis_pengajuan');
        $jenisCuti = trim((string) $req->get('jenis_cuti')) ?: null;
        $mulai = (string) $req->get('tanggal_mulai');
        $selesai = (string) ($req->get('tanggal_selesai') ?: '') ?: $mulai;
        $alamat = trim((string) $req->input('alamat')) ?: null;
        $alasan = trim((string) $req->get('alasan'));
        $berjenjang = in_array($jenis, ['Izin', 'Cuti'], true);

        $galat = [];
        $validJenis = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
        if (! in_array($jenis, $validJenis, true)) {
            $galat[] = 'Jenis pengajuan tidak valid.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) {
            $galat[] = 'Tanggal mulai wajib diisi.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) {
            $selesai = $mulai;
        }
        if ($selesai < $mulai) {
            $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }
        if ($alasan === '') {
            $galat[] = 'Alasan/keperluan wajib diisi.';
        }
        if (! $galat && (strtotime($selesai) - strtotime($mulai)) / 86400 > 60) {
            $galat[] = 'Rentang pengajuan maksimal 60 hari.';
        }

        if ($jenis === 'Cuti') {
            if (! is_pns($u)) {
                $galat[] = 'Cuti hanya dapat diajukan oleh pegawai berstatus PNS.';
            }
            if (! in_array($jenisCuti, jenis_cuti_list(), true)) {
                $galat[] = 'Jenis cuti wajib dipilih.';
            }
            if ($alamat === null) {
                $galat[] = 'Alamat selama cuti wajib diisi.';
            }
        }
        if ($jenis === 'Izin' && $alamat === null) {
            $galat[] = 'Alamat selama izin wajib diisi.';
        }

        if (! $galat) {
            $tindih = Izin::where('user_id', $u->id)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->where('tanggal_mulai', '<=', $selesai)->where('tanggal_selesai', '>=', $mulai)
                ->count() > 0;
            if ($tindih) {
                $galat[] = 'Rentang tanggal tersebut bertumpang-tindih dengan pengajuan lain yang masih Menunggu/Disetujui.';
            }
        }

        $lamaHari = null;
        if (! $galat && $berjenjang) {
            pastikan_libur_tetap((int) date('Y', strtotime($mulai)));
            pastikan_libur_tetap((int) date('Y', strtotime($selesai)));
            $liburSet = [];
            foreach (HariLibur::all() as $h) {
                $liburSet[$h->tanggal->format('Y-m-d')] = true;
            }
            $mingguLibur = pengaturan('minggu_libur', '0') === '1';
            $lamaHari = hari_kerja_antara($mulai, $selesai, $liburSet, $mingguLibur);
            if ($lamaHari < 1) {
                $lamaHari = 1;
            }

            $motongKuota = $jenis === 'Izin' || ($jenis === 'Cuti' && $jenisCuti === 'Cuti Tahunan');
            if ($motongKuota && is_pns($u)) {
                $sisa = app(CutiService::class)->rekap($u->id, (int) date('Y', strtotime($mulai)))['sisa'];
                if ($lamaHari > $sisa) {
                    $galat[] = "Sisa hak cuti tahun ini hanya {$sisa} hari kerja, "
                        ."sedangkan pengajuan ini memerlukan {$lamaHari} hari kerja.";
                }
            }
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
                $dir = 'izin/'.now()->format('Ym');
                $nama = $u->id.'_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(3))
                    .'.'.($eks === 'jpeg' ? 'jpg' : $eks);
                $lampiran = $berkas->storeAs($dir, $nama, 'public');
            }
        }

        if ($galat) {
            return response()->json(['sukses' => false, 'pesan' => implode(' ', $galat)]);
        }

        $izinId = null;

        $namaAtasan = AtasanLangsung::where('atasan_langsung.user_id', $u->id)
            ->where('atasan_langsung.atasan_id', '!=', $u->id)
            ->join('users as atasan_user', 'atasan_user.id', '=', 'atasan_langsung.atasan_id')
            ->where('atasan_user.status', 'aktif')
            ->whereNull('atasan_user.deleted_at')
            ->orderBy('atasan_langsung.id')
            ->pluck('atasan_user.nama_lengkap');

        $punyaAtasan = $namaAtasan->isNotEmpty();
        $keteranganAtasan = $punyaAtasan
            ? 'Pengajuan telah diajukan ke atasan langsung Anda: '.$namaAtasan->implode(', ').'.'
            : 'Anda belum memiliki atasan langsung; pengajuan diteruskan ke admin untuk disetujui.';

        $izinTerbuat = null;

        if ($berjenjang || $punyaAtasan) {
            $tahapAktif = 0;
            $statusAwal = 'Menunggu';

            DB::transaction(function () use ($u, $jenis, $jenisCuti, $mulai, $selesai, $lamaHari, $alamat, $alasan, $lampiran, &$tahapAktif, &$statusAwal, &$izinId, &$izinTerbuat) {
                $izin = Izin::create([
                    'user_id' => $u->id,
                    'jenis' => $jenis,
                    'jenis_cuti' => $jenis === 'Cuti' ? $jenisCuti : null,
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                    'lama_hari' => $lamaHari,
                    'alamat_izin' => $alamat,
                    'keterangan' => $alasan,
                    'lampiran' => $lampiran,
                    'status' => 'Menunggu',
                    'tahap_aktif' => 0,
                    'created_at' => now(),
                ]);
                $izinId = $izin->id;
                [$tahapAktif, $statusAwal] = app(AlurIzinService::class)->mulai($izinId, $u->toArray());
                $izin->update(['tahap_aktif' => $tahapAktif, 'status' => $statusAwal]);
                $izinTerbuat = $izin;
                catat_aktivitas('Pengajuan '.$jenis, $u->nama_lengkap.' — '.($jenisCuti ?: $jenis)
                    .' ('.$mulai.' s.d. '.$selesai.($lamaHari ? ", {$lamaHari} hr kerja" : '').')');
            });

            $pesan = 'Pengajuan '.$jenis.' terkirim. '.$keteranganAtasan;
        } else {
            $izin = Izin::create([
                'user_id' => $u->id,
                'jenis' => $jenis,
                'jenis_cuti' => null,
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'lama_hari' => null,
                'alamat_izin' => $alamat,
                'keterangan' => $alasan,
                'lampiran' => $lampiran,
                'status' => 'Menunggu',
                'tahap_aktif' => 0,
                'created_at' => now(),
            ]);
            $izinId = $izin->id;
            $izinTerbuat = $izin;
            catat_aktivitas('Pengajuan '.$jenis, $u->nama_lengkap.' mengajukan '.$jenis
            .' ('.$mulai.' s.d. '.$selesai.')');
            $pesan = 'Pengajuan '.$jenis.' terkirim. '.$keteranganAtasan;
        }

        if ($izinTerbuat) {
            app(AlurIzinService::class)->notifikasiPengajuanBaru($izinTerbuat, $u);
        }

        return response()->json([
            'sukses' => true,
            'pesan' => $pesan,
            'izin_id' => $izinId,
            'atasan_langsung' => $namaAtasan->values(),
            'keterangan_atasan' => $keteranganAtasan,
        ]);

    }

    public function deleteIzin(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');
        $izin = Izin::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'Menunggu')
            ->first();

        if (! $izin) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Izin tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        $izin->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Pengajuan izin berhasil dibatalkan.',
        ]);
    }

    public function prosesIzinMenunggu(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $id = (int) $req->input('id');
        $putusan = (string) $req->input('putusan');
        $catatan = trim((string) $req->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return response()->json([
                'sukses' => false, 'pesan' => 'Putusan tidak valid.',
            ], 422);
        }

        $iz = Izin::with('user:id,id,nama_lengkap,unit_kerja_id,sub_unit_id,jabatan_id,seksi_pembina_id,posisi')->find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pengajuan tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        $lib = app(AlurIzinService::class);
        $pemohonArr = $iz->user->toArray();
        $izArr = $iz->toArray();

        if (! $lib->bolehBertindak($izArr, $pemohonArr, $user)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Anda tidak berwenang memutus pengajuan ini.',
            ], 403);
        }

        $tahapLabel = IzinPersetujuan::where('pengajuan_id', $iz->id)
            ->where('tahap', (int) $iz->tahap_aktif)
            ->value('posisi_tahap') ?: 'Atasan Langsung';

        $hasil = $lib->proses($izArr, $pemohonArr, $user->id, $putusan, $catatan);
        catat_aktivitas('Persetujuan '.$iz->jenis, $iz->user->nama_lengkap
            .' — '.$tahapLabel
            .' oleh '.$user->nama_lengkap.' → '.$hasil);

        $pesan = match ($hasil) {
            'Ditolak' => 'Pengajuan ditolak.',
            'Disetujui' => 'Pengajuan disetujui.',
            default => 'Persetujuan tercatat, pengajuan diteruskan ke tahap berikutnya.',
        };

        return response()->json(['sukses' => true, 'pesan' => $pesan, 'hasil' => $hasil]);
    }
}
