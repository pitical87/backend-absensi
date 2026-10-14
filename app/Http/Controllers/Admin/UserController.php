<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\NoteDeleted;
use App\Models\User;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    private const PER_HALAMAN = 20;

    /** Tab menu user: kunci => label. */
    private const TABS = [
        'aktif' => 'User Aktif',
        'nonaktif' => 'User Non Aktif',
        'admin' => 'Administrator',
        'deletes' => 'Deletes',
    ];

    public function index(Request $request): View
    {
        $tab = $this->tab($request);

        return view('admin.users.index', $this->dataTabel($request, $tab) + [
            'judulHalaman' => 'Manajemen User',
            'menuAktif' => 'users',
            'tabs' => self::TABS,
            'tabAktif' => $tab,
            'jumlahTab' => $this->jumlahTab(),
        ]);
    }

    /** Endpoint asinkron: fragment tabel per tab (cari + pagination tanpa reload). */
    public function data(Request $request): JsonResponse
    {
        $tab = $this->tab($request);
        $data = $this->dataTabel($request, $tab);

        return response()->json([
            'sukses' => true,
            'tab' => $tab,
            'total' => $data['users']->total(),
            'dari' => $data['users']->firstItem(),
            'sampai' => $data['users']->lastItem(),
            'halaman' => $data['users']->currentPage(),
            'totalHal' => $data['users']->lastPage(),
            'tbody' => view('admin.users.rows', $data)->render(),
            'paginasi' => view('admin.users.paginasi', $data)->render(),
        ]);
    }

    private function dataTabel(Request $request, string $tab): array
    {
        return [
            'users' => $this->kueri($request, $tab)->paginate(self::PER_HALAMAN)->withQueryString(),
            'q' => trim((string) $request->get('q')),
            'tab' => $tab,
        ];
    }

    private function tab(Request $request): string
    {
        $tab = (string) $request->get('tab', 'aktif');

        return array_key_exists($tab, self::TABS) ? $tab : 'aktif';
    }

    /**
     * Query dasar daftar user. Dua subkueri menghitung "terakhir dilihat" dari
     * login web terakhir yang berhasil dan aktivitas terakhir aplikasi mobile.
     */
    private function kueri(Request $request, string $tab)
    {
        $b = User::query()
            ->select('users.*')
            ->selectSub(function ($q) {
                $q->from('login_attempts')->selectRaw('MAX(waktu)')
                    ->whereColumn('login_attempts.email', 'users.email')
                    ->where('sukses', 1);
            }, 'terakhir_login')
            ->selectSub(function ($q) {
                $q->from('api_tokens')->selectRaw('MAX(last_aktivitas)')
                    ->whereColumn('api_tokens.user_id', 'users.id');
            }, 'terakhir_mobile');

        if ($tab === 'deletes') {
            $b->onlyTrashed()
                ->addSelect(['catatan_hapus' => function ($q) {
                    $q->from('note_deleted')->select('catatan')
                        ->whereColumn('note_deleted.user_id', 'users.id')
                        ->latest('id')
                        ->limit(1);
                }])
                ->addSelect(['catatan_oleh' => function ($q) {
                    $q->from('note_deleted as nd')
                        ->leftJoin('users as adm', 'adm.id', '=', 'nd.dihapus_oleh')
                        ->select('adm.nama_lengkap')
                        ->whereColumn('nd.user_id', 'users.id')
                        ->latest('nd.id')
                        ->limit(1);
                }]);
        } elseif ($tab === 'admin') {
            $b->where('users.role', 'admin');
        } else {
            $b->where('users.role', 'pegawai')
                ->where('users.status', $tab === 'aktif' ? 'aktif' : 'nonaktif');
        }

        $q = trim((string) $request->get('q'));
        if ($q !== '') {
            $b->where(function ($w) use ($q) {
                $w->where('users.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%");
            });
        }

        if ($tab === 'deletes') {
            return $b->orderByDesc('users.deleted_at');
        }

        return $b->orderBy('users.nama_lengkap');
    }

    /** Jumlah user tiap tab untuk badge pada tombol tab. */
    private function jumlahTab(): array
    {
        return [
            'aktif' => User::where('role', 'pegawai')->where('status', 'aktif')->count(),
            'nonaktif' => User::where('role', 'pegawai')->where('status', 'nonaktif')->count(),
            'admin' => User::where('role', 'admin')->count(),
            'deletes' => User::onlyTrashed()->count(),
        ];
    }

    private function tabDari(User $user): string
    {
        if ($user->role === 'admin') {
            return 'admin';
        }

        return $user->status === 'aktif' ? 'aktif' : 'nonaktif';
    }

    public function form(int $id): View|RedirectResponse
    {
        $user = User::find($id);
        if (! $user) {
            return redirect('admin/users')->with('error', 'User tidak ditemukan.');
        }

        return view('admin.users.form', [
            'judulHalaman' => 'Ubah User',
            'menuAktif' => 'users',
            'edit' => $user,
            'tab' => $this->tabDari($user),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $user = User::find($id);
        if (! $user) {
            return redirect('admin/users')->with('error', 'User tidak ditemukan.');
        }

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc,dns', 'max:150', 'unique:users,email,'.$id],
            'status' => ['in:aktif,nonaktif'],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan user lain.',
        ]);

        $user->update($data);
        catat_aktivitas('Ubah User', $user->nama_lengkap.' ('.$user->email.')');

        return redirect('admin/users?tab='.$this->tabDari($user))
            ->with('success', 'Data user diperbarui.');
    }

    public function gantiPassword(Request $request, PasswordService $password): RedirectResponse
    {
        $id = (int) $request->input('id');
        $pass = (string) $request->input('password');
        $konf = (string) $request->input('password_konfirmasi');
        $tab = $this->tab($request);

        $user = User::find($id);
        if (! $user) {
            return back()->with('error', 'User tidak ditemukan.');
        }
        if (strlen($pass) < 6) {
            return back()->with('error', 'Password baru minimal 6 karakter.');
        }
        if ($pass !== $konf) {
            return back()->with('error', 'Konfirmasi password baru tidak cocok.');
        }

        $user->update(['password_hash' => Hash::make($pass)]);
        LoginAttempt::where('email', $user->email)->where('sukses', 0)->delete();
        $password->tandaiDiubah($user, 'oleh administrator');

        catat_aktivitas('Ganti Password User', 'Password '.$user->nama_lengkap.' ('.$user->email.') diganti oleh admin');

        return redirect('admin/users?tab='.$tab)
            ->with('success', 'Password '.$user->nama_lengkap.' berhasil diganti.');
    }

    public function status(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $tab = $this->tab($request);

        if ($id === (int) session('uid')) {
            return redirect('admin/users?tab='.$tab)
                ->with('error', 'Anda tidak dapat mengubah status akun sendiri.');
        }

        $user = User::find($id);
        if ($user) {
            $baru = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
            $user->update(['status' => $baru]);
            catat_aktivitas('Status User', $user->nama_lengkap.' → '.$baru);
        }

        return redirect('admin/users?tab='.$tab)->with('success', 'Status user diperbarui.');
    }

    public function hapus(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $tab = $this->tab($request);

        if ($id === (int) session('uid')) {
            return redirect('admin/users?tab='.$tab)
                ->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user = User::find($id);
        if (! $user) {
            return redirect('admin/users?tab='.$tab)
                ->with('error', 'User tidak ditemukan.');
        }

        $catatan = trim((string) $request->input('catatan'));
        if ($catatan === '') {
            return redirect('admin/users?tab='.$tab)
                ->with('error', 'Catatan penghapusan wajib diisi (contoh: pegawai keluar, meninggal).');
        }

        NoteDeleted::catat($user, $catatan);
        $user->delete();
        catat_aktivitas('Hapus User', $user->nama_lengkap.' ('.$user->email.') dipindah ke Deletes — '.$catatan);

        return redirect('admin/users?tab='.$tab)
            ->with('success', 'User dipindahkan ke Deletes. Riwayat absen dan datanya tetap tersimpan.');
    }

    /** Pulihkan user yang dihapus (soft delete). */
    public function pulihkan(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $user = User::onlyTrashed()->find($id);

        if (! $user) {
            return redirect('admin/users?tab=deletes')
                ->with('error', 'User tidak ditemukan di Deletes.');
        }

        $user->restore();
        catat_aktivitas('Pulihkan User', $user->nama_lengkap.' ('.$user->email.') dipulihkan');

        return redirect('admin/users?tab='.$this->tabDari($user))
            ->with('success', 'User '.$user->nama_lengkap.' berhasil dipulihkan.');
    }

    /** Hapus permanen user beserta seluruh data terkait (tidak dapat dikembalikan). */
    public function hapusPermanen(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id');
        $user = User::onlyTrashed()->find($id);

        if (! $user) {
            return redirect('admin/users?tab=deletes')
                ->with('error', 'User tidak ditemukan di Deletes.');
        }

        $nama = $user->nama_lengkap;
        $email = $user->email;
        $user->forceDelete();
        catat_aktivitas('Hapus Permanen User', $nama.' ('.$email.') beserta seluruh data terkait dihapus permanen');

        return redirect('admin/users?tab=deletes')
            ->with('success', 'User '.$nama.' dihapus permanen beserta seluruh datanya.');
    }
}
