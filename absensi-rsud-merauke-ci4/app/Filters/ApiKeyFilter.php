<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pelindung API integrasi SIMRS: header X-API-KEY harus cocok dengan
 * kunci pada pengaturan aplikasi (dibuat saat pemasangan, dapat diganti admin).
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('absensi');
        $kunci = (string) pengaturan('api_key', '');
        $kirim = (string) $request->getHeaderLine('X-API-KEY');

        if ($kunci === '' || $kirim === '' || ! hash_equals($kunci, $kirim)) {
            return service('response')->setStatusCode(401)->setJSON([
                'sukses' => false,
                'pesan'  => 'Kunci API tidak valid. Sertakan header X-API-KEY yang benar.',
            ]);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
