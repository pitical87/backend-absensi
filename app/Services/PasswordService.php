<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Jejak dan pengecekan status password setiap pengguna.
 *
 * Satu-satunya sumber kebenaran waktu password terakhir diganti adalah kolom
 * users.password_changed_at. Kolom itu sengaja dikosongkan (null) saat akun
 * dibuat, jadi null berarti "belum pernah mengganti password sejak akun dibuat"
 * dan bukan "tidak diketahui" — inilah yang membuat pengecekan di panel admin
 * bisa membedakan akun berisiko dari akun yang sudah aman.
 */
class PasswordService
{
    /** Usia password lebih dari ini perlu diperbarui. */
    public const AMBANG_PERLU_HARI = 90;

    /** Usia password lebih dari ini dianggap lama dan berisiko. */
    public const AMBANG_LAMA_HARI = 180;

    /** Jendela cooldown pengingat, supaya pengguna tidak dikirimi terus. */
    public const COOLDOWN_HARI = 30;

    private const LABEL = [
        'baru' => 'Aman',
        'perlu' => 'Perlu diperbarui',
        'lama' => 'Terlalu lama',
        'belum' => 'Belum pernah ganti',
    ];

    /**
     * Status password seorang pengguna.
     *
     * @return array{pernah: bool, terakhir: ?string, umur_hari: ?int, level: string, label: string, perlu_ganti: bool}
     */
    public function status(User $user): array
    {
        $terakhir = $user->password_changed_at;
        $pernah = $terakhir !== null;

        $umur = $pernah
            ? (int) $terakhir->copy()->startOfDay()->diffInDays(now()->startOfDay())
            : null;

        $level = match (true) {
            ! $pernah => 'belum',
            $umur >= self::AMBANG_LAMA_HARI => 'lama',
            $umur >= self::AMBANG_PERLU_HARI => 'perlu',
            default => 'baru',
        };

        return [
            'pernah' => $pernah,
            'terakhir' => $pernah ? $terakhir->toIso8601String() : null,
            'umur_hari' => $umur,
            'level' => $level,
            'label' => self::LABEL[$level],
            'perlu_ganti' => $level !== 'baru',
        ];
    }

    public function perluGanti(User $user): bool
    {
        return $this->status($user)['perlu_ganti'];
    }

    /**
     * Catat waktu password terakhir diganti dan beri tahu pemiliknya, supaya
     * perubahan password di luar Ticket (reset, ganti oleh admin) tetap terlihat.
     *
     * Dipanggil dari semua jalur penulisan password_hash selain pembuatan akun.
     */
    public function tandaiDiubah(User $user, ?string $oleh = null, bool $kirimNotifikasi = true): void
    {
        $user->forceFill(['password_changed_at' => now()])->save();

        if (! $kirimNotifikasi) {
            return;
        }

        $oleh = $oleh ? ' ('.$oleh.')' : '';

        buat_notifikasi(
            (int) $user->id,
            'Password akun Anda baru saja diperbarui'.$oleh.'. '
            .'Bila Anda tidak melakukan ini, segera hubungi administrator dan ganti password Anda kembali.',
            'success',
            null,
            'password',
        );
    }

    /**
     * Angka ringkas untuk kartu statistik di panel admin.
     *
     * @return array{total: int, belum: int, perlu: int, lama: int, aman: int, perluPerhatian: int}
     */
    public function ringkasan(): array
    {
        $total = (int) User::where('role', '!=', 'admin')->count();

        $belum = (int) User::where('role', '!=', 'admin')->whereNull('password_changed_at')->count();
        $lama = (int) User::where('role', '!=', 'admin')
            ->where('password_changed_at', '<=', now()->subDays(self::AMBANG_LAMA_HARI))->count();
        $perlu = (int) User::where('role', '!=', 'admin')
            ->whereNotNull('password_changed_at')
            ->where('password_changed_at', '>', now()->subDays(self::AMBANG_LAMA_HARI))
            ->where('password_changed_at', '<=', now()->subDays(self::AMBANG_PERLU_HARI))->count();

        $aman = max(0, $total - $belum - $lama - $perlu);

        return [
            'total' => $total,
            'belum' => $belum,
            'perlu' => $perlu,
            'lama' => $lama,
            'aman' => $aman,
            'perluPerhatian' => $belum + $lama + $perlu,
        ];
    }

