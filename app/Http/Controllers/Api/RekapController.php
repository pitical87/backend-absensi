<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AtasanLangsung;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\Logbook;
use App\Models\User;
use App\Services\AtasanLangsungService;
use App\Services\BintangService;
use App\Services\ParafQrService;
use App\Services\RekapService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RekapController extends Controller
{
    public function statistik(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $data = $rekap->hitung($user->id, (int) now()->month, (int) now()->year);

        return response()->json([
            'sukses' => true,
            'kehadiran' => [
                'persen' => $data['persen'],
                'hadir' => $data['hadir'] + $data['dinas_luar'],
                'target' => $data['hari_efektif'],
            ],
            'jam_kerja' => [
                'total_jam' => round($data['total_menit'] / 60, 1),
                'target_jam' => (float) pengaturan('target_jam_kerja_bulanan', 160),
            ],
            'ketepatan' => [
                'tepat_masuk' => $data['persen_tepat_masuk'],
                'tepat_pulang' => $data['persen_tepat_pulang'],
            ],
            'bintang_bulanan' => $data['bintang_bulanan'],
        ]);
    }

    public function performaBulan(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $bulan = min(12, max(1, (int) ($req->query('bulan') ?: now()->subMonth()->month)));
        $tahun = (int) ($req->query('tahun') ?: now()->subMonth()->year);

        if ($bulan > now()->month && $tahun === (int) now()->year) {
            $bulan = now()->month;
        }

        $data = $rekap->hitung($user->id, $bulan, $tahun);
        $bintang = $data['bintang_bulanan'];
        $servis = app(BintangService::class);

        return response()->json([
            'sukses' => true,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nama_bulan' => BULAN_ID[$bulan] ?? $bulan,
            'bintang' => $bintang,
            'pesan' => $bintang === null ? null : $servis->pesanBulanan((float) $bintang),
        ]);
    }

    public function rekapBulanan(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter bulan/tahun tidak valid.',
            ], 422);
        }

        $d = $rekap->hitung($user->id, $bulan, $tahun);

        $detail = [];
        foreach ($d['per_tanggal'] as $tgl => $baris) {
            $rec = $baris['rec'];
            $item = [
                'tanggal' => $tgl,
                'hari' => Carbon::parse($tgl)->locale('id')->translatedFormat('l'),
                'status' => $baris['status'],
                'keterangan' => $baris['keterangan'],
                'jam_masuk' => null,
                'jam_pulang' => null,
                'menit_telat' => 0,
                'menit_pulang_awal' => 0,
                'total_jam_kerja' => null,
                'bintang_masuk' => null,
                'bintang_pulang' => null,
                'bintang_harian' => null,
            ];
            if ($rec) {
                $item['jam_masuk'] = substr((string) $rec->waktu_masuk, 11, 5);
                $item['jam_pulang'] = $rec->waktu_pulang ? substr((string) $rec->waktu_pulang, 11, 5) : null;
                $item['menit_telat'] = (int) $rec->menit_terlambat;
                $item['menit_pulang_awal'] = (int) $rec->menit_awal_pulang;
                $item['total_jam_kerja'] = $rec->total_menit_kerja !== null
                    ? round((int) $rec->total_menit_kerja / 60, 1) : null;
                $item['bintang_masuk'] = $rec->bintang_masuk !== null ? (int) $rec->bintang_masuk : null;
                $item['bintang_pulang'] = $rec->bintang_pulang !== null ? (int) $rec->bintang_pulang : null;
                $item['bintang_harian'] = $rec->bintang_harian !== null ? (float) $rec->bintang_harian : null;
            }
            $detail[] = $item;
        }

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
                'hari_dalam_bulan' => $d['hari_dalam_bulan'],
                'hari_berjalan' => $d['hari_berjalan'],
                'hari_efektif' => $d['hari_efektif'],
            ],
            'ringkasan' => [
                'hadir' => $d['hadir'],
                'tepat_masuk' => $d['tepat'],
                'terlambat' => $d['terlambat'],
                'total_menit_telat' => $d['menit_terlambat'],
                'tepat_pulang' => $d['tepat_pulang'],
                'pulang_awal' => $d['pulang_awal'],
                'total_menit_pulang_awal' => $d['menit_pulang_awal'],
                'izin' => $d['izin'], 'sakit' => $d['sakit'], 'cuti' => $d['cuti'],
                'dinas_luar' => $d['dinas_luar'], 'libur' => $d['libur'], 'alpa' => $d['alpa'],
                'anomali' => $d['anomali'],
                'persen_kehadiran' => $d['persen'],
                'total_jam_kerja' => round($d['total_menit'] / 60, 1),
                'bintang_bulanan' => $d['bintang_bulanan'],
            ],
            'detail' => $detail,
        ]);
    }

    public function rekapSayaPdf(
        Request $req,
        RekapService $rekap,
        ParafQrService $parafQr
    ): Response|JsonResponse {
        $user = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter bulan/tahun tidak valid.',
            ], 422);
        }

        $pegawai = User::with(['unitKerja', 'subUnit', 'jabatan', 'profesi'])
            ->find((int) $user->id);

        if (! $pegawai) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pegawai tidak ditemukan.',
            ], 404);
        }

        $atasanId = AtasanLangsung::where('user_id', (int) $pegawai->id)
            ->where('atasan_id', '!=', (int) $pegawai->id)
            ->value('atasan_id');
        $atasanUser = $atasanId ? User::find((int) $atasanId) : null;

        $atasan = [
            'nama' => (string) ($atasanUser?->nama_lengkap ?? ''),
            'nip' => (string) ($atasanUser?->nip ?? ''),
        ];

        $pdf = $this->buatPdfRekapHarian(
            collect([$pegawai]),
            $bulan,
            $tahun,
            $atasan,
            $rekap,
            $parafQr
        );

        $namaBulan = BULAN_ID[$bulan] ?? (string) $bulan;
        catat_aktivitas(
            'Cetak Rekap Kehadiran Pribadi',
            sprintf('%s mencetak PDF rekap %s %d', $pegawai->nama_lengkap, $namaBulan, $tahun),
            (int) $pegawai->id
        );

        $namaFile = sprintf('rekap-kehadiran-%04d-%02d.pdf', $tahun, $bulan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$namaFile.'"',
        ]);
    }

    public function rekapBawahanPdf(
        Request $req,
        RekapService $rekap,
        AtasanLangsungService $atasanService,
        ParafQrService $parafQr
    ): Response|JsonResponse {
        $user = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter bulan/tahun tidak valid.',
            ], 422);
        }

        $rawIds = $req->query('user_ids');
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }
        $requestedIds = collect(is_array($rawIds) ? $rawIds : [])
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($requestedIds === []) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pilih minimal satu pegawai.',
            ], 422);
        }

        if (! $user->isAdmin()) {
            $bolehIds = collect($atasanService->daftarBawahan($user))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (array_diff($requestedIds, $bolehIds) !== []) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Anda tidak memiliki akses ke rekap pegawai yang dipilih.',
                ], 403);
            }
        }

        $pegawaiList = User::with(['unitKerja', 'subUnit', 'jabatan', 'profesi'])
            ->whereIn('id', $requestedIds)
            ->orderBy('nama_lengkap')
            ->get();

        if ($pegawaiList->isEmpty()) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pegawai tidak ditemukan.',
            ], 404);
        }

        $pdf = $this->buatPdfRekapHarian(
            $pegawaiList,
            $bulan,
            $tahun,
            [
                'nama' => (string) $user->nama_lengkap,
                'nip' => (string) ($user->nip ?? ''),
            ],
            $rekap,
            $parafQr
        );

        $namaBulan = BULAN_ID[$bulan] ?? (string) $bulan;
        catat_aktivitas(
            'Cetak Rekap Bulanan Bawahan',
            sprintf('%s mencetak PDF rekap %s %d (%d pegawai)', $user->nama_lengkap, $namaBulan, $tahun, $pegawaiList->count()),
            (int) $user->id
        );

        $namaFile = sprintf('rekap-bulanan-%04d-%02d.pdf', $tahun, $bulan);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$namaFile.'"',
        ]);
    }

    /**
     * Render PDF rekap absen harian (1 halaman per pegawai) diikuti lembaran daftar logbook per pegawai,
     * lengkap dengan KOP RSUD & QR verifikasi.
     *
     * @param  Collection<int, User>  $pegawaiList
     * @param  array{nama: string, nip: string}  $atasan
     */
    private function buatPdfRekapHarian(
        Collection $pegawaiList,
        int $bulan,
        int $tahun,
        array $atasan,
        RekapService $rekap,
        ParafQrService $parafQr
    ): string {
        $hariDalamBulan = (int) Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hariDalamBulan);
        $pegawaiIds = $pegawaiList->pluck('id')->all();

        pastikan_libur_tetap($tahun);
        $liburMap = [];
        foreach (HariLibur::where('tanggal', '>=', $awal)->where('tanggal', '<=', $akhir)->get() as $libur) {
            $liburMap[$libur->tanggal->format('Y-m-d')] = $libur->keterangan ?: 'Hari Libur';
        }

        $izinPerUser = [];
        foreach (Izin::whereIn('user_id', $pegawaiIds)
            ->where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $akhir)
            ->where('tanggal_selesai', '>=', $awal)
            ->get() as $iz) {
            $mulaiTs = max($iz->tanggal_mulai->timestamp, strtotime($awal));
            $selesaiTs = min($iz->tanggal_selesai->timestamp, strtotime($akhir));
            for ($ts = $mulaiTs; $ts <= $selesaiTs; $ts += 86400) {
                $izinPerUser[(int) $iz->user_id][date('Y-m-d', $ts)] = $iz->jenis;
            }
        }

        $logbookPerUser = [];
        if ($pegawaiIds !== []) {
            $logbookRows = Logbook::query()
                ->whereIn('user_id', $pegawaiIds)
                ->where('tanggal', '>=', $awal)
                ->where('tanggal', '<=', $akhir)
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->orderBy('id')
                ->get();

            foreach ($logbookRows as $lb) {
                $carbonLb = $lb->tanggal instanceof Carbon ? $lb->tanggal : Carbon::parse($lb->tanggal);
                $uid = (int) $lb->user_id;
                $logbookPerUser[$uid][] = [
                    'tanggal_key' => $carbonLb->format('Y-m-d'),
                    'hari_tanggal' => HARI_ID[(int) $carbonLb->dayOfWeek].', '.$carbonLb->format('d/m/Y'),
                    'jam' => substr((string) $lb->jam, 0, 5),
                    'isi' => trim((string) $lb->isi),
                    'terverifikasi' => (bool) $lb->is_verified,
                ];
            }
        }

        $instansiDefault = strtoupper((string) pengaturan('nama_instansi', 'RSUD MERAUKE'));
        $lembarList = [];

        foreach ($pegawaiList as $pegawai) {
            $hasil = $rekap->hitung((int) $pegawai->id, $bulan, $tahun);
            $baris = [];

            for ($hari = 1; $hari <= $hariDalamBulan; $hari++) {
                $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
                $carbon = Carbon::createFromDate($tahun, $bulan, $hari);
                $isMinggu = $carbon->dayOfWeek === Carbon::SUNDAY;
                $hariTanggal = HARI_ID[(int) $carbon->dayOfWeek].', '.$carbon->format('d/m/Y');

                $jamMasuk = '';
                $jamPulang = '';
                $ket = '';

                if (isset($hasil['per_tanggal'][$tgl])) {
                    $entri = $hasil['per_tanggal'][$tgl];
                    $rec = $entri['rec'];

                    if ($rec) {
                        $jamMasuk = $rec->waktu_masuk ? substr((string) $rec->waktu_masuk, 11, 5) : '';
                        $jamPulang = $rec->waktu_pulang ? substr((string) $rec->waktu_pulang, 11, 5) : '';

                        $bagianKet = [];
                        if ($hasil['is_dokter']) {
                            if ($entri['status'] === 'Belum Pulang') {
                                $bagianKet[] = 'Belum Pulang';
                            } else {
                                $sesi = (int) ($entri['jumlah_sesi'] ?? 1);
                                $bagianKet[] = $sesi > 1 ? "Hadir ({$sesi} sesi)" : 'Hadir';
                            }
                        } else {
                            if ($entri['status'] === 'Belum Pulang') {
                                $bagianKet[] = 'Belum Pulang';
                            } elseif ($entri['status'] === 'Terlambat') {
                                $bagianKet[] = 'Terlambat '.((int) $rec->menit_terlambat).' mnt';
                            } else {
                                $bagianKet[] = 'Hadir';
                            }

                            $awalPulang = (int) ($rec->menit_awal_pulang ?? 0);
                            if ($awalPulang > 0) {
                                $bagianKet[] = 'Pulang awal '.$awalPulang.' mnt';
                            }
                        }
                        $ket = implode(', ', $bagianKet);
                    } elseif ($entri['status'] === 'Libur') {
                        $ket = $entri['keterangan'] ?: ($isMinggu ? '' : 'Libur');
                    } else {
                        $ket = (string) $entri['status'];
                    }
                } else {
                    $ket = $izinPerUser[(int) $pegawai->id][$tgl] ?? ($liburMap[$tgl] ?? '');
                }

                $baris[] = [
                    'no' => $hari,
                    'hari_tanggal' => $hariTanggal,
                    'minggu' => $isMinggu,
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                    'ket' => $ket,
                ];
            }

            $unitNama = $pegawai->unitKerja?->nama;
            $subUnitNama = $pegawai->subUnit?->nama;
            $unitKerja = $unitNama
                ? ($subUnitNama ? "{$unitNama} — {$subUnitNama}" : $unitNama)
                : $instansiDefault;

            $entriLogbook = $logbookPerUser[(int) $pegawai->id] ?? [];
            $logbookBaris = [];
            $hariLogbookUnik = [];
            $jumlahTerverifikasi = 0;

            foreach ($entriLogbook as $idx => $item) {
                $hariLogbookUnik[$item['tanggal_key']] = true;
                if ($item['terverifikasi']) {
                    $jumlahTerverifikasi++;
                }
                $logbookBaris[] = [
                    'no' => $idx + 1,
                    'hari_tanggal' => $item['hari_tanggal'],
                    'jam' => $item['jam'],
                    'isi' => $item['isi'],
                    'terverifikasi' => $item['terverifikasi'],
                ];
            }

            $lembarList[] = [
                'pegawai' => [
                    'nama' => (string) $pegawai->nama_lengkap,
                    'nip' => (string) ($pegawai->nip ?? ''),
                    'pangkat_gol' => '',
                    'jabatan' => (string) ($pegawai->jabatan?->nama ?: ($pegawai->jabatan_kategori ?: ($pegawai->posisi ?: ''))),
                    'unit_kerja' => $unitKerja,
                    'qr_paraf' => $parafQr->qrDataUri((int) $pegawai->id),
                ],
                'baris' => $baris,
                'logbook' => $logbookBaris,
                'logbook_ringkasan' => [
                    'total_entri' => count($logbookBaris),
                    'total_hari' => count($hariLogbookUnik),
                    'terverifikasi' => $jumlahTerverifikasi,
                ],
            ];
        }

        $logoKiri = $this->gambarKeDataUri(public_path('assets/img/kop-kabupaten-merauke.png'));
        $logoKanan = $this->gambarKeDataUri(public_path('assets/img/kop-rsud-merauke.png'));
        $namaBulan = BULAN_ID[$bulan] ?? (string) $bulan;

        $html = view('pdf.rekap_absen_harian', [
            'lembarList' => $lembarList,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'namaBulan' => $namaBulan,
            'tanggalCetak' => tgl_id(now()->format('Y-m-d'), false),
            'atasan' => $atasan,
            'logoKiri' => $logoKiri,
            'logoKanan' => $logoKanan,
        ])->render();

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');

        @set_time_limit(300);
        ini_set('memory_limit', '512M');
        $dompdf->render();

        return $dompdf->output();
    }

    private function gambarKeDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $isi = file_get_contents($path);
        if ($isi === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($isi);
    }

    public function rekapKeterlambatan(Request $req): JsonResponse
    {
        $user  = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $rows = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->where('a.user_id', $user->id)
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->orderBy('a.tanggal')
            ->get([
                'a.tanggal',
                'a.waktu_masuk',
                'a.waktu_pulang',
                'k.menit_telat',
                'k.bintang_masuk',
                'k.menit_awal_pulang',
                'k.bintang_pulang',
                'k.total_bintang',
            ]);

        $detail = $rows->map(fn ($r) => [
            'tanggal'    => substr((string) $r->tanggal, 0, 10),
            'hari'       => Carbon::parse($r->tanggal)->locale('id')->translatedFormat('l'),
            'jam_masuk'  => $r->waktu_masuk ? substr((string) $r->waktu_masuk, 11, 5) : null,
            'jam_pulang' => $r->waktu_pulang ? substr((string) $r->waktu_pulang, 11, 5) : null,
            'menit_telat' => (int) $r->menit_telat,
            'bintang_masuk' => $r->bintang_masuk !== null ? (int) $r->bintang_masuk : null,
            'menit_pulang_awal' => (int) $r->menit_awal_pulang,
            'bintang_pulang' => $r->bintang_pulang !== null ? (int) $r->bintang_pulang : null,
            'total_bintang' => $r->total_bintang !== null ? (float) $r->total_bintang : null,
        ]);

        $terlambat = $detail->where('menit_telat', '>', 0);
        $pulangAwal = $detail->where('menit_pulang_awal', '>', 0);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'ringkasan' => [
                'tercatat'   => $detail->count(),
                'terlambat'  => $terlambat->count(),
                'total_menit_telat' => $detail->sum('menit_telat'),
                'rata_menit_telat'  => $terlambat->isNotEmpty() ? round($terlambat->avg('menit_telat'), 1) : 0.0,
                'terlama_menit_telat' => $terlambat->max('menit_telat') ?? 0,
                'pulang_awal' => $pulangAwal->count(),
                'total_menit_pulang_awal' => $detail->sum('menit_pulang_awal'),
                'rata_bintang_masuk'  => $this->rataKolom($detail, 'bintang_masuk'),
                'rata_bintang_pulang' => $this->rataKolom($detail, 'bintang_pulang'),
                'rata_bintang_total'  => $this->rataKolom($detail, 'total_bintang'),
            ],
            'detail' => $detail->values(),
        ]);
    }

    private function rataKolom($detail, string $kolom): ?float
    {
        $isi = $detail->filter(fn ($d) => $d[$kolom] !== null);

        return $isi->isNotEmpty() ? round($isi->avg($kolom), 1) : null;
    }

    public function pegawaiTeladan(Request $req): JsonResponse
    {
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $daftar = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->where('u.role', '!=', 'admin')
            ->groupBy('u.id', 'u.nama_lengkap', 'uk.nama', 'su.nama')
            ->orderByDesc(DB::raw('COALESCE(SUM(k.total_bintang), 0)'))
            ->orderByDesc(DB::raw('AVG(k.total_bintang)'))
            ->orderBy('u.nama_lengkap')
            ->limit(10)
            ->get([
                'u.id AS pegawai_id',
                'u.nama_lengkap',
                DB::raw('COALESCE(su.nama, uk.nama) AS unit'),
                DB::raw('COUNT(*) AS hari_tercatat'),
                DB::raw('ROUND(COALESCE(SUM(k.total_bintang), 0), 1) AS total_bintang'),
                DB::raw('ROUND(AVG(k.total_bintang), 2) AS rata_bintang'),
                DB::raw('SUM(CASE WHEN k.menit_telat > 0 THEN 1 ELSE 0 END) AS jumlah_telat'),
                DB::raw('SUM(CASE WHEN k.bintang_masuk = 5 OR k.bintang_pulang = 5 THEN 1 ELSE 0 END) AS hari_bintang_lima'),
            ])
            ->values()
            ->map(fn ($r, $i) => [
                'peringkat'   => $i + 1,
                'pegawai_id'  => (int) $r->pegawai_id,
                'nama'        => $r->nama_lengkap,
                'unit'        => $r->unit,
                'hari_tercatat' => (int) $r->hari_tercatat,
                'total_bintang' => (float) $r->total_bintang,
                'rata_bintang'  => (float) $r->rata_bintang,
                'jumlah_telat'  => (int) $r->jumlah_telat,
                'hari_bintang_lima' => (int) $r->hari_bintang_lima,
            ]);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'daftar' => $daftar,
        ]);
    }
}
