<?php

declare(strict_types=1);

namespace App\Services;

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Cache;

final class SecretsManager
{
    /**
     * AWS Secrets Manager client.
     */
    private ?SecretsManagerClient $client = null;

    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if ($this->isAwsSecretsEnabled()) {
            $this->initializeClient();
        }
    }

    /**
     * Get a secret by name.
     *
     * @throws \RuntimeException if AWS Secrets Manager is not configured
     */
    public function get(string $secretName, bool $useCache = true): ?string
    {
        // Si no está habilitado, obtener del .env
        if (!$this->isAwsSecretsEnabled()) {
            return $this->getFromEnv($secretName);
        }

        // Verificar cache
        if ($useCache) {
            $cached = Cache::get($this->getCacheKey($secretName));
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $secret = $this->client->getSecretValue([
                'SecretId' => $secretName,
            ]);

            // Obtener valor (puede estar en 'SecretString' o 'SecretBinary')
            $value = $secret['SecretString'] ?? $secret['SecretBinary'] ?? null;

            // Cachear por 1 hora
            if ($value && $useCache) {
                Cache::put($this->getCacheKey($secretName), $value, self::CACHE_TTL);
            }

            return $value;
        } catch (AwsException $e) {
            \Log::error('Error fetching secret from AWS Secrets Manager', [
                'secret' => $secretName,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to fetch secret: {$secretName}");
        }
    }

    /**
     * Get a JSON secret and parse it.
     *
     * @return array<string, mixed>
     */
    public function getJson(string $secretName, bool $useCache = true): array
    {
        $secret = $this->get($secretName, $useCache);

        if (!$secret) {
            return [];
        }

        $parsed = json_decode($secret, true);

        if (!is_array($parsed)) {
            \Log::warning('Secret is not valid JSON', ['secret' => $secretName]);
            return [];
        }

        return $parsed;
    }

    /**
     * Store a secret in AWS Secrets Manager.
     *
     * @param bool $createIfNotExists - Create secret if it doesn't exist
     */
    public function put(string $secretName, string $secretValue, bool $createIfNotExists = false): bool
    {
        if (!$this->isAwsSecretsEnabled()) {
            \Log::warning('AWS Secrets Manager not configured, cannot store secret', [
                'secret' => $secretName,
            ]);
            return false;
        }

        try {
            $this->client->putSecretValue([
                'SecretId' => $secretName,
                'SecretString' => $secretValue,
            ]);

            // Limpiar cache
            Cache::forget($this->getCacheKey($secretName));

            \Log::info('Secret stored in AWS Secrets Manager', ['secret' => $secretName]);

            return true;
        } catch (AwsException $e) {
            // Si falla porque no existe y no queremos crearlo
            if (!$createIfNotExists) {
                \Log::error('Error storing secret in AWS Secrets Manager', [
                    'secret' => $secretName,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            // Intentar crear el secreto
            return $this->create($secretName, $secretValue);
        }
    }

    /**
     * Create a new secret.
     */
    public function create(string $secretName, string $secretValue): bool
    {
        if (!$this->isAwsSecretsEnabled()) {
            return false;
        }

        try {
            $this->client->createSecret([
                'Name' => $secretName,
                'SecretString' => $secretValue,
                'Description' => "Secret for {$secretName}",
            ]);

            \Log::info('Secret created in AWS Secrets Manager', ['secret' => $secretName]);

            return true;
        } catch (AwsException $e) {
            \Log::error('Error creating secret in AWS Secrets Manager', [
                'secret' => $secretName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete a secret.
     *
     * @param int $recoveryWindowDays - Days until permanent deletion (7-30)
     */
    public function delete(string $secretName, int $recoveryWindowDays = 7): bool
    {
        if (!$this->isAwsSecretsEnabled()) {
            return false;
        }

        try {
            $this->client->deleteSecret([
                'SecretId' => $secretName,
                'RecoveryWindowInDays' => $recoveryWindowDays,
            ]);

            // Limpiar cache
            Cache::forget($this->getCacheKey($secretName));

            \Log::info('Secret deleted from AWS Secrets Manager', ['secret' => $secretName]);

            return true;
        } catch (AwsException $e) {
            \Log::error('Error deleting secret from AWS Secrets Manager', [
                'secret' => $secretName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Rotate a secret.
     *
     * @param string $newValue - New secret value
     */
    public function rotate(string $secretName, string $newValue): bool
    {
        return $this->put($secretName, $newValue);
    }

    /**
     * Clear cache for a secret.
     */
    public function clearCache(string $secretName): void
    {
        Cache::forget($this->getCacheKey($secretName));
    }

    /**
     * Clear all secrets cache.
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }

    /**
     * Get multiple secrets efficiently.
     *
     * @param array<int, string> $secretNames
     *
     * @return array<string, ?string>
     */
    public function getMultiple(array $secretNames): array
    {
        $result = [];

        foreach ($secretNames as $name) {
            $result[$name] = $this->get($name);
        }

        return $result;
    }

    /**
     * Check if AWS Secrets Manager is enabled.
     */
    private function isAwsSecretsEnabled(): bool
    {
        return config('services.aws_secrets.enabled', false) && 
               env('AWS_SECRETS_ENABLED', false);
    }

    /**
     * Initialize AWS SecretsManager client.
     */
    private function initializeClient(): void
    {
        $this->client = new SecretsManagerClient([
            'version' => 'latest',
            'region' => env('AWS_REGION', 'us-east-1'),
        ]);
    }

    /**
     * Get cache key for secret.
     */
    private function getCacheKey(string $secretName): string
    {
        return "aws_secret:{$secretName}";
    }

    /**
     * Get secret from environment variables.
     *
     * Busca:
     * 1. {SECRETNAME}_SECRET
     * 2. {SECRETNAME}
     */
    private function getFromEnv(string $secretName): ?string
    {
        $envKey = strtoupper(str_replace('-', '_', $secretName)) . '_SECRET';
        $value = env($envKey);

        if ($value) {
            return $value;
        }

        // Intentar sin sufijo _SECRET
        return env(strtoupper(str_replace('-', '_', $secretName)));
    }
}
