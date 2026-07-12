<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Psr\Log\LoggerInterface;

/**
 * BaseController — induk semua controller aplikasi.
 */
abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    protected $helpers = ['absensi', 'form', 'url'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->db = Database::connect();
    }

    /**
     * Data lengkap pengguna yang sedang masuk (ter-join unit/sub/profesi/shift),
     * dengan cache per-permintaan. null bila tidak ada sesi / akun nonaktif.
     */
    protected function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }
        $u = $this->db->table('users as u')
            ->select('u.*, uk.nama AS unit_nama, su.nama AS sub_unit_nama, p.nama AS profesi_nama,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_jam_masuk,
                      s.jam_pulang AS shift_jam_pulang,
                      j.nama AS jabatan_nama,
                      COALESCE(j.unit_label, ji.unit_label) AS jabatan_unit,
                      sp.nama AS seksi_pembina_nama, spi.unit_label AS bidang_pembina_label')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->join('shift as s', 's.id = u.shift_id', 'left')
            ->join('jabatan as j', 'j.id = u.jabatan_id', 'left')
            ->join('jabatan ji', 'ji.id = j.induk_id', 'left')
            ->join('jabatan sp', 'sp.id = u.seksi_pembina_id', 'left')
            ->join('jabatan spi', 'spi.id = sp.induk_id', 'left')
            ->where('u.id', $uid)
            ->get()->getRowArray();

        if (! $u || $u['status'] !== 'aktif') {
            session()->destroy();
            return $cache = null;
        }
        return $cache = $u;
    }

    /** Balasan JSON seragam. */
    protected function json(array $data, int $kode = 200): ResponseInterface
    {
        return $this->response->setStatusCode($kode)->setJSON($data);
    }
}
