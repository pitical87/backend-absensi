<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Wajib sudah masuk (pegawai atau admin). */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session('uid')) {
            if ($request->hasHeader('X-Requested-With') || str_starts_with((string) $request->getHeaderLine('Content-Type'), 'application/json')) {
                return service('response')->setStatusCode(401)
                    ->setJSON(['sukses' => false, 'pesan' => 'Sesi berakhir. Silakan masuk kembali.']);
            }
            return redirect()->to('login');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