    /**
     * Filter status password untuk daftar pegawai di panel admin.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function terapkanFilter($query, ?string $filter)
    {
        return match ($filter) {
            'belum' => $query->whereNull('users.password_changed_at'),
            'perlu' => $query->whereNotNull('users.password_changed_at')
                ->where('users.password_changed_at', '<=', now()->subDays(self::AMBANG_PERLU_HARI))
                ->where('users.password_changed_at', '>', now()->subDays(self::AMBANG_LAMA_HARI)),
            'lama' => $query->where('users.password_changed_at', '<=', now()->subDays(self::AMBANG_LAMA_HARI)),
            'aman' => $query->whereNotNull('users.password_changed_at')
                ->where('users.password_changed_at', '>', now()->subDays(self::AMBANG_PERLU_HARI)),
            default => $query,
        };
    }

    /** Opsi filter untuk dropdown di panel admin. */
    public function opsiFilter(): array
    {
        return [
            '' => 'Semua Status Password',
            'belum' => 'Belum pernah ganti',
            'perlu' => 'Perlu diperbarui ('.self::AMBANG_PERLU_HARI.'-'.self::AMBANG_LAMA_HARI.' hari)',
            'lama' => 'Terlalu lama ('.'>'.self::AMBANG_LAMA_HARI.' hari)',
            'aman' => 'Aman ('.'<'.self::AMBANG_PERLU_HARI.' hari)',
        ];
    }

    /**
     * Kirim notifikasi pengingat ganti password ke pengguna yang passwordnya
     * belum pernah diganti atau sudah lama.
     *
     * Pengguna yang sudah dikirimi pengingat dalam masa cooldown dilewati agar
     * kotak masuknya tidak penuh pesan serupa.
     *
     * @return array{sukses: bool, pesan: string, terkirim: int, dilewati: int}
     */
    public function ingatkan(): array
    {
        $kandidat = User::where('role', '!=', 'admin')
            ->where(function ($q) {
                $q->whereNull('password_changed_at')
                    ->orWhere('password_changed_at', '<=', now()->subDays(self::AMBANG_PERLU_HARI));
            })
            ->get(['id', 'nama_lengkap', 'email', 'status', 'password_changed_at', 'created_at']);

        $terkirim = 0;
        $dilewati = 0;

        foreach ($kandidat as $user) {
            if ($this->sudahDingatkan($user->id)) {
                $dilewati++;

                continue;
            }

            $status = $this->status($user);

            $isi = $status['pernah']
                ? 'Password akun Anda terakhir diganti pada '.tgl_id($status['terakhir']).' ('.$status['umur_hari'].' hari lalu). '
                    .' Demi keamanan, ganti password Anda bila sudah melewati '.self::AMBANG_PERLU_HARI.' hari.'
                : 'Password akun Anda belum pernah diganti sejak akun ini dibuat '
                    .(($user->created_at) ? 'pada '.tgl_id($user->created_at) : '').'. '
                    .'Ganti password Anda sekarang untuk meningkatkan keamanan akun.';

            if ($user->status !== 'aktif') {
                $isi .= ' Akun Anda sedang berstatus nonaktif.';
            }

            if (buat_notifikasi((int) $user->id, $isi, 'warning', null, 'password') !== null) {
                $terkirim++;
            }
        }

        catat_aktivitas('Pengingat Password',
            'Pengingat ganti password dikirim ke '.$terkirim.' pengguna'
            .($dilewati > 0 ? ', '.$dilewati.' pengguna dilewati karena sudah dikirimi dalam '
                .self::COOLDOWN_HARI.' hari terakhir' : '').'.');

        if ($terkirim === 0) {
            return [
                'sukses' => true,
                'pesan' => $dilewati > 0
                    ? 'Semua pengguna yang perlu diingatkan sudah dikirimi pengingat dalam '
                        .self::COOLDOWN_HARI.' hari terakhir ('.$dilewati.' pengguna).'
                    : 'Tidak ada pengguna yang perlu diingatkan.',
                'terkirim' => 0,
                'dilewati' => $dilewati,
            ];
        }

        return [
            'sukses' => true,
            'pesan' => 'Pengingat ganti password dikirim ke '.$terkirim.' pengguna.'
                .($dilewati > 0 ? ' '.$dilewati.' pengguna dilewati karena sudah dikirimi baru-baru ini.' : ''),
            'terkirim' => $terkirim,
            'dilewati' => $dilewati,
        ];
    }

    /** Apakah pengguna sudah dikirimi pengingat password dalam masa cooldown? */
    public function sudahDingatkan(int $userId): bool
    {
        return Notifikasi::where('user_id', $userId)
            ->where('kategori', 'password')
            ->where('created_at', '>=', now()->subDays(self::COOLDOWN_HARI))
            ->exists();
    }
}
