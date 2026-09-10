<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memasang header keamanan pada setiap respons web.
 *
 * Konfigurasinya ada di config/security.php sehingga daftar sumber CSP dapat
 * disesuaikan tanpa mengubah kode.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ((array) config('security.headers', []) as $header => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $response->headers->set($header, $value, false);
        }

        if (config('security.hsts.enabled') && $request->secure()) {
            $hsts = 'max-age='.(int) config('security.hsts.max_age', 31536000);

            if (config('security.hsts.include_subdomains')) {
                $hsts .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $hsts, false);
        }

        if ($policy = $this->contentSecurityPolicy()) {
            $header = config('security.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $policy, false);
        }

        return $response;
    }

    /**
     * Susun nilai header Content-Security-Policy dari konfigurasi.
     */
    private function contentSecurityPolicy(): ?string
    {
        if (! config('security.csp.enabled')) {
            return null;
        }

        $parts = [];

        foreach ((array) config('security.csp.directives', []) as $directive => $sources) {
            $sources = array_filter((array) $sources);

            if ($sources === []) {
                continue;
            }

            $parts[] = $directive.' '.implode(' ', $sources);
        }

        return $parts === [] ? null : implode('; ', $parts);
    }
}
