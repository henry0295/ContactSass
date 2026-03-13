<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceHttpsWithHsts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // En producción, forzar HTTPS
        if ($this->shouldEnforceHttps()) {
            if (!$request->secure()) {
                // Redirigir a HTTPS
                return redirect(
                    'https://' . $request->getHttpHost() . $request->getRequestUri(),
                    301
                );
            }
        }

        /** @var Response $response */
        $response = $next($request);

        // Adjuntar HSTS header
        $response->header('Strict-Transport-Security', $this->getHstsHeader());

        // Adicionales de seguridad
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');

        return $response;
    }

    /**
     * Check if HTTPS should be enforced.
     */
    private function shouldEnforceHttps(): bool
    {
        // No forzar en desarrollo o testing
        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        // Forzar en producción y staging
        return config('app.https_enforced', true);
    }

    /**
     * Get HSTS header value.
     *
     * Includesubdomains en producción.
     * Incluya preload para HSTS preload list.
     */
    private function getHstsHeader(): string
    {
        $maxAge = $this->getMaxAge();
        $includeSubdomains = app()->environment('production');

        $header = "max-age={$maxAge}";

        if ($includeSubdomains) {
            $header .= '; includeSubDomains';
        }

        // Incluir preload solo en producción
        if (app()->environment('production')) {
            $header .= '; preload';
        }

        return $header;
    }

    /**
     * Get HSTS max-age value in seconds.
     *
     * 1 año en producción, 60 días en staging.
     */
    private function getMaxAge(): int
    {
        if (app()->environment('production')) {
            // 1 año
            return 31536000;
        }

        // 60 días
        return 5184000;
    }
}
