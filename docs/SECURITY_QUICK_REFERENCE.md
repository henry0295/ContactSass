# 🔐 ContactSass Security - Quick Reference

## 🚀 Deploy en 5 Pasos

### Paso 1: Setup AWS Secrets (2 min)
```bash
# Crear secretos en AWS
aws secretsmanager create-secret --name contact-sass/database/password --secret-string "your-db-password"
aws secretsmanager create-secret --name contact-sass/aws/access-key-id --secret-string "your-key"
aws secretsmanager create-secret --name contact-sass/integrations/amazon-ses/key --secret-string "your-ses-key"
```

### Paso 2: Configurar .env
```bash
# Copiar .env.security.example
cp .env.security.example .env

# Editar estos valores:
AWS_SECRETS_ENABLED=true
SECURITY_AUDIT_ENABLED=true
APP_HTTPS_ENFORCED=true
WEBHOOK_AMAZON_SES_SECRET=your-webhook-secret
```

### Paso 3: Migrar Base de Datos
```bash
php artisan migrate

# Crear índices para performance
php artisan db:seed --class=SecurityIndexSeeder
```

### Paso 4: Registrar Middlewares
```php
// En app/Http/HttpKernel.php
protected array $middleware = [
    EnforceHttpsWithHsts::class,
    CsrfProtectionMiddleware::class,
    // resto...
];
```

### Paso 5: Tests
```bash
php artisan test tests/Unit/Security/SecurityTest.php
```

---

## 📡 Endpoints Seguros

### Sin CSRF (Webhooks Verificados)
```
POST /api/webhooks/amazon-ses          # Signature required
POST /api/webhooks/amazon-sns          # Signature required
POST /api/webhooks/freeswitch          # Signature required
POST /auth/login                       # No CSRF needed
POST /auth/register                    # No CSRF needed
```

### Con CSRF (API Autenticada)
```
POST   /api/campaigns                  # CSRF token required
PUT    /api/campaigns/{id}             # CSRF token required
DELETE /api/campaigns/{id}             # CSRF token required
POST   /api/users                      # CSRF token required
DELETE /api/users/{id}                 # CSRF token required
```

---

## 🔑 Manejo de Secretos

### Obtener Secreto
```php
use App\Services\SecretsManager;

$manager = app(SecretsManager::class);
$secret = $manager->get('database-password');
```

### Guardar Secreto
```php
$manager->put('my-secret', 'secret-value');
```

### Rotar Secreto
```php
$manager->rotate('old-secret', 'new-value');
```

---

## 📊 Auditoría

### Registrar Evento
```php
use App\Services\SecurityAuditLogger;

SecurityAuditLogger::log('resource.created', [
    'resource_type' => 'campaign',
    'resource_id' => '123',
]);
```

### Consultar Logs
```php
// Últimos eventos de usuario
$logs = SecurityAuditLogger::getUserLogs($userId);

// Eventos de seguridad (24h)
$events = SecurityAuditLogger::getRecentSecurityEvents();

// Intentos fallidos
$failed = SecurityAuditLogger::getFailedAuthAttempts();

// Dashboard
$dashboard = SecurityAuditLog::getSecurityDashboard($tenantId);
```

---

## ⚙️ Configuración

### CSRF
```php
// config/security.php
'csrf' => [
    'enabled' => true,
    'token_name' => 'X-CSRF-Token',
    'exclude_paths' => [
        'api/*/webhooks/*',
    ],
],
```

### Webhooks
```php
'webhooks' => [
    'enabled' => true,
    'providers' => [
        'amazon-ses' => [...],
        'amazon-sns' => [...],
    ],
],
```

### HTTPS
```php
'https' => [
    'enforce' => env('APP_ENV') === 'production',
    'hsts' => [
        'max_age' => 31536000,
        'include_subdomains' => true,
        'preload' => true,
    ],
],
```

### Audit
```php
'audit' => [
    'enabled' => true,
    'retention_days' => 90,
    'alerts' => [
        'enabled' => true,
        'channel' => 'mail',
    ],
],
```

---

## 🧪 Tests

```bash
# Todos los tests de seguridad
php artisan test tests/Unit/Security/

# Test específico
php artisan test tests/Unit/Security/SecurityTest::testCsrfTokenVerification

# Con output
php artisan test tests/Unit/Security/ --stop-on-failure -v
```

---

## 🩺 Monitoreo

### Ver Intentos Fallidos en Última Hora
```sql
SELECT user_id, COUNT(*) as attempts
FROM security_audit_logs
WHERE action = 'auth.failed_login'
  AND timestamp >= NOW() - INTERVAL 1 HOUR
GROUP BY user_id
HAVING attempts >= 5;
```

### Ver Dashboard de Seguridad
```php
// En endpoint
$dashboard = SecurityAuditLog::getSecurityDashboard($tenantId, 7);
```

