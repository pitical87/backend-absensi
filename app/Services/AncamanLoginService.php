<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi percobaan login gagal menjadi daftar ancaman untuk panel admin.
 *
 * Unit analisis adalah email bila alamatnya diketahui, karena itu yang
 * diserang. Kegagalan yang tidak menyertakan email (misalnya token Google
 * tidak valid) dikelompokkan per IP; untuk grup ini sinyal keberagaman yang
 * dipakai adalah banyaknya email berbeda yang dicoba dari IP tersebut, yang
 * justru menandakan percobaan tebak-menebak kredensial.
 *
 * Skala severity:
 *   low         gagal < 2
 *   mencurigakan gagal 2-5
 *   medium      gagal > 5 dan hanya satu IP/sumber target
 *   danger      gagal > 5 dan target/IP beragam
 *   danger2     gagal > 5, target beragam, dan perangkat berbeda
 */
class AncamanLoginService
{
    /** Jendela default untuk menghitung severity, dalam jam. */
    public const JENDELA_JAM = 24;

    /** Opsi jendela waktu yang ditawarkan di halaman tracker. */
    public const OPSI_JENDELA = [
        24 => '24 Jam',
        168 => '7 Hari',
        720 => '30 Hari',
    ];

    private const KUNCI_BADGE = 'ancaman-login.badge';

    /** Urutan severity, makin besar makin serius. */
    public const LEVEL = [
        'low' => 1,
        'mencurigakan' => 2,
        'medium' => 3,
        'danger' => 4,
        'danger2' => 5,
    ];

    private const LABEL = [
        'low' => 'Low',
        'mencurigakan' => 'Mencurigakan',
        'medium' => 'Medium',
        'danger' => 'Danger',
        'danger2' => 'Danger 2',
    ];

    /**
     * Tentukan level ancaman dari angka agregat satu grup.
     *
     * @param  int  $gagal  jumlah percobaan gagal
     * @param  int  $beragam  jumlah IP atau email target berbeda (>1 = beragam)
     * @param  int  $perangkat  jumlah user agent berbeda
     */
    public function level(int $gagal, int $beragam, int $perangkat): string
    {
        if ($gagal < 2) {
            return 'low';
        }
        if ($gagal <= 5) {
            return 'mencurigakan';
        }

        if ($beragam < 2) {
            return 'medium';
        }

        return $perangkat > 1 ? 'danger2' : 'danger';
    }

    public function label(string $level): string
    {
        return self::LABEL[$level] ?? $level;
    }

