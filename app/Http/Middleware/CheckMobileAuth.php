<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class  CheckMobileAuth {
    public function handle(Request $request, Closure $next): Response{
        $token = $request->cookie('auth_token');
        if(!$token){
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Token tidak ditemukan"
            ], 401);
        }
        $apitoken = ApiToken::where('token',$token)
            ->first();
        if(!$apitoken){
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Token tidak valid atau kadaluarsa. Silahkan login kembali"
            ],401);
        }
        if(now()->greaterThan($apitoken->expires_at)){
            ApiToken::where('id',$apitoken->id)
                ->delete();
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Token tidak valid atau kadaluarsa. Silahkan login kembali"
            ],401);
        }
        $user = User::where('id',$apitoken->user_id)
            ->first();
        if(!$user || $user->status !== 'aktif'){
            ApiToken::where('id',$apitoken->id)
                ->delete();
            return response()->json([
                "sukses"=>false,
                "pesan"=>"Akun tidak aktif"
            ],401);
        }

        $request->attributes->set('user',$user);
        return $next($request);
    }
}