### Detectar Actividad Sospechosa
```php
$isSuspicious = SecurityAuditLog::checkSuspiciousActivity(
    userId: $user->id,
    failedAttemptThreshold: 5
);
```

---

## 🔧 Troubleshooting

### CSRF Token Mismatch (419)
```
Problema: Frontend no está enviando token
Solución: 
  1. Verificar que X-CSRF-Token header se envía
  2. Token debe coincidir con session csrf_token
  3. En tests: session(['csrf_token' => $token])
```

### Webhook Signature Invalid (401)
```
Problema: Firma de webhook no valida
Solución:
  1. Verificar secreto es correcto en AWS
  2. Payload no fue modificado
  3. Algoritmo es SHA256
  4. Comparar signature exactamente
```

### Secrets Manager Error
```
Problema: "Failed to fetch secret"
Solución:
  1. AWS_SECRETS_ENABLED=true en .env
  2. IAM policy tiene permisos
  3. Region correcta
  4. Fallback a .env si AWS no disponible
```

### HTTPS Redirect Loop
```
Problema: Redirect loop HTTP -> HTTPS
Solución:
  1. Nginx no debe hacer double redirect
  2. APP_HTTPS_ENFORCED=true solo en production
  3. Verificar X-Forwarded-Proto header en load balancer
```

---

## 📝 Ejemplos de Código

### Frontend: CSRF Token

```javascript
// 1. Obtener token al cargar la página
async function getCsrfToken() {
  const response = await fetch('/csrf-token');
  const data = await response.json();
  return data.csrf_token;
}

// 2. Guardar en localStorage
const token = await getCsrfToken();
localStorage.setItem('csrf_token', token);

// 3. Usar en requests
async function createCampaign(campaignData) {
  const token = localStorage.getItem('csrf_token');
  
  const response = await fetch('/api/campaigns', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': token,
    },
    body: JSON.stringify(campaignData),
  });
  
  return response.json();
}
```

### Backend: Security Logging

```php
// En CampaignController
public function store(StoreCampaignRequest $request): JsonResponse
{
    $campaign = Campaign::create($request->validated());

    // Log creación
    SecurityAuditLogger::logResourceModification(
        action: 'created',
        resourceType: 'campaign',
        resourceId: $campaign->id,
        changes: $request->only(['name', 'subject']),
        status: 'success'
    );

    return response()->json(['data' => $campaign], 201);
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

public function destroy(Campaign $campaign): JsonResponse
{
    $campaign->delete();

    // Log eliminación
    SecurityAuditLogger::logCampaignDeleted($campaign->id);

    return response()->json(['message' => 'Deleted']);
}
```

### Webhook Handler

```php
// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    #[Route('POST', '/webhooks/amazon-ses')]
    public function handleSes(Request $request): JsonResponse
    {
        // Firma ya fue verificada por middleware
        $payload = $request->json()->all();

        // Log evento
        SecurityAuditLogger::logSecurityEvent(
            event: 'webhook.amazon_ses.received',
            details: [
                'message_id' => $payload['Mail']['MessageId'] ?? null,
                'event_type' => $payload['eventType'] ?? null,
            ]
        );

        // Procesar
        dispatch(new ProcessSesEvent($payload));

        return response()->json(['status' => 'processed']);
    }
}
```

---

## 🛡️ Checklist de Seguridad

### Antes de Deploy
- [ ] CSRF middleware registrado
- [ ] Webhook signature verificado
- [ ] HTTPS en producción
- [ ] AWS Secrets configurado
- [ ] Audit logging activo
- [ ] Tests pasos
- [ ] SSL certificate válido
- [ ] HSTS header presente
- [ ] Secretos no en .env
- [ ] Logging de eventos sensibles

### En Producción
- [ ] Monitorear audit logs diariamente
- [ ] Revisar intentos fallidos de login
- [ ] Rotar secretos mensualmente
- [ ] Actualizar dependencies
- [ ] Backups configurados
- [ ] Alertas de seguridad activas
- [ ] HTTPS renewal reminders
- [ ] Rate limits ajustados

---

## 📞 Soporte

- **Docs Completas**: `docs/SECURITY_CRITICAL.md`
- **Resumen**: `docs/SECURITY_IMPLEMENTATION_SUMMARY.md`
- **Código**: Mirar archivos en `app/Http/Middleware/` y `app/Services/`
- **Tests**: `tests/Unit/Security/SecurityTest.php`

---

## ✨ Resultado

```
✅ CSRF Protection           Implementado
✅ Webhook Verification      Implementado
✅ HTTPS/HSTS               Implementado
✅ Secrets Management       Implementado
✅ Audit Logging            Implementado
✅ Tests                    Incluidos
✅ Documentación            Completa

Status: PRODUCTION READY 🚀
```