    /**
     * Kelompokkan seluruh percobaan gagal dalam jendela waktu, sudah diurutkan
     * dari yang paling serius.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function grup(int $jam = self::JENDELA_JAM, ?string $sumber = null)
    {
        $sejak = now()->subHours($jam);

        // Percobaan terakhir tiap kelompok, untuk mengisi kolom IP Terakhir dan
        // Perangkat pada tabel tracker.
        $terakhirEmail = $this->terakhirPerKelompok('email', $jam, $sumber);
        $terakhirIp = $this->terakhirPerKelompok('ip', $jam, $sumber);

        $grup = collect();

        // Grup per email: satu akun yang diserang dari beberapa arah.
        $perEmail = DB::table('login_attempts')
            ->selectRaw('email, COUNT(*) as gagal, COUNT(DISTINCT ip) as jml_ip, COUNT(DISTINCT user_agent) as jml_perangkat, MAX(waktu) as terakhir, GROUP_CONCAT(DISTINCT ip) as daftar_ip')
            ->where('sukses', 0)->where('waktu', '>=', $sejak)->whereNotNull('email')->where('email', '<>', '')
            ->when($sumber !== null && $sumber !== '', fn ($q) => $q->where('sumber', $sumber))
            ->groupBy('email')
            ->get();

        foreach ($perEmail as $row) {
            $email = (string) $row->email;
            $akhir = $terakhirEmail->get($email);

            $grup->push($this->bentukGrup([
                'kunci' => 'email',
                'email' => $email,
                'ip' => $akhir?->ip,
                'gagal' => (int) $row->gagal,
                'jml_ip' => (int) $row->jml_ip,
                'jml_email' => 1,
                'jml_perangkat' => (int) $row->jml_perangkat,
                'perangkat' => ApiToken::namaPerangkatDariUserAgent($akhir?->user_agent),
                'terakhir' => (string) $row->terakhir,
                'daftar_ip' => $row->daftar_ip ? explode(',', (string) $row->daftar_ip) : [],
            ]));
        }

        // Grup per IP: kegagalan tanpa email (token Google tidak valid, dsb).
        $perIp = DB::table('login_attempts')
            ->selectRaw('ip, COUNT(*) as gagal, COUNT(DISTINCT email) as jml_email, COUNT(DISTINCT user_agent) as jml_perangkat, MAX(waktu) as terakhir')
            ->where('sukses', 0)->where('waktu', '>=', $sejak)
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', '=', ''))
            ->when($sumber !== null && $sumber !== '', fn ($q) => $q->where('sumber', $sumber))
            ->groupBy('ip')
            ->get();

        foreach ($perIp as $row) {
            $ip = (string) $row->ip;
            $akhir = $terakhirIp->get($ip);

            $grup->push($this->bentukGrup([
                'kunci' => 'ip',
                'email' => null,
                'ip' => $ip,
                'gagal' => (int) $row->gagal,
                'jml_ip' => 1,
                'jml_email' => (int) $row->jml_email,
                'jml_perangkat' => (int) $row->jml_perangkat,
                'perangkat' => ApiToken::namaPerangkatDariUserAgent($akhir?->user_agent),
                'terakhir' => (string) $row->terakhir,
                'daftar_ip' => [$ip],
            ]));
        }

        return $this->lengkapiNama($grup->sortByDesc(function ($g) {
            // Kunci tunggal: level lebih didahulukan, lalu jumlah kegagalan.
            return self::LEVEL[$g['level']] * 1000000 + $g['gagal'];
        })->values());
    }

    /**
     * Percobaan gagal paling baru untuk tiap nilai $kolom ('email' atau 'ip'),
     * lengkap dengan IP dan user agent-nya.
     *
     * Bentuknya join ke MAX(waktu) per kelompok supaya tetap satu query dan tidak
     * bergantung fungsi khusus MySQL seperti GROUP_CONCAT yang diurutkan. Bila
     * dua percobaan tercatat pada detik yang sama, yang dipakai tetap satu per
     * kelompok.
     *
     * @return Collection<string, object>
     */
    private function terakhirPerKelompok(string $kolom, int $jam, ?string $sumber): Collection
    {
        $sejak = now()->subHours($jam);

        $maks = DB::table('login_attempts')
            ->select($kolom, DB::raw('MAX(waktu) as waktu'))
            ->where('sukses', 0)->where('waktu', '>=', $sejak)
            ->when($kolom === 'email',
                fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''),
                fn ($q) => $q->where(fn ($q) => $q->whereNull('email')->orWhere('email', '=', '')))
            ->when($sumber !== null && $sumber !== '', fn ($q) => $q->where('sumber', $sumber))
            ->groupBy($kolom);

