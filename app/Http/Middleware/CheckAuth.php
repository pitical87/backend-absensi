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

        $rememberUntil = session('remember_login_until');
        if ($rememberUntil !== null) {
            $remainingSeconds = (int) $rememberUntil - time();
            if ($remainingSeconds <= 0) {
                session()->flush();
                if ($request->expectsJson() || $request->isJson()) {
                    return response()->json([
                        'sukses' => false,
                        'pesan' => 'Sesi berakhir. Silakan masuk kembali.',
                    ], 401);
                }
                return redirect()->route('login');
            }

            config([
                'session.lifetime' => max(1, (int) ceil($remainingSeconds / 60)),
                'session.expire_on_close' => false,
            ]);
        }

        return $next($request);
    }
}
