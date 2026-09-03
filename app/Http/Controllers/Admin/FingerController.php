<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\FingerPegawai;
use App\Models\User;
use App\Services\FingerImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FingerController extends Controller
{
    public function index(Request $request)
    {
        $mapping = FingerPegawai::with('user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id')
            ->orderBy('id')->get();

        $pegawaiList = User::where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);

        $belumDipetakan = FingerPegawai::pluck('user_id');

        // Preview data hasil impor FingerSpot (bulan + tahun).
        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) now()->month;
        }
        if ($tahun < 2000 || $tahun > now()->year + 1) {
            $tahun = (int) now()->year;
        }

        $rabuAbsensi = Absensi::with('user:id,nama_lengkap,nip')
            ->where('sumber', 'finger')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('admin.finger.index', [
            'judulHalaman' => 'Absen FingerSpot',
            'menuAktif' => 'finger',
            'mapping' => $mapping,
            'pegawaiList' => $pegawaiList,
            'pegawaiTanpaMapping' => $pegawaiList->whereNotIn('id', $belumDipetakan),
            'preview' => $rabuAbsensi,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'ipMesin' => (string) pengaturan('finger_ip', ''),
            'portMesin' => (string) pengaturan('finger_port', ''),
        ]);
    }

    public function simpanSetting(Request $request)
    {
        $request->validate([
            'ip' => ['required', 'ip'],
            'port' => ['required', 'numeric', 'min:1', 'max:65535'],
        ], [], [
            'ip' => 'IP Mesin',
            'port' => 'Port',
        ]);

        simpan_pengaturan('finger_ip', trim((string) $request->input('ip')));
        simpan_pengaturan('finger_port', (string) (int) $request->input('port'));
        catat_aktivitas('Pengaturan FingerSpot', 'IP/port mesin diperbarui');

        return redirect()->route('admin.finger', ['tab' => 'pengaturan'])
            ->with('success', 'Pengaturan mesin FingerSpot disimpan.');
    }

    public function simpanMapping(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'finger_id' => 'required|string|max:50',
        ], [], [
            'user_id' => 'Pegawai',
            'finger_id' => 'ID Finger',
        ]);

        $userId = (int) $request->input('user_id');
        $fingerId = trim((string) $request->input('finger_id'));
        $id = (int) $request->input('id');

        $user = User::find($userId);
        if (! $user || $user->role === 'admin') {
            return redirect()->back()->with('error', 'Pegawai tidak ditemukan.');
        }

        // cegah finger_id dipakai dua user
        $bentrok = FingerPegawai::where('finger_id', $fingerId)
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();
        if ($bentrok) {
            return redirect()->back()->with('error', 'ID Finger "'.$fingerId.'" sudah dipetakan ke pegawai lain.');
        }

        $data = ['user_id' => $userId, 'finger_id' => $fingerId];

        if ($id) {
            $rec = FingerPegawai::find($id);
            if (! $rec) {
                return redirect()->back()->with('error', 'Mapping tidak ditemukan.');
            }
            $rec->fill($data)->save();
            $pesan = 'Mapping ID Finger diperbarui.';
        } else {
            // satu user hanya satu finger_id
            if (FingerPegawai::where('user_id', $userId)->exists()) {
                return redirect()->back()->with('error',
                    $user->nama_lengkap.' sudah memiliki ID Finger. Gunakan mode ubah untuk menggantinya.');
            }
            $rec = FingerPegawai::create($data + ['created_at' => now()]);
            $pesan = 'Mapping ID Finger untuk '.$user->nama_lengkap.' ditambahkan.';
        }

        catat_aktivitas('Mapping FingerSpot', $user->nama_lengkap.' → ID '.$rec->finger_id);

        return redirect()->back()->with('success', $pesan);
    }

    public function hapusMapping(Request $request)
    {
        $rec = FingerPegawai::find((int) $request->input('id'));
        if ($rec) {
            $nama = (string) User::where('id', $rec->user_id)->value('nama_lengkap');
            $rec->delete();
            catat_aktivitas('Hapus Mapping FingerSpot', ($nama !== '' ? $nama.' → ' : '').$rec->finger_id);
        }

        return redirect()->back()->with('success', 'Mapping ID Finger dihapus.');
    }

    public function imporCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ], [], ['file' => 'File CSV']);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $service = app(FingerImportService::class);
        $scan = $service->parseCsv($path);
        if ($scan === []) {
            return redirect()->route('admin.finger', ['tab' => 'csv'])->with('error',
                'Format tidak dikenali. Pastikan ada kolom ID finger, tanggal, dan jam.');
        }

        $hasil = $service->impor($scan);
        catat_aktivitas('Impor Absen FingerSpot (CSV)', $hasil['ditambah'].' baru, '.$hasil['diperbarui'].' diperbarui, '.$hasil['dilewati'].' dilewati');

        return redirect()->route('admin.finger', ['tab' => 'csv'])->with('finger_hasil', $hasil);
    }

    public function ambilDariMesin(Request $request)
    {
        $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ], [], [
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_selesai' => 'Tanggal Selesai',
        ]);

        $ip = trim((string) $request->input('ip', (string) pengaturan('finger_ip', '')));
        $port = trim((string) $request->input('port', (string) pengaturan('finger_port', '')));
        $url = trim((string) $request->input('url'));

        if ($url === '') {
            if ($ip === '' || $port === '') {
                return redirect()->route('admin.finger', ['tab' => 'mesin'])->with('error',
                    'Alamat mesin belum diatur. Isi IP dan Port pada tab Pengaturan terlebih dahulu.');
            }
            $url = 'http://'.$ip.':'.$port.'/GetTransactionData';
        }

        $mulai = (string) $request->input('tanggal_mulai');
        $selesai = (string) $request->input('tanggal_selesai');

        $service = app(FingerImportService::class);
        $scan = $service->ambilDariMesin($url, $mulai, $selesai);

        if ($scan === []) {
            return redirect()->route('admin.finger', ['tab' => 'mesin'])->with('error',
                'Tidak ada data yang didapat dari mesin. Periksa IP/port, jarak tanggal, atau koneksi mesin.');
        }

        $hasil = $service->impor($scan);
        catat_aktivitas('Impor Absen FingerSpot (Mesin)', $url.' → '.$hasil['ditambah'].' baru, '.$hasil['diperbarui'].' diperbarui');

        return redirect()->route('admin.finger', ['tab' => 'mesin'])->with('finger_hasil', $hasil)
            ->withInput(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
    }
}
