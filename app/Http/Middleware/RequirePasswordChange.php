<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengunci pengguna pada halaman ganti kata sandi selama kata sandinya masih
 * ditandai wajib diganti — misalnya karena tidak memenuhi syarat kekuatan, atau
 * karena kata sandinya baru saja diatur oleh Super Admin.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Kata sandi Anda harus diganti sebelum melanjutkan.',
                ], 423);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
