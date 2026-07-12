<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('uid')) {
            return redirect()->route('login');
        }

        if (session('role') !== 'admin') {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
