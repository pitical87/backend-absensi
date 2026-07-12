<?php

namespace App\Controllers;

use Config\Database;
use Throwable;

/**
 * Install — pemasangan awal: membuat database, menjalankan migrasi & seeder,
 * lalu membuat akun admin pertama. Aman dijalankan berulang.
 */
class Install extends BaseController
{
    public function index()
    {
        [$tahap, $galat] = $this->siapkanSkema();

        return view('install/index', [
            'tahap' => $tahap,
            'galat' => $galat,
        ]);
    }

    public function buatAdmin()
    {
        [$tahap, $galat] = $this->siapkanSkema();
        if ($tahap !== 'admin') {
            return redirect()->to('install');
        }

        $nama  = trim((string) $this->request->getPost('nama'));
        $email = trim((string) $this->request->getPost('email'));
        $pass  = (string) $this->request->getPost('password');
        $pass2 = (string) $this->request->getPost('password2');

        if ($nama === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $galat = 'Nama dan email admin wajib diisi dengan benar.';
        } elseif (strlen($pass) < 6) {
            $galat = 'Password minimal 6 karakter.';
        } elseif ($pass !== $pass2) {
            $galat = 'Konfirmasi password tidak sama.';
        } else {
            $this->db->table('users')->insert([
                'nama_lengkap'  => $nama,
                'email'         => $email,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role'          => 'admin',
                'status'        => 'aktif',
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            catat_aktivitas('Pemasangan', 'Aplikasi terpasang; akun admin pertama dibuat: ' . $email);

            return redirect()->to('login')
                ->with('flash_sukses', 'Pemasangan selesai. Silakan masuk menggunakan akun admin Anda.');
        }

        return view('install/index', ['tahap' => 'admin', 'galat' => $galat]);
    }

    /**
     * Membuat database (bila belum ada), menjalankan migrasi + seeder.
     *
     * @return array{0: string, 1: string} [tahap: admin|selesai|gagal, pesan galat]
     */
    private function siapkanSkema(): array
    {
        $cfg = config(Database::class)->default;

        try {
            // 1) Buat database bila belum ada (koneksi tanpa nama database)
            $mysqli = @new \mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], '', (int) ($cfg['port'] ?: 3306));
            if ($mysqli->connect_errno) {
                throw new \RuntimeException('Tidak dapat terhubung ke MySQL: ' . $mysqli->connect_error);
            }
            $mysqli->query(
                'CREATE DATABASE IF NOT EXISTS `' . $mysqli->real_escape_string($cfg['database'])
                . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $mysqli->close();

            // 2) Jalankan migrasi & data master
            $migrasi = service('migrations');
            $migrasi->setNamespace('App')->latest();

            $seeder = Database::seeder();
            $seeder->call('MasterSeeder');

            // 3) Sudah ada admin?
            $adaAdmin = $this->db->table('users')->where('role', 'admin')->countAllResults() > 0;

            return [$adaAdmin ? 'selesai' : 'admin', ''];
        } catch (Throwable $e) {
            return ['gagal',
                'Pemasangan gagal: ' . $e->getMessage()
                . ' — Periksa pengaturan database pada app/Config/Database.php dan pastikan '
                . 'layanan MySQL pada XAMPP sudah berjalan.'];
        }
    }
}
