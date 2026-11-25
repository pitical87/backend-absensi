<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\User;
use Carbon\Carbon;

class RekapStatusPegawaiService
{
    public const GRUP_PILIHAN = [
        'profesi' => 'Kelompok: Profesi',
        'unit' => 'Kelompok: Unit Kerja',
        'sub_unit' => 'Kelompok: Sub Unit',
        'tanpa' => 'Tanpa Pengelompokan',
    ];

    public static function labelStatusJudul(?string $statusPegawai): string
    {
        return match ($statusPegawai) {
            'Honor' => 'HONORER',
            'PNS' => 'PNS',
            'Non-PNS' => 'NON-PNS',
            'BLUD' => 'BLUD',
            default => '',
        };
    }

    public static function judulLaporan(?string $statusPegawai): string
    {
        $label = self::labelStatusJudul($statusPegawai);

        return $label !== ''
            ? 'REKAPAN ABSEN KERJA PEGAWAI '.$label.' RSUD MERAUKE'
            : 'REKAPAN ABSEN KERJA PEGAWAI RSUD MERAUKE';
    }

    /**
     * Bangun data matriks kehadiran bulanan berdasarkan status pegawai.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function bangunMatriks(array $f): array
    {
        $bulan = min(12, max(1, (int) ($f['bulan'] ?? now()->month)));
        $tahun = min(2100, max(2024, (int) ($f['tahun'] ?? now()->year)));
        $statusPegawai = in_array($f['status_pegawai'] ?? '', User::STATUS_PEGAWAI, true)
            ? (string) $f['status_pegawai']
            : '';
        $grup = array_key_exists($f['grup'] ?? '', self::GRUP_PILIHAN)
            ? (string) $f['grup']
            : 'profesi';

        $hariDalamBulan = (int) Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $sekarang = Carbon::now();
        if ($tahun > $sekarang->year || ($tahun === $sekarang->year && $bulan > $sekarang->month)) {
            $hariBerjalan = 0;
        } elseif ($tahun === $sekarang->year && $bulan === $sekarang->month) {
            $hariBerjalan = (int) $sekarang->format('j');
        } else {
            $hariBerjalan = $hariDalamBulan;
        }

        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hariDalamBulan);

        pastikan_libur_tetap($tahun);

        $liburMap = [];
        foreach (HariLibur::where('tanggal', '>=', $awal)->where('tanggal', '<=', $akhir)->get() as $r) {
            $liburMap[$r->tanggal->format('Y-m-d')] = $r->keterangan ?: 'Hari libur';
        }

        $tanggalMeta = [];
        $hariLiburUmum = 0;
        for ($d = 1; $d <= $hariDalamBulan; $d++) {
            $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
            $isMinggu = Carbon::createFromDate($tahun, $bulan, $d)->dayOfWeekIso === 7;
            $merah = $isMinggu || isset($liburMap[$tgl]);
            if ($merah && $d <= $hariBerjalan) {
                $hariLiburUmum++;
            }
            $tanggalMeta[$d] = [
                'hari' => $d,
                'tgl' => $tgl,
                'merah' => $merah,
                'ket_libur' => $liburMap[$tgl] ?? ($isMinggu ? 'Minggu' : null),
            ];
        }

        $query = User::select(
            'users.id',
            'users.nama_lengkap',
            'users.nip',
            'users.status_pegawai',
            'users.unit_kerja_id',
            'users.sub_unit_id',
            'users.profesi_id',
            'uk.nama AS unit_nama',
            'su.nama AS sub_nama',
            'p.nama AS profesi_nama'
        )
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->where('users.role', 'pegawai')
            ->where('users.status', 'aktif');

        if ($statusPegawai !== '') {
            $query->where('users.status_pegawai', $statusPegawai);
        }
        if (! empty($f['unit'])) {
            $query->where('users.unit_kerja_id', (int) $f['unit']);
        }
        if (! empty($f['sub'])) {
            $query->where('users.sub_unit_id', (int) $f['sub']);
        }
        if (! empty($f['prof'])) {
            $query->where('users.profesi_id', (int) $f['prof']);
        }
        if (! empty($f['q'])) {
            $q = mb_substr(trim((string) $f['q']), 0, 60);
            $query->where(function ($sub) use ($q) {
                $sub->where('users.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('users.nip', 'like', "%{$q}%");
            });
        }

        $pegawaiList = $query
            ->orderByRaw('COALESCE(p.id, 999999)')
            ->orderByRaw('COALESCE(uk.id, 999999)')
            ->orderBy('users.nama_lengkap')
            ->get();

        $userIds = $pegawaiList->pluck('id')->map(fn ($id) => (int) $id)->all();

        $absensiMap = [];
        $izinMap = [];
        $jadwalMap = [];

        if ($userIds !== []) {
            $absensiRows = Absensi::whereIn('user_id', $userIds)
                ->where('tanggal', '>=', $awal)
                ->where('tanggal', '<=', $akhir)
                ->get(['user_id', 'tanggal']);
            foreach ($absensiRows as $row) {
                $absensiMap[(int) $row->user_id][$row->tanggal->format('Y-m-d')] = true;
            }

            $izinRows = Izin::whereIn('user_id', $userIds)
                ->where('status', 'Disetujui')
                ->where('tanggal_mulai', '<=', $akhir)
                ->where('tanggal_selesai', '>=', $awal)
                ->orderBy('tanggal_mulai')
                ->get(['id', 'user_id', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai', 'keterangan']);

            $tsAwal = strtotime($awal);
            $tsAkhir = strtotime($akhir);
            foreach ($izinRows as $iz) {
                $mulai = max($iz->tanggal_mulai->timestamp, $tsAwal);
                $selesai = min($iz->tanggal_selesai->timestamp, $tsAkhir);
                $ketRingkas = trim((string) ($iz->keterangan ?: $iz->jenis_cuti ?: ''));
                for ($t = $mulai; $t <= $selesai; $t += 86400) {
                    $izinMap[(int) $iz->user_id][date('Y-m-d', $t)] = [
                        'id' => (int) $iz->id,
                        'jenis' => (string) $iz->jenis,
                        'keterangan' => $ketRingkas,
                    ];
                }
            }

            $jadwalMap = app(JadwalEfektifService::class)
                ->petaUntukBanyakPegawai($pegawaiList, $awal, $akhir);
        }

        $kelompokMap = [];
        $totalPersen = 0;

        foreach ($pegawaiList as $u) {
            $uid = (int) $u->id;
            $baris = $this->bangunBarisPegawai(
                $u,
                $hariDalamBulan,
                $hariBerjalan,
                $tanggalMeta,
                $absensiMap[$uid] ?? [],
                $izinMap[$uid] ?? [],
                $jadwalMap[$uid] ?? []
            );

            $totalPersen += $baris['persen'];

            $namaGrup = match ($grup) {
                'profesi' => strtoupper(trim((string) ($u->profesi_nama ?: 'LAINNYA'))),
                'unit' => strtoupper(trim((string) ($u->unit_nama ?: 'TANPA UNIT KERJA'))),
                'sub_unit' => strtoupper(trim((string) (
                    $u->sub_nama
                        ? ($u->unit_nama ? $u->unit_nama.' — '.$u->sub_nama : $u->sub_nama)
                        : ($u->unit_nama ?: 'TANPA UNIT')
                ))),
                default => '',
            };

            $kelompokMap[$namaGrup][] = $baris;
        }

        $kelompokList = [];
        foreach ($kelompokMap as $namaGrup => $anggota) {
            $kelompokList[] = [
                'nama' => $namaGrup,
                'pegawai' => $anggota,
            ];
        }

        $totalPegawai = $pegawaiList->count();

        return [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nama_bulan' => BULAN_ID[$bulan] ?? (string) $bulan,
            'status_pegawai' => $statusPegawai,
            'grup' => $grup,
            'judul' => self::judulLaporan($statusPegawai),
            'periode_label' => 'BULAN '.strtoupper(BULAN_ID[$bulan] ?? (string) $bulan).'/ TAHUN '.$tahun,
            'hari_dalam_bulan' => $hariDalamBulan,
            'hari_berjalan' => $hariBerjalan,
            'hari_kerja_umum' => max(0, $hariBerjalan - $hariLiburUmum),
            'tanggal_meta' => $tanggalMeta,
            'kelompok' => $kelompokList,
            'total_pegawai' => $totalPegawai,
            'rata_persen' => $totalPegawai > 0 ? round($totalPersen / $totalPegawai, 1) : 0.0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tanggalMeta
     * @param  array<string, bool>  $absUser
     * @param  array<string, array{id: int, jenis: string, keterangan: string}>  $izinUser
     * @param  array<string, int>  $jadwalUser
     * @return array<string, mixed>
     */
    private function bangunBarisPegawai(
        User $u,
        int $hariDalamBulan,
        int $hariBerjalan,
        array $tanggalMeta,
        array $absUser,
        array $izinUser,
        array $jadwalUser
    ): array {
        $harian = [];
        $hadir = 0;
        $dinasLuar = 0;
        $izin = 0;
        $sakit = 0;
        $cuti = 0;
        $alpa = 0;
        $libur = 0;

        /** @var array<int, array{jenis: string, keterangan: string, hari: int}> */
        $ringkasanIzin = [];

        for ($d = 1; $d <= $hariDalamBulan; $d++) {
            $meta = $tanggalMeta[$d];
            $tgl = $meta['tgl'];

            if ($d > $hariBerjalan) {
                $harian[$d] = [
                    'tipe' => 'belum',
                    'kode' => '',
                    'izin_id' => null,
                    'label_panjang' => null,
                ];
                continue;
            }

            $adaAbsen = isset($absUser[$tgl]);
            $iz = $izinUser[$tgl] ?? null;
            $adalahLibur = $meta['merah'] || ! isset($jadwalUser[$tgl]);

            if ($adaAbsen) {
                $hadir++;
                $harian[$d] = [
                    'tipe' => 'hadir',
                    'kode' => 'H',
                    'izin_id' => null,
                    'label_panjang' => null,
                ];
            } elseif ($iz !== null) {
                $jenis = $iz['jenis'];
                $kode = match ($jenis) {
                    'Sakit' => 'S',
                    'Cuti' => 'C',
                    'Dinas Luar' => 'DL',
                    default => 'I',
                };
                if (! $adalahLibur) {
                    match ($jenis) {
                        'Sakit' => $sakit++,
                        'Cuti' => $cuti++,
                        'Dinas Luar' => $dinasLuar++,
                        default => $izin++,
                    };
                    if (! isset($ringkasanIzin[$iz['id']])) {
                        $ringkasanIzin[$iz['id']] = [
                            'jenis' => $jenis,
                            'keterangan' => $iz['keterangan'],
                            'hari' => 0,
                        ];
                    }
                    $ringkasanIzin[$iz['id']]['hari']++;
                } else {
                    $libur++;
                }

                $labelPanjang = strtoupper($jenis);
                if ($iz['keterangan'] !== '') {
                    $labelPanjang .= ' '.strtoupper($iz['keterangan']);
                }

                $harian[$d] = [
                    'tipe' => $jenis === 'Dinas Luar' ? 'dinas' : 'izin',
                    'kode' => $adalahLibur ? 'L' : $kode,
                    'izin_id' => $iz['id'],
                    'label_panjang' => $labelPanjang,
                ];
            } elseif ($adalahLibur) {
                $libur++;
                $harian[$d] = [
                    'tipe' => 'libur',
                    'kode' => 'L',
                    'izin_id' => null,
                    'label_panjang' => null,
                ];
            } else {
                $alpa++;
                $harian[$d] = [
                    'tipe' => 'alpa',
                    'kode' => 'A',
                    'izin_id' => null,
                    'label_panjang' => null,
                ];
            }
        }

        // Gabungkan sel berurutan (>= 3 hari) untuk izin yang sama agar rapi seperti format rumah sakit
        $sel = [];
        $d = 1;
        while ($d <= $hariDalamBulan) {
            $cur = $harian[$d];
            if ($cur['izin_id'] !== null) {
                $akhirSpan = $d;
                while (
                    $akhirSpan + 1 <= $hariDalamBulan
                    && $harian[$akhirSpan + 1]['izin_id'] === $cur['izin_id']
                ) {
                    $akhirSpan++;
                }
                $panjang = $akhirSpan - $d + 1;
                if ($panjang >= 3) {
                    $maksKarakter = max(12, $panjang * 4);
                    $teks = (string) $cur['label_panjang'];
                    if (mb_strlen($teks) > $maksKarakter) {
                        $teks = mb_substr($teks, 0, $maksKarakter - 1).'…';
                    }
                    $sel[] = [
                        'mulai' => $d,
                        'sampai' => $akhirSpan,
                        'colspan' => $panjang,
                        'tipe' => $cur['tipe'],
                        'teks' => $teks,
                        'gabung' => true,
                    ];
                    $d = $akhirSpan + 1;
                    continue;
                }
            }

            $sel[] = [
                'mulai' => $d,
                'sampai' => $d,
                'colspan' => 1,
                'tipe' => $cur['kode'] === 'L' ? 'libur' : $cur['tipe'],
                'teks' => $cur['kode'],
                'gabung' => false,
            ];
            $d++;
        }

        $jmlh = $hadir + $dinasLuar;
        $hariKerja = $hadir + $dinasLuar + $izin + $sakit + $cuti + $alpa;
        $persen = $hariKerja > 0 ? (int) round(($jmlh / $hariKerja) * 100) : 0;

        $ketBagian = [];
        foreach ($ringkasanIzin as $item) {
            if ($item['hari'] <= 0) {
                continue;
            }
            $bag = strtoupper($item['jenis']).' '.$item['hari'];
            if ($item['keterangan'] !== '') {
                $ketPendek = mb_strlen($item['keterangan']) > 28
                    ? mb_substr($item['keterangan'], 0, 27).'…'
                    : $item['keterangan'];
                $bag .= ' ('.strtoupper($ketPendek).')';
            }
            $ketBagian[] = $bag;
        }
        if ($alpa > 0) {
            $ketBagian[] = 'ALPA '.$alpa;
        }

        return [
            'id' => (int) $u->id,
            'nama' => (string) $u->nama_lengkap,
            'nip' => $u->nip,
            'status_pegawai' => (string) ($u->status_pegawai ?? 'Non-PNS'),
            'unit_nama' => $u->unit_nama,
            'sub_nama' => $u->sub_nama,
            'profesi_nama' => $u->profesi_nama,
            'sel' => $sel,
            'jmlh' => $jmlh,
            'hari_kerja' => $hariKerja,
            'persen' => $persen,
            'alpa' => $alpa,
            'izin' => $izin,
            'sakit' => $sakit,
            'cuti' => $cuti,
            'dinas_luar' => $dinasLuar,
            'libur' => $libur,
            'ket' => implode('; ', $ketBagian),
        ];
    }
}
