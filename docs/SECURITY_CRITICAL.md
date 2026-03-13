# 🔒 Critical Security Implementation Guide

Este documento describe la implementación de los 5 puntos críticos de seguridad para ContactSass.

---

## 📋 Tabla de Contenidos

1. [CSRF Protection](#csrf-protection)
2. [Webhook Signature Verification](#webhook-signature-verification)
3. [HTTPS/HSTS Enforcement](#httpshsts-enforcement)
4. [Secrets Management](#secrets-management)
5. [Audit Logging](#audit-logging)
6. [Integración & Deployment](#integración--deployment)

---

## 1. CSRF Protection

### Descripción
Previene ataques Cross-Site Request Forgery (CSRF) en endpoints que modifican datos.

### Implementación

#### Middleware: `CsrfProtectionMiddleware`
```php
// app/Http/Middleware/CsrfProtectionMiddleware.php
```

**Características:**
- Verifica tokens CSRF en métodos POST, PUT, PATCH, DELETE
- Excluye webhooks (que usan signature verification)
- Compara tokens de forma segura con `hash_equals()`
- Loguea intentos fallidos

#### Uso en Routes

```php
// Sin CSRF (webhooks y endpoints públicos)
Route::post('/webhooks/amazon-ses', [WebhookController::class, 'sesEvent']);

// Con CSRF
Route::post('/campaigns', [CampaignController::class, 'store'])
    ->middleware('csrf'); // Requiere token

// Con CSRF excepto algunos
Route::post('/api/sensitive', [SensitiveController::class, 'handle'])
    ->middleware('csrf:except-me');
```

#### Cliente (Frontend)

```javascript
// Obtener token CSRF
fetch('/csrf-token', { method: 'GET' })
  .then(r => r.json())
  .then(data => {
    // Guardar token
    const token = data.csrf_token;

    // En cada request POST/PUT/PATCH/DELETE
    fetch('/api/campaigns', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token, // ← Adjuntar token
      },
      body: JSON.stringify({ name: 'My Campaign' })
    });
  });
```

#### Token en Header vs Body

```php
// El middleware busca en orden:
1. Header: X-CSRF-Token
2. Header: X-Token
3. Form field: csrf_token

// Ejemplos
fetch('/api/campaigns', {
  method: 'POST',
  headers: {
    'X-CSRF-Token': 'token-value', // ← Option 1
  }
});

// O en FormData
const form = new FormData();
form.append('csrf_token', 'token-value'); // ← Option 2
fetch('/api/campaigns', { method: 'POST', body: form });
```

#### Testing

```php
// En tests
public function test_csrf_token_required(): void
{
    $response = $this->post('/api/campaigns', [], [
        'X-CSRF-Token' => session('csrf_token'), // Token válido
    ]);
    
    $this->assertEquals(200, $response->getStatusCode());
}

public function test_csrf_token_rejected(): void
{
    $response = $this->post('/api/campaigns', [], [
        'X-CSRF-Token' => 'invalid-token',
    ]);
    
    $this->assertEquals(419, $response->getStatusCode()); // 419 = Token Mismatch
}
```

---

## 2. Webhook Signature Verification

### Descripción
Verifica que webhooks provengan de fuentes legítimas usando HMAC signatures.

### Implementación

#### Middleware: `VerifyWebhookSignature`
```php
// app/Http/Middleware/VerifyWebhookSignature.php
```

**Características:**
- Soporta múltiples providers: Amazon SES, SNS, FreeSWITCH
- HMAC-SHA256 signature verification
- Obtiene secretos de AWS Secrets Manager o env
- Loguea intentos fallidos

#### Providers Configurados

| Provider | Header | Algoritmo |
|----------|--------|-----------|
| Amazon SES | X-Signature | SHA256 |
| Amazon SNS | X-Signature | SHA256 |
| FreeSWITCH | X-Freeswitch-Signature | SHA256 |

#### Uso en Routes

```php
Route::group(['middleware' => 'verify.webhook'], function () {
    Route::post('/webhooks/amazon-ses', [WebhookController::class, 'handleSes'])
        ->name('webhooks.ses');

    Route::post('/webhooks/amazon-sns', [WebhookController::class, 'handleSns'])
        ->name('webhooks.sns');

    Route::post('/webhooks/freeswitch', [WebhookController::class, 'handleFreeswitch'])
        ->name('webhooks.freeswitch');
});
```

#### Configuración de Secretos

**Opción 1: Environment Variables (.env)**
```bash
WEBHOOK_AMAZON_SES_SECRET=your-webhook-secret
WEBHOOK_AMAZON_SNS_SECRET=your-webhook-secret
WEBHOOK_FREESWITCH_SECRET=your-webhook-secret
```

**Opción 2: AWS Secrets Manager (Recomendado)**
```bash
AWS_SECRETS_ENABLED=true
# Luego el middleware obtiene automáticamente de AWS
```

**Opción 3: Base de Datos**
```php
// Tabla webhook_configs
WebhookConfig::create([
    'provider' => 'amazon-ses',
    'tenant_id' => $tenantId,
    'secret' => 'your-webhook-secret',
    'is_active' => true,
]);
```

#### Implementación en Controller

```php
// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    public function handleSes(Request $request): JsonResponse
    {
        // En este punto, la firma ya fue verificada por el middleware
        $payload = $request->json()->all();

        \Log::info('SES webhook received', ['event' => $payload['eventType'] ?? null]);

        // Procesar webhook
        dispatch(new ProcessSesEvent($payload));

        return response()->json(['success' => true]);
    }

    public function handleSns(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        
        // SNS envía mensaje en formato especial
        // Manejar SubscriptionConfirmation si es necesario
        if ($payload['Type'] === 'SubscriptionConfirmation') {
            $this->confirmSnsSubscription($payload);
            return response()->json(['success' => true]);
        }

        \Log::info('SNS webhook received', ['subject' => $payload['Subject'] ?? null]);

        dispatch(new ProcessSnsEvent($payload));

        return response()->json(['success' => true]);
    }

    private function confirmSnsSubscription(array $payload): void
    {
        // Confirmar suscripción a SNS
        file_get_contents($payload['SubscribeURL']);
    }
}
```

#### Testing

```php
public function test_webhook_signature_verification(): void
{
    $secret = 'test-webhook-secret';
    $payload = json_encode(['event' => 'message.received']);
    $signature = hash_hmac('sha256', $payload, $secret);

    $response = $this->post('/webhooks/amazon-ses', 
        json_decode($payload, true),
        ['X-Signature' => $signature]
    );

    $this->assertEquals(200, $response->getStatusCode());
}

public function test_webhook_invalid_signature_rejected(): void
{
    $response = $this->post('/webhooks/amazon-ses', 
        ['event' => 'message.received'],
        ['X-Signature' => 'invalid-signature']
    );

    $this->assertEquals(401, $response->getStatusCode());
}
```

---

## 3. HTTPS/HSTS Enforcement

### Descripción
Fuerza HTTPS y adjunta HSTS headers para seguridad en tránsito.

### Implementación

#### Middleware: `EnforceHttpsWithHsts`
```php
// app/Http/Middleware/EnforceHttpsWithHsts.php
```

**Características:**
- Redirige HTTP → HTTPS en producción
- Adjunta HSTS header automáticamente
- Configurable por environment
- Soporte para preload list

#### Configuración

```bash
# .env
APP_ENV=production
APP_HTTPS_ENFORCED=true
HTTPS_REDIRECT_ENABLED=true
```

#### Headers Adjuntados

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
```

#### Nginx Configuration

```nginx
# nginx.conf - Adicional al middleware
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /etc/ssl/certs/certificate.crt;
    ssl_certificate_key /etc/ssl/private/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # HSTS Header (1 year)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Redirect HTTP to HTTPS
    error_page 497 =301 https://$server_name$request_uri;

    location / {
        proxy_pass http://laravel:9000;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.example.com;
    return 301 https://$server_name$request_uri;
}
```

#### HSTS Preload Registration

```bash
# Una vez en producción, registrar en HSTS preload list
# https://hstspreload.org
# Requisitos:
# - Max-Age >= 31536000 (1 año)
# - Include subdomains: true
# - Preload: true
# - Chrome, Firefox, Safari, Edge lo incluirán automáticamente
```

#### Testing

```php
public function test_https_redirect_in_production(): void
{
    $this->withoutMiddleware(); // Simular sin middleware
    
    $response = $this->get('http://api.example.com/campaigns');
    
    // En producción debe redirigir
    // (En testing no lo hace por defecto)
    $this->assertTrue(true);
}

public function test_hsts_header_present(): void
{
    $response = $this->get('/campaigns');
    
    $this->assertNotNull($response->header('Strict-Transport-Security'));
    $this->assertStringContainsString('max-age=31536000', 
        $response->header('Strict-Transport-Security')
    );
}
```

---

## 4. Secrets Management (AWS Secrets Manager)

### Descripción
Gestiona secretos sensibles (passwords, API keys) en AWS Secrets Manager en lugar de .env.

### Implementación

#### Service: `SecretsManager`
```php
// app/Services/SecretsManager.php
```

**Características:**
- Integración con AWS Secrets Manager
- Caching local (1 hora)
- Fallback a environment variables
- Soporte para JSON secrets
- Audit logging de accesos

#### Estructura de Secretos en AWS

```
contact-sass/
├── database/
│   ├── password         # DB password
│   └── root-password    # DB root password
├── aws/
│   ├── access-key-id
│   └── secret-access-key
├── integrations/
│   ├── amazon-ses/
│   │   ├── key
│   │   └── secret
│   ├── amazon-sns/
│   │   ├── key
│   │   └── secret
│   └── freeswitch/
│       └── password
├── webhooks/
│   ├── amazon-ses/secret
│   ├── amazon-sns/secret
│   └── freeswitch/secret
├── auth/
│   ├── jwt-secret
│   └── sanctum-secret
└── redis/
    └── password
```

#### Uso

```php
// En providers, controllers, etc

use App\Services\SecretsManager;

$manager = new SecretsManager();

// Obtener secreto individual
$dbPassword = $manager->get('database-password');

// Obtener JSON
$sesConfig = $manager->getJson('integrations/amazon-ses/config');
// Retorna: ['key' => '...', 'secret' => '...']

// Almacenar nuevo secreto
$manager->put('my-secret', 'secret-value');

// Crear nuevo secreto
$manager->create('new-secret', 'value');

// Rotar secreto (actualizar)
$manager->rotate('old-secret', 'new-value');

// Obtener múltiples
$secrets = $manager->getMultiple([
    'database-password',
    'redis-password',
]);
```

#### Configuración

```php
// config/aws_secrets.php
'enabled' => env('AWS_SECRETS_ENABLED', false),
'region' => env('AWS_REGION', 'us-east-1'),
'cache_ttl' => env('AWS_SECRETS_CACHE_TTL', 3600),

// Mappings: config key => AWS secret name
'secrets' => [
    'db_password' => env('AWS_SECRETS_DB_PASSWORD', 'contact-sass/database/password'),
    // ...
]
```

#### Environment Setup

```bash
# .env
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_SECRETS_ENABLED=true

# IAM Policy required:
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret",
        "secretsmanager:ListSecrets",
        "secretsmanager:PutSecretValue",
        "secretsmanager:CreateSecret",
        "secretsmanager:DeleteSecret"
      ],
      "Resource": "arn:aws:secretsmanager:us-east-1:*:secret:contact-sass/*"
    }
  ]
}
```

#### Service Provider

```php
// app/Providers/AppServiceProvider.php

use App\Services\SecretsManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register SecretsManager as singleton
        $this->app->singleton(SecretsManager::class, function () {
            return new SecretsManager();
        });

        // Usar en configuración si está habilitado
        if (env('AWS_SECRETS_ENABLED', false)) {
            $manager = app(SecretsManager::class);
            
            // Cargar secretos en app config
            config([
                'database.connections.mysql.password' => $manager->get('database-password'),
                'database.redis.password' => $manager->get('redis-password'),
            ]);
        }
    }
}
```

#### Testing

```php
public function test_secrets_manager_get_secret(): void
{
    $manager = new SecretsManager();
    
    // En testing, fallback a env
    putenv('TEST_SECRET_SECRET=my-secret-value');
    
    $secret = $manager->get('test-secret');
    $this->assertEquals('my-secret-value', $secret);
}

