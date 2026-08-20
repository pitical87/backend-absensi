<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjukanIzinRequest;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;
use App\Services\CutiService;
use Illuminate\Support\Facades\DB;

class IzinController extends Controller
{
    use Concerns\HasPenggunaAktif;

    public const JENIS = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
    public const BERJENJANG = ['Izin', 'Cuti'];

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $riwayat = Izin::with('diprosesOleh:id,nama_lengkap')
            ->where('user_id', $u['id'])
            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get()
            ->all();

        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($riwayat,
            static fn ($r) => in_array($r->jenis, self::BERJENJANG, true)), 'id');
        if ($idBerjenjang) {
            $persetujuan = IzinPersetujuan::with('user:id,nama_lengkap')
                ->whereIn('pengajuan_id', $idBerjenjang)
                ->orderBy('tahap')
                ->get();
            foreach ($persetujuan as $p) {
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

    public function ajukan(AjukanIzinRequest $request)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }

        $validated = $request->validated();
        $jenis     = $validated['jenis'];
        $jenisCuti = $validated['jenis_cuti'] ?? null;
        $mulai     = $validated['tanggal_mulai'];
        $selesai   = $validated['tanggal_selesai'] ?? $mulai;
        $alamat    = $validated['alamat_izin'] ?? null;
        $ket       = $validated['keterangan'];
        $berjenjang = in_array($jenis, self::BERJENJANG, true);

        $lampiran = null;
        if ($request->hasFile('lampiran')) {
            $berkas = $request->file('lampiran');
            $eks = strtolower($berkas->getClientOriginalExtension() ?: '');
            $dir = 'izin/' . now()->format('Ym');
            $nama = $u['id'] . '_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . ($eks === 'jpeg' ? 'jpg' : $eks);
            $lampiran = $dir . '/' . $nama;
            $berkas->storeAs($dir, $nama, 'public');
        }

        $data = [
            'user_id'         => $u['id'],
            'jenis'           => $jenis,
            'jenis_cuti'      => $jenis === 'Cuti' ? $jenisCuti : null,
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'lama_hari'       => $request->input('lama_hari'),
            'alamat_izin'     => $alamat,
            'keterangan'      => $ket,
            'lampiran'        => $lampiran,
            'status'          => 'Menunggu',
            'tahap_aktif'     => 0,
            'created_at'      => now(),
        ];

        if ($berjenjang) {
            $pesan = DB::transaction(function () use ($data, $u, $jenis, $mulai, $selesai, $jenisCuti) {
                $izin = Izin::create($data);
                [$tahapAktif, $statusAwal] = app(AlurIzinService::class)->mulai($izin->id, $u);

                $update = ['tahap_aktif' => $tahapAktif, 'status' => $statusAwal];
                if ($statusAwal === 'Disetujui') {
                    $update['processed_at'] = now();
                    $update['nomor_surat'] = sprintf('800/%03d/RSUD-MRK/%02d/%d',
                        Izin::whereNotNull('nomor_surat')
                            ->whereMonth('created_at', now()->format('n'))->whereYear('created_at', now()->format('Y'))
                            ->count() + 1, now()->format('n'), now()->format('Y'));
                    $update['kode_verifikasi'] = strtoupper(bin2hex(random_bytes(5)));
                }
                $izin->update($update);

                catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' — ' . ($jenisCuti ?: $jenis)
                    . ' (' . $mulai . ' s.d. ' . $selesai . ", {$data['lama_hari']} hr kerja)");

                return $statusAwal === 'Disetujui'
                    ? "Pengajuan {$jenis} langsung disetujui (posisi Anda berada di puncak alur persetujuan)."
                    : 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan '
                        . label_tahap_izin($tahapAktif) . '.';
            });
        } else {
            Izin::create($data);
            catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' mengajukan ' . $jenis
                . ' (' . $mulai . ' s.d. ' . $selesai . ')');
            $pesan = 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan admin.';
        }

        return redirect('izin')->with('success', $pesan);
    }

    public function batal(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        $baris = Izin::where('id', $id)->where('user_id', $u['id'])->where('status', 'Menunggu')->first();
        if ($baris) {
            $baris->delete();
            return redirect('izin')->with('success', 'Pengajuan dibatalkan.');
        }
        return redirect('izin')->with('error', 'Pengajuan tidak ditemukan atau sudah diproses.');
    }

    public function dokumen(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        $b = Izin::where('id', $id)->where('status', 'Disetujui');
        $bolehLihatSemua = $u['posisi'] === 'Direktur' || $u['role'] === 'admin' || $u['posisi'] === 'HRD';
        if (! $bolehLihatSemua) {
            $b->where('user_id', $u['id']);
        }
        $iz = $b->first();
        if (! $iz) {
            return redirect('izin')->with('error', 'Dokumen belum tersedia untuk pengajuan ini.');
        }
        $pemilik = $iz->user_id === $u['id'] ? $u
            : (array) User::where('id', $iz->user_id)->first();

        $tahap = IzinPersetujuan::with('user:id,nama_lengkap')
            ->where('pengajuan_id', $id)
            ->orderBy('tahap')
            ->get()
            ->all();
        $ttdOleh = $iz->ttd_oleh
            ? User::where('id', $iz->ttd_oleh)->first()
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
                ->with('error', 'Hanya Direktur yang dapat membubuhkan tanda tangan digital.');
        }

        $iz = Izin::where('id', $id)->where('status', 'Disetujui')->first();
        if (! $iz) {
            return redirect()->back()->with('error', 'Pengajuan belum disetujui penuh / tidak ditemukan.');
        }

        $iz->update([
            'ttd_digital' => 1, 'ttd_oleh' => $u['id'], 'ttd_waktu' => now(),
        ]);
        catat_aktivitas('Tanda Tangan Digital', 'Dokumen izin/cuti #' . $id . ' oleh ' . $u['nama_lengkap']);

        return redirect()->back()->with('success', 'Dokumen telah ditandatangani secara elektronik.');
    }
}
