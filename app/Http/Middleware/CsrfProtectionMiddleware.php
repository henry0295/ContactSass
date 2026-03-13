<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CsrfProtectionMiddleware
{
    /**
     * Rutas excluidas de verificación CSRF.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'api/*/webhooks/*',
        'api/webhooks/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo verificar en métodos que modifican estado
        if (!$this->isMethodProtected($request->method())) {
            return $next($request);
        }

        // Verificar si esta ruta está excluida
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        // Obtener token CSRF
        $token = $this->getToken($request);

        // Validar token
        if (!$token || !$this->validateToken($token)) {
            \Log::warning('CSRF token verification failed', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'CSRF token verification failed',
                'code' => 'CSRF_TOKEN_MISMATCH',
            ], 419);
        }

        return $next($request);
    }

    /**
     * Check if HTTP method requires CSRF protection.
     */
    private function isMethodProtected(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Check if request is excluded from CSRF protection.
     */
    private function isExcluded(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($this->matchesPattern($request->path(), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match path against pattern with wildcards.
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        $regex = str_replace('*', '.*', preg_quote($pattern, '/'));

        return (bool) preg_match("/^{$regex}$/", $path);
    }

    /**
     * Get CSRF token from request.
     * 
     * Busca en orden:
     * 1. X-CSRF-Token header
     * 2. X-Token header
     * 3. csrf_token form field
     */
    private function getToken(Request $request): ?string
    {
        // Header X-CSRF-Token
        if ($request->header('X-CSRF-Token')) {
            return $request->header('X-CSRF-Token');
        }

        // Header X-Token (alternativa)
        if ($request->header('X-Token')) {
            return $request->header('X-Token');
        }

        // Form field
        if ($request->has('csrf_token')) {
            return $request->input('csrf_token');
        }

        return null;
    }

    /**
     * Validate CSRF token against session.
     */
    private function validateToken(string $token): bool
    {
        $sessionToken = session('csrf_token') ?? session('_token');

        if (!$sessionToken) {
            return false;
        }

        // Comparación segura de hashes
        return hash_equals((string) $sessionToken, (string) $token);
    }
}
