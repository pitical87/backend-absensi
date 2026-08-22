<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class SimrsService
{
    private ?PDO $pdo = null;

    public function terhubung(): bool
    {
        try {
            $this->pdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Cek apakah simrs_user_id dari tabel mapping cocok dengan NIK
     * pegawai pada database SIMRS. Jika cocok, kembalikan nik dan nama.
     *
     * @return array{sukses: bool, pesan?: string, data?: ?array}
     */
    public function cekMapping(?string $simrsUserId): array
    {
        $simrsUserId = trim((string) $simrsUserId);
        if ($simrsUserId === '') {
            return ['sukses' => true, 'data' => null];
        }

        try {
            $stmt = $this->pdo()->prepare(
                'SELECT nik, nama
                 FROM pegawai
                 WHERE nik = :nik
                 LIMIT 1'
            );

            $stmt->bindValue(':nik', $simrsUserId);
            $stmt->execute();

            return [
                'sukses' => true,
                'data' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null,
            ];
        } catch (Throwable $e) {
            Log::warning('Cek SIMRS gagal: '.$e->getMessage());

            return ['sukses' => false, 'pesan' => 'Gagal terhubung ke database SIMRS. Periksa konfigurasi koneksi.'];
        }
    }

    /**
     * Ambil daftar pegawai (nik dan nama) dari database SIMRS.
     * Dapat dicari berdasarkan nik/nama dan dipagination.
     *
     * @return array{sukses: bool, pesan?: string, data?: array, total?: int, halaman?: int, totalHal?: int}
     */
    public function cariPegawai(string $kataKunci = '', int $halaman = 1, int $per = 10): array
    {
        $kataKunci = trim($kataKunci);
        $halaman = max(1, $halaman);
        $offset = ($halaman - 1) * $per;

        try {
            if ($kataKunci === '') {
                $where = '';
            } else {
                $where = 'WHERE nik LIKE :kata1 OR nama LIKE :kata2';
            }

            $hitung = $this->pdo()->prepare("SELECT COUNT(*) FROM pegawai {$where}");
            $ambil = $this->pdo()->prepare(
                "SELECT nik, nama
                 FROM pegawai
                 {$where}
                 ORDER BY nama
                 LIMIT {$per} OFFSET {$offset}"
            );

            if ($kataKunci !== '') {
                $like = '%'.$kataKunci.'%';
                $hitung->bindValue(':kata1', $like);
                $hitung->bindValue(':kata2', $like);
                $ambil->bindValue(':kata1', $like);
                $ambil->bindValue(':kata2', $like);
            }

            $hitung->execute();
            $total = (int) $hitung->fetchColumn();

            $ambil->execute();

            return [
                'sukses' => true,
                'data' => $ambil->fetchAll(PDO::FETCH_ASSOC),
                'total' => $total,
                'halaman' => $halaman,
                'totalHal' => max(1, (int) ceil($total / $per)),
            ];
        } catch (Throwable $e) {
            Log::warning('Cek SIMRS gagal: '.$e->getMessage());

            return ['sukses' => false, 'pesan' => 'Gagal terhubung ke database SIMRS. Periksa konfigurasi koneksi.'];
        }
    }

    /**
     * Cek koneksi ke database SIMRS beserta kecepatannya.
     *
     * @return array{sukses: bool, pesan?: string, host: string, port: string, database: string,
     *              ms_total: int, ms_koneksi?: int, ms_query?: int,
     *              versi_server?: string, waktu_server?: string}
     */
    public function cekKoneksi(): array
    {
        $this->pdo = null;

        $hasil = [
            'sukses' => false,
            'host' => (string) config('simrs.host'),
            'port' => (string) config('simrs.port'),
            'database' => (string) config('simrs.database'),
            'ms_total' => 0,
        ];

        $mulai = microtime(true);

        try {
            $mulaiKoneksi = microtime(true);
            $pdo = $this->pdo();
            $msKoneksi = (int) round((microtime(true) - $mulaiKoneksi) * 1000);

            $mulaiQuery = microtime(true);
            $stmt = $pdo->query('SELECT VERSION(), NOW()');
            $baris = $stmt->fetch(PDO::FETCH_NUM);
            $msQuery = (int) round((microtime(true) - $mulaiQuery) * 1000);

            return array_merge($hasil, [
                'sukses' => true,
                'ms_koneksi' => $msKoneksi,
                'ms_query' => $msQuery,
                'ms_total' => $msKoneksi + $msQuery,
                'versi_server' => (string) $baris[0],
                'waktu_server' => (string) $baris[1],
            ]);
        } catch (Throwable $e) {
            Log::warning('Cek koneksi SIMRS gagal: '.$e->getMessage());

            return array_merge($hasil, [
                'pesan' => $e->getMessage(),
                'ms_total' => (int) round((microtime(true) - $mulai) * 1000),
            ]);
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            config('simrs.host'),
            config('simrs.port'),
            config('simrs.database')
        );

        $this->pdo = new PDO($dsn, config('simrs.username'), config('simrs.password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => (int) config('simrs.timeout', 5),
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $this->pdo;
    }
}
