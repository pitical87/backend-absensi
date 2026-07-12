<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('uid')) {
            if ($request->expectsJson() || $request->isJson()) {
                return response()->json([
                    'sukses' => false,
                    'pesan' => 'Sesi berakhir. Silakan masuk kembali.',
                ], 401);
            }
            return redirect()->route('login');
        }

        return $next($request);
    }
}