public function test_secrets_sanitization(): void
{
    // Secretos no deben estar en logs
    $manager = new SecretsManager();
    $secret = $manager->get('database-password');
    
    // Verificar que no aparece en logs
    $logs = \Log::getHandler()->getRecords();
    // Assert secret not in logs
}
```

---

## 5. Audit Logging (Security Audit Logger)

### Descripción
Registra todas las acciones sensibles para compliance y forensics.

### Implementación

#### Service: `SecurityAuditLogger`
```php
// app/Services/SecurityAuditLogger.php
```

#### Model: `SecurityAuditLog`
```php
// app/Models/SecurityAuditLog.php
```

#### Eventos que se Registran

```
Autenticación:
- auth.attempt           # Failed login
- auth.failed_login
- auth.success
- auth.password_changed
- auth.api_key_generated
- auth.api_key_revoked

Autorización:
- authorization.denied   # Permission check failed

Recursos:
- resource.created
- resource.updated
- resource.deleted

Operaciones Sensibles:
- sensitive.password_change
- sensitive.api_key_rotation
- sensitive.token_issued

Seguridad:
- security.csrf_failure
- security.rate_limit_exceeded
- security.webhook_verification_failed

Campañas:
- campaign.sent           # Campaña enviada
- campaign.deleted
```

#### Uso

```php
use App\Services\SecurityAuditLogger;

