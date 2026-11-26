<?php

namespace App\Http\Middleware;

use App\Services\RememberLoginService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Berjalan pada grup middleware `web` (setelah EncryptCookies & StartSession)
 * agar fitur "Ingat saya" bekerja konsisten di seluruh rute web (admin, pegawai,
 * beranda `/`, maupun `/login`) bahkan setelah sesi database 120 menit habis
 * atau browser ditutup dan dibuka kembali.
 */
class RestoreRememberedLogin
{
    public function __construct(private readonly RememberLoginService $remember) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->is('logout')
            || $request->is('install')
            || ($request->is('login') && $request->isMethod('POST'))
        ) {
            return $next($request);
        }

        $this->remember->sinkronkan($request);

        return $next($request);
    }
}