        return DB::table('login_attempts as t')
            ->joinSub($maks, 'm', fn ($j) => $j
                ->on('t.'.$kolom, '=', 'm.'.$kolom)
                ->on('t.waktu', '=', 'm.waktu'))
            ->where('t.sukses', 0)
            ->orderBy('t.waktu', 'desc')
            ->get(['t.'.$kolom.' as kunci', 't.ip', 't.user_agent'])
            ->keyBy('kunci');
    }

    /**
     * Ringkasan angka untuk kartu statistik dan badge.
     *
     * @return array<string, int>
     */
    public function ringkasan(int $jam = self::JENDELA_JAM): array
    {
        $grup = $this->grup($jam);

        $r = [
            'totalGagal' => (int) $grup->sum('gagal'),
            'totalGrup' => $grup->count(),
            'jumlahAncaman' => 0,
        ];
        foreach (array_keys(self::LEVEL) as $level) {
            $r['jumlah_'.$level] = $grup->where('level', $level)->count();
        }
        $r['jumlahAncaman'] = $r['jumlah_medium'] + $r['jumlah_danger'] + $r['jumlah_danger2'];

        return $r;
    }

    /**
     * Jumlah ancaman untuk badge sidebar dan lonceng navbar. Di-cache
     * singkat supaya agregasi tidak dijalankan di setiap halaman admin.
     */
    public function jumlahBadge(): int
    {
        return (int) Cache::remember(self::KUNCI_BADGE, now()->addSeconds(60), fn () => $this->ringkasan()['jumlahAncaman']);
    }

    public function bersihkanBadge(): void
    {
        Cache::forget(self::KUNCI_BADGE);
    }

    /**
     * Daftar ancaman terpaginasi dengan pencarian dan filter.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function daftar(array $filter = []): LengthAwarePaginator
    {
        $jam = max(1, (int) ($filter['jam'] ?? self::JENDELA_JAM));
        $sumber = trim((string) ($filter['sumber'] ?? '')) ?: null;
        $level = trim((string) ($filter['level'] ?? '')) ?: null;
        $q = trim((string) ($filter['q'] ?? ''));
        $perHal = max(1, (int) ($filter['perHal'] ?? 15));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));

        $grup = $this->grup($jam, $sumber);

        if ($level !== null && isset(self::LEVEL[$level])) {
            $grup = $grup->where('level', $level)->values();
        }
        if ($q !== '') {
            $cari = mb_strtolower($q);
            $grup = $grup->filter(function ($g) use ($cari) {
                if (str_contains(mb_strtolower((string) $g['email']), $cari)) {
                    return true;
                }
                foreach ($g['daftar_ip'] as $ip) {
                    if (str_contains((string) $ip, $cari)) {
                        return true;
                    }
                }

                return str_contains((string) $g['ip'], $cari);
            })->values();
        }

        return new LengthAwarePaginator(
            $grup->forPage($halaman, $perHal)->values(),
            $grup->count(),
            $perHal,
            $halaman,
            ['path' => url('admin/login-gagal')]
        );
    }

    /**
     * Blokir atau buka blokir sebuah akun,(matikan sesi aktifnya, dan bersihkan
     * penghitung gagalnya. Memakai status kepegawaian yang sudah ada, karena
     * status tersebut sudah ditolak di ketiga jalur login (web, API, Google).
     */
    public function alihkanBlokir(int $userId, int $aktorId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return ['sukses' => false, 'pesan' => 'Akun tidak ditemukan.'];
        }
        if ($user->id === $aktorId) {
            return ['sukses' => false, 'pesan' => 'Anda tidak dapat memblokir akun sendiri.'];
        }
        if ($user->role === 'admin') {
            return ['sukses' => false, 'pesan' => 'Akun administrator tidak dapat diblokir dari halaman ini.'];
        }

        $baru = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->update(['status' => $baru]);

        if ($baru === 'nonaktif') {
            // Cabut sesi aktif supaya akses yang sedang berjalan langsung putus.
            ApiToken::where('user_id', $user->id)->delete();
        }
        LoginAttempt::where('email', $user->email)->where('sukses', 0)->delete();

        $this->bersihkanBadge();

        return [
            'sukses' => true,
            'pesan' => ($baru === 'nonaktif' ? 'Akun ' : 'Blokir akun ')
                .$user->nama_lengkap.' '.($baru === 'nonaktif' ? 'dibuka.' : 'berhasil dinonaktifkan.'),
            'status' => $baru,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function bentukGrup(array $data): array
    {
        // Untuk grup email, keberagaman diukur dari jumlah IP. Untuk grup IP,
        // dari jumlah email target yang dicoba.
        $beragam = $data['kunci'] === 'email' ? $data['jml_ip'] : $data['jml_email'];

        $data['level'] = $this->level($data['gagal'], $beragam, $data['jml_perangkat']);
        $data['nama'] = null;
        $data['terblokir'] = false;

        return $data;
    }

    /**
     * Sambungkan nama pegawai dan status blokir ke grup yang berbasis email.
     *
     * @param  Collection<int, array<string, mixed>>  $grup
     * @return Collection<int, array<string, mixed>>
     */
    private function lengkapiNama($grup)
    {
        $emails = $grup->pluck('email')->filter()->unique()->values();
        if ($emails->isEmpty()) {
            return $grup;
        }

        $pengguna = User::whereIn('email', $emails)
            ->get(['id', 'nama_lengkap', 'email', 'status', 'role'])
            ->keyBy('email');

        return $grup->map(function ($g) use ($pengguna) {
            if (! empty($g['email']) && isset($pengguna[$g['email']])) {
                $u = $pengguna[$g['email']];
                $g['nama'] = $u->nama_lengkap;
                $g['terblokir'] = $u->status !== 'aktif';
                $g['user_id'] = $u->id;
            }

            return $g;
        });
    }
}
