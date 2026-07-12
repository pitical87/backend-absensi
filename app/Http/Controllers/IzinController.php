<?php

namespace App\Http\Controllers;

use App\Services\AlurIzinService;
use App\Services\CutiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IzinController extends Controller
{
    private const JENIS = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
    private const BERJENJANG = ['Izin', 'Cuti'];

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $riwayat = DB::table('pengajuan_izin as i')
            ->select('i.*', 'a.nama_lengkap AS admin_nama')
            ->leftJoin('users as a', 'a.id', '=', 'i.diproses_oleh')
            ->where('i.user_id', $u['id'])
            ->orderBy('i.id', 'DESC')->limit(50)
            ->get()->all();

        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($riwayat,
            static fn ($r) => in_array($r->jenis, self::BERJENJANG, true)), 'id');
        if ($idBerjenjang) {
            foreach (DB::table('izin_persetujuan as p')
                         ->select('p.*', 'o.nama_lengkap AS oleh_nama')
                         ->leftJoin('users as o', 'o.id', '=', 'p.oleh_user_id')
                         ->whereIn('pengajuan_id', $idBerjenjang)
                         ->orderBy('tahap')->get() as $p) {
                $tahapPer[(int) $p->pengajuan_id][] = $p;
            }
        }

        $userObj = (object) $u;
        $cuti = is_pns($userObj) ? app(CutiService::class)->rekap($u['id'], (int) now()->format('Y')) : null;

        return view('pegawai.izin', [
            'u' => $u, 'riwayat' => $riwayat, 'jenisList' => self::JENIS,
            'jenisCutiList' => jenis_cuti_list(), 'tahapPer' => $tahapPer, 'cuti' => $cuti,
        ]);
    }

    public function ajukan(Request $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $jenis     = (string) $request->input('jenis');
        $jenisCuti = trim((string) $request->input('jenis_cuti')) ?: null;
        $mulai     = (string) $request->input('tanggal_mulai');
        $selesai   = (string) $request->input('tanggal_selesai') ?: $mulai;
        $alamat    = trim((string) $request->input('alamat_izin')) ?: null;
        $ket       = trim((string) $request->input('keterangan'));
        $berjenjang = in_array($jenis, self::BERJENJANG, true);

        $galat = [];
        if (! in_array($jenis, self::JENIS, true)) $galat[] = 'Jenis pengajuan tidak valid.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $galat[] = 'Tanggal mulai wajib diisi.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) $selesai = $mulai;
        if ($selesai < $mulai) $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        if ($ket === '') $galat[] = 'Alasan/keperluan wajib diisi.';
        if (! $galat && (strtotime($selesai) - strtotime($mulai)) / 86400 > 60) {
            $galat[] = 'Rentang pengajuan maksimal 60 hari.';
        }

        $userObj = (object) $u;
        if ($jenis === 'Cuti') {
            if (! is_pns($userObj)) {
                $galat[] = 'Cuti hanya dapat diajukan oleh pegawai berstatus PNS. '
                    . 'Gunakan jenis "Izin" untuk keperluan non-cuti.';
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
            $tindih = DB::table('pengajuan_izin')
                ->where('user_id', $u['id'])->whereIn('status', ['Menunggu', 'Disetujui'])
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
            foreach (DB::table('hari_libur')->get() as $h) {
                $liburSet[$h->tanggal] = true;
            }
            $mingguLibur = pengaturan('minggu_libur', '0') === '1';
            $lamaHari = hari_kerja_antara($mulai, $selesai, $liburSet, $mingguLibur);
            if ($lamaHari < 1) $lamaHari = 1;

            $motongKuota = $jenis === 'Izin' || ($jenis === 'Cuti' && $jenisCuti === 'Cuti Tahunan');
            if ($motongKuota && is_pns($userObj)) {
                $sisa = app(CutiService::class)->rekap($u['id'], (int) date('Y', strtotime($mulai)))['sisa'];
                if ($lamaHari > $sisa) {
                    $galat[] = "Sisa hak cuti tahun ini hanya {$sisa} hari kerja, "
                        . "sedangkan pengajuan ini memerlukan {$lamaHari} hari kerja.";
                }
            }
        }

        $lampiran = null;
        if ($request->hasFile('lampiran')) {
            $berkas = $request->file('lampiran');
            $eks = strtolower($berkas->getClientOriginalExtension() ?: '');
            if (! in_array($eks, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                $galat[] = 'Lampiran hanya boleh berupa JPG, PNG, atau PDF.';
            } elseif ($berkas->getSize() > 3 * 1024 * 1024) {
                $galat[] = 'Ukuran lampiran maksimal 3 MB.';
            } else {
                $dir = 'izin/' . now()->format('Ym');
                $nama = $u['id'] . '_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . ($eks === 'jpeg' ? 'jpg' : $eks);
                $path = $berkas->storeAs($dir, $nama, 'public');
                $lampiran = $dir . '/' . $nama;
            }
        }

        if ($galat) {
            return redirect('izin')->with('flash_gagal', implode(' ', $galat));
        }

        $data = [
            'user_id'         => $u['id'],
            'jenis'           => $jenis,
            'jenis_cuti'      => $jenis === 'Cuti' ? $jenisCuti : null,
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'lama_hari'       => $lamaHari,
            'alamat_izin'     => $alamat,
            'keterangan'      => $ket,
            'lampiran'        => $lampiran,
            'status'          => 'Menunggu',
            'tahap_aktif'     => 0,
            'created_at'      => now(),
        ];

        if ($berjenjang) {
            DB::transaction(function () use ($data, $u, $jenis, $mulai, $selesai, $lamaHari, $jenisCuti, &$pesan) {
                $id = DB::table('pengajuan_izin')->insertGetId($data);
                [$tahapAktif, $statusAwal] = app(AlurIzinService::class)->mulai($id, $u);
                $update = ['tahap_aktif' => $tahapAktif, 'status' => $statusAwal];
                if ($statusAwal === 'Disetujui') {
                    $update['processed_at'] = now();
                    $update['nomor_surat'] = sprintf('800/%03d/RSUD-MRK/%02d/%d',
                        DB::table('pengajuan_izin')->whereNotNull('nomor_surat')
                            ->whereMonth('created_at', now()->format('n'))->whereYear('created_at', now()->format('Y'))
                            ->count() + 1, now()->format('n'), now()->format('Y'));
                    $update['kode_verifikasi'] = strtoupper(bin2hex(random_bytes(5)));
                }
                DB::table('pengajuan_izin')->where('id', $id)->update($update);
                catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' — ' . ($jenisCuti ?: $jenis)
                    . ' (' . $mulai . ' s.d. ' . $selesai . ", {$lamaHari} hr kerja)");
                $pesan = $statusAwal === 'Disetujui'
                    ? "Pengajuan {$jenis} langsung disetujui (posisi Anda berada di puncak alur persetujuan)."
                    : 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan '
                        . label_tahap_izin($tahapAktif) . '.';
            });
        } else {
            DB::table('pengajuan_izin')->insert($data);
            catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' mengajukan ' . $jenis
                . ' (' . $mulai . ' s.d. ' . $selesai . ')');
            $pesan = 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan admin.';
        }

        return redirect('izin')->with('flash_sukses', $pesan);
    }

    public function batal(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        $baris = DB::table('pengajuan_izin')
            ->where('id', $id)->where('user_id', $u['id'])->where('status', 'Menunggu')
            ->first();
        if ($baris) {
            DB::table('pengajuan_izin')->where('id', $id)->delete();
            return redirect('izin')->with('flash_sukses', 'Pengajuan dibatalkan.');
        }
        return redirect('izin')->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah diproses.');
    }

    public function dokumen(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        $b = DB::table('pengajuan_izin')->where('id', $id)->where('status', 'Disetujui');
        $bolehLihatSemua = $u['posisi'] === 'Direktur' || $u['role'] === 'admin' || $u['posisi'] === 'HRD';
        if (! $bolehLihatSemua) {
            $b->where('user_id', $u['id']);
        }
        $iz = $b->first();
        if (! $iz) {
            return redirect('izin')->with('flash_gagal', 'Dokumen belum tersedia untuk pengajuan ini.');
        }
        $pemilik = $iz->user_id === $u['id'] ? $u
            : (array) DB::table('users')->where('id', $iz->user_id)->first();

        $tahap = DB::table('izin_persetujuan p')
            ->select('p.*', 'o.nama_lengkap AS oleh_nama')
            ->leftJoin('users o', 'o.id', '=', 'p.oleh_user_id')
            ->where('pengajuan_id', $id)->orderBy('tahap')->get()->all();
        $ttdOleh = $iz->ttd_oleh
            ? DB::table('users')->where('id', $iz->ttd_oleh)->first()
            : null;
        $bolehTtd = $u['posisi'] === 'Direktur' || $u['role'] === 'admin';

        return view('pegawai.dokumen_izin', [
            'iz' => $iz, 'u' => $pemilik, 'tahap' => $tahap, 'ttdOleh' => $ttdOleh,
            'bolehTtd' => $bolehTtd,
        ]);
    }

    public function tandaTangan(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u || ($u['posisi'] !== 'Direktur' && $u['role'] !== 'admin')) {
            return redirect('dashboard')
                ->with('flash_gagal', 'Hanya Direktur yang dapat membubuhkan tanda tangan digital.');
        }

        $iz = DB::table('pengajuan_izin')->where('id', $id)->where('status', 'Disetujui')->first();
        if (! $iz) {
            return redirect()->back()->with('flash_gagal', 'Pengajuan belum disetujui penuh / tidak ditemukan.');
        }

        DB::table('pengajuan_izin')->where('id', $id)->update([
            'ttd_digital' => 1, 'ttd_oleh' => $u['id'], 'ttd_waktu' => now(),
        ]);
        catat_aktivitas('Tanda Tangan Digital', 'Dokumen izin/cuti #' . $id . ' oleh ' . $u['nama_lengkap']);

        return redirect()->back()->with('flash_sukses', 'Dokumen telah ditandatangani secara elektronik.');
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