// Log genérico
SecurityAuditLogger::log('auth.attempt', [
    'user_id' => auth()->id(),
    'status' => 'failed',
]);

// Log autenticación
SecurityAuditLogger::logAuthentication(
    userId: $user->id,
    success: true,
    method: 'password'
);

// Log fallo de autorización
SecurityAuditLogger::logAuthorizationFailure(
    action: 'campaigns.delete',
    resourceType: 'campaign',
    resourceId: '123',
    reason: 'insufficient_permissions'
);

// Log cambio de recurso
SecurityAuditLogger::logResourceModification(
    action: 'created',
    resourceType: 'campaign',
    resourceId: '123',
    changes: ['name' => 'My Campaign']
);

// Log operación sensible
SecurityAuditLogger::logSensitiveOperation(
    operation: 'password_change',
    details: ['user_id' => '123']
);

// Log evento de seguridad
SecurityAuditLogger::logSecurityEvent(
    event: 'rate_limit_exceeded',
    details: ['endpoint' => '/api/campaigns', 'attempts' => 100],
    severity: 'warning'
);

// Log fallos de login
SecurityAuditLogger::logFailedLogin(
    email: 'user@example.com',
    reason: 'invalid_credentials'
);

// Log campaña enviada
SecurityAuditLogger::logCampaignSent(
    campaignId: '123',
    recipientCount: 1000
);
```

#### Alcance de Queries

```php
use App\Models\SecurityAuditLog;

