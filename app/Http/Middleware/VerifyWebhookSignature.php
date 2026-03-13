<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    /**
     * Providers de webhook soportados.
     *
     * @var array<string, array{header: string, algorithm: string}>
     */
    private const WEBHOOK_PROVIDERS = [
        'amazonaws' => ['header' => 'X-Amz-SNS-Message-Id', 'algorithm' => 'sha256'],
        'amazon-ses' => ['header' => 'X-Amz-SNS-Message-Id', 'algorithm' => 'sha256'],
        'freeswitch' => ['header' => 'X-Freeswitch-Signature', 'algorithm' => 'sha256'],
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
        // Obtener provider del path o header
        $provider = $this->getProvider($request);

        // Obtener secreto del provider
        $secret = $this->getSecret($provider);

        if (!$secret) {
            \Log::error('Webhook secret not configured', ['provider' => $provider]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook secret not configured',
                'code' => 'WEBHOOK_SECRET_MISSING',
            ], 500);
        }

        // Verificar firma
        if (!$this->verifySignature($request, $secret, $provider)) {
            \Log::warning('Webhook signature verification failed', [
                'provider' => $provider,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook signature verification failed',
                'code' => 'WEBHOOK_SIGNATURE_INVALID',
            ], 401);
        }

        // Log webhook recibido
        \Log::info('Webhook signature verified', [
            'provider' => $provider,
            'path' => $request->path(),
        ]);

        return $next($request);
    }

    /**
     * Get webhook provider from request.
     */
    private function getProvider(Request $request): string
    {
        // Obtener del path: /webhooks/amazon-ses -> amazon-ses
        if (preg_match('/webhooks\/([a-z-]+)/i', $request->path(), $matches)) {
            return strtolower($matches[1]);
        }

        // Obtener del header
        if ($request->header('X-Webhook-Provider')) {
            return strtolower($request->header('X-Webhook-Provider'));
        }

        return 'unknown';
    }

    /**
     * Get webhook secret from environment or database.
     */
    private function getSecret(string $provider): ?string
    {
        // Intentar obtener de environment primero
        $envKey = 'WEBHOOK_' . strtoupper($provider) . '_SECRET';
        $secret = env($envKey);

        if ($secret) {
            return $secret;
        }

        // Intentar obtener de base de datos (para multi-tenant)
        try {
            $config = \DB::table('webhook_configs')
                ->where('provider', $provider)
                ->where('is_active', true)
                ->first();

            if ($config) {
                return $config->secret;
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching webhook secret from database', ['provider' => $provider]);
        }

        return null;
    }

    /**
     * Verify webhook signature.
     */
    private function verifySignature(Request $request, string $secret, string $provider): bool
    {
        // Obtener firma del request
        $signature = $this->getSignature($request, $provider);

        if (!$signature) {
            return false;
        }

        // Obtener payload
        $payload = $request->getContent();

        if (empty($payload)) {
            return false;
        }

        // Calcular firma esperada
        $algorithm = self::WEBHOOK_PROVIDERS[$provider]['algorithm'] ?? 'sha256';
        $expectedSignature = hash_hmac($algorithm, $payload, $secret);

        // Comparación segura
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get signature from request.
     *
     * Busca en diferentes headers según provider.
     */
    private function getSignature(Request $request, string $provider): ?string
    {
        // Headers comunes
        $headers = [
            'X-Signature',
            'X-' . ucfirst($provider) . '-Signature',
            'X-Webhook-Signature',
            'Signature',
        ];

        foreach ($headers as $header) {
            $value = $request->header($header);
            if ($value) {
                return $value;
            }
        }

        // Para SNS de AWS, buscar en el body (JSON)
        if ($provider === 'amazon-ses' || $provider === 'amazonaws') {
            $body = $request->json();
            if ($body && $body->has('Signature')) {
                return $body->get('Signature');
            }
        }

        return null;
    }
}
