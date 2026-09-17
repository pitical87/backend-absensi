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
        if(!$apitoken->last_aktivitas || $apitoken->last_aktivitas->lte(now()->subMinute())){
            $ubah = ['last_aktivitas' => now()];
            if (! $apitoken->ip) {
                $ubah['ip'] = $request->ip();
            }
            if (! $apitoken->perangkat) {
                $ubah['perangkat'] = substr(trim((string) ($request->header('X-Device-Name', ''))), 0, 150);
            }
            if (empty($apitoken->user_agent)) {
                $ubah['user_agent'] = substr((string) $request->header('User-Agent', ''), 0, 255);
            }
            $apitoken->forceFill($ubah)->save();
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