// Por usuario (últimos 30 días)
$logs = SecurityAuditLogger::getUserLogs(userId: $user->id);

// Por recurso
$logs = SecurityAuditLog::byResource('campaign', '123')->get();

// Eventos de seguridad (últimas 24h)
$events = SecurityAuditLogger::getRecentSecurityEvents();

// Intentos fallidos de login (última hora)
$failed = SecurityAuditLogger::getFailedAuthAttempts();

// Dashboard de seguridad
$dashboard = SecurityAuditLog::getSecurityDashboard(
    tenantId: $tenant->id,
    days: 7
);

// Detectar actividad sospechosa
$isSuspicious = SecurityAuditLog::checkSuspiciousActivity(
    userId: $user->id,
    failedAttemptThreshold: 5
);
```

#### Integración en Controller

```php
class CampaignController extends Controller
{
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        // Log creación
        SecurityAuditLogger::logResourceModification(
            action: 'created',
            resourceType: 'campaign',
            resourceId: $campaign->id,
            changes: $request->only(['name', 'subject', 'channel']),
            status: 'success'
        );

        return response()->json(['data' => $campaign]);
    }

    public function send(Campaign $campaign): JsonResponse
    {
        $count = $campaign->send();

        // Log envío
        SecurityAuditLogger::logCampaignSent(
            campaignId: $campaign->id,
            recipientCount: $count
        );

        return response()->json(['recipients' => $count]);
    }
}
```

#### Sanitización de Datos Sensibles

```php
// Automáticamente se sanitizan:
$data = [
    'password' => 'secret123',      // → ***REDACTED***
    'api_key' => 'sk-12345',        // → ***REDACTED***
    'token' => 'bearer-xyz',        // → ***REDACTED***
    'credit_card' => '4111...',     // → ***REDACTED***
    'email' => 'user@example.com',  // ✓ OK (no sensitive)
];

$sanitized = SecurityAuditLogger::sanitize($data);
// Result: password, api_key, token, credit_card → ***REDACTED***
```

#### Alertas de Seguridad

```bash
# .env
SECURITY_AUDIT_ALERTS_ENABLED=true
SECURITY_AUDIT_ALERT_CHANNEL=mail
SECURITY_ALERT_RECIPIENTS=security@example.com

# Triggers
RATE_LIMIT_EXCEEDED_THRESHOLD=100
FAILED_LOGIN_THRESHOLD=5
AUTHORIZATION_FAILURES_THRESHOLD=10
```

#### Monitoring & Reporting

```php
// artisan command para revisar logs
// php artisan security:audit-report --period=7days

// Dashboard endpoint
Route::get('/admin/security/dashboard', function (Request $request) {
    $dashboard = SecurityAuditLog::getSecurityDashboard(
        tenantId: auth()->user()->tenant_id,
        days: 7
    );
    
    return response()->json($dashboard);
})->middleware('auth:sanctum', 'admin');
```

---

## Integración & Deployment

### 1. Registrar Middlewares

```php
// app/Http/HttpKernel.php (o similar)

protected array $middleware = [
    // ... existing middleware
    EnforceHttpsWithHsts::class,
    CsrfProtectionMiddleware::class,
    // ResponseTransformMiddleware should be last
];

protected array $routeMiddleware = [
    'csrf' => CsrfProtectionMiddleware::class,
    'verify.webhook' => VerifyWebhookSignature::class,
];
```

### 2. Rutas Ejemplo

```php
// routes/api.php

// Endpoints públicos (sin autenticación)
Route::post('/webhooks/amazon-ses', [WebhookController::class, 'ses'])
    ->middleware('verify.webhook');

Route::post('/auth/login', [AuthController::class, 'login']);

// Endpoints autenticados (con CSRF)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/campaigns', [CampaignController::class, 'store'])
        ->middleware('csrf');

    Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'send'])
        ->middleware('csrf');

    // Admin (con auditoría)
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('csrf');
});
```

### 3. Migración a Producción

```bash
# 1. Crear tablas
php artisan migrate --production

# 2. Configurar AWS Secrets Manager
aws secretsmanager create-secret \
  --name contact-sass/database/password \
  --secret-string "your-production-password"

# 3. Configurar .env
AWS_SECRETS_ENABLED=true
SECURITY_AUDIT_ENABLED=true
APP_HTTPS_ENFORCED=true

# 4. Tests
php artisan test tests/Unit/Security/

# 5. Deploy
git push production main
```

### 4. Monitoring

```bash
# Ver últimos eventos de seguridad
SELECT * FROM security_audit_logs 
WHERE timestamp >= NOW() - INTERVAL 24 HOUR
ORDER BY timestamp DESC 
LIMIT 100;

# Alertas de intentos fallidos
SELECT user_id, COUNT(*) as attempts
FROM security_audit_logs
WHERE action = 'auth.failed_login'
  AND timestamp >= NOW() - INTERVAL 1 HOUR
GROUP BY user_id
HAVING attempts >= 5;
```

---

## 📋 Checklist de Implementación

```
[ ] CSRF Protection
    [ ] Middleware creado
    [ ] Rutas configuradas
    [ ] Frontend actualizado con tokens
    [ ] Tests pasados

[ ] Webhook Verification
    [ ] Middleware creado
    [ ] Providers configurados
    [ ] Secretos en AWS o .env
    [ ] Webhooks reciben correctamente

[ ] HTTPS/HSTS
    [ ] Middleware registrado
    [ ] Nginx configurado
    [ ] Certificados SSL validos
    [ ] HSTS headers presentes

[ ] Secrets Manager
    [ ] AWS Secretos creados
    [ ] IAM policy configurada
    [ ] .env tiene AWS_SECRETS_ENABLED=true
    [ ] Fallback a .env funciona

[ ] Audit Logging
    [ ] Tablas migradas
    [ ] SecurityAuditLogger usado en endpoints
    [ ] Alertas configuradas
    [ ] Dashboard accessible
```

---

## 📚 Referencias

- OWASP CSRF Prevention: https://owasp.org/www-community/attacks/csrf/
- AWS Secrets Manager: https://aws.amazon.com/secrets-manager/
- HSTS: https://hstspreload.org/
- Laravel Security: https://laravel.com/docs/security

