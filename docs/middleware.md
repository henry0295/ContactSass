# HTTP Middleware Architecture

Esta documentación describe el sistema de middleware HTTP implementado en la aplicación ContactSass.

## Descripción General

El middleware maneja:
- **Seguridad**: Headers de seguridad, CORS, validación de versiones API
- **Validación**: Tenant, permisos de usuario, autenticación
- **Performance**: Rate limiting, logging de requests
- **Transformación**: Estandarización de respuestas

## Middlewares Globales

Todos estos middlewares se ejecutan en **TODAS** las requests:

### 1. RequestLoggingMiddleware
**Ubicación**: `app/Http/Middleware/RequestLoggingMiddleware.php`

**Responsabilidades**:
- Genera ID único de request (ULID)
- Registra información de request entrante (método, path, IP, user-agent)
- Calcula tiempo de ejecución
- Registra información de response (status, duración)
- Adjunta `X-Request-ID` header a la response

**Headers de Request**:
- `X-Request-ID` (opcional): ID personalizado para seguimiento

**Headers de Response**:
- `X-Request-ID`: ID único asignado por el middleware

**Log Output**:
```json
{
  "request_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "method": "POST",
  "path": "/api/campaigns",
  "ip": "192.168.1.1",
  "user_id": 123,
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### 2. SecurityHeaders
**Ubicación**: `app/Http/Middleware/SecurityHeaders.php`

**Headers Implementados**:
- `X-Frame-Options: SAMEORIGIN` - Previene clickjacking
- `X-Content-Type-Options: nosniff` - Previene MIME type sniffing
- `X-XSS-Protection: 1; mode=block` - Protección XSS en navegadores antiguos
- `Referrer-Policy: strict-origin-when-cross-origin` - Previene referrer leakage
- `Content-Security-Policy` - Policy restrictiva para scripts y recursos
- `Permissions-Policy` - Deshabilita acceso a cámara, micrófono, geolocalización
- `X-Permitted-Cross-Domain-Policies: none` - No permite cross-domain policies

### 3. CorsMiddleware
**Ubicación**: `app/Http/Middleware/CorsMiddleware.php`

**Configuración**: `config/cors.php`

**Responsabilidades**:
- Valida origen de request contra lista permitida
- Soporta patrones wildcards (ej: `*.example.com`)
- Maneja preflight requests (OPTIONS)
- Adjunta headers CORS a responses

**Headers de Response**:
```
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
```

**Configuración Recomendada**:
```php
// En .env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
```

### 4. ValidateApiVersionMiddleware
**Ubicación**: `app/Http/Middleware/ValidateApiVersionMiddleware.php`

**Versiones Soportadas**: 1.0, 2.0

**Formas de Especificar Versión** (en orden de prioridad):
1. Header `Accept`: `application/vnd.api+json; version=2.0`
2. Header `X-API-Version: 2.0`
3. Query parameter: `api_version=2.0`
4. Default: 2.0

**Response Cuando Versión no Soportada**:
```json
{
  "success": false,
  "error": "API version '99.0' is not supported",
  "code": "UNSUPPORTED_API_VERSION",
  "supported_versions": ["1.0", "2.0"],
  "current_version": "2.0"
}
```

### 5. RateLimitMiddleware
**Ubicación**: `app/Http/Middleware/RateLimitMiddleware.php`

**Implementación**: Token bucket con Redis

**Uso**:
```php
// En routes/api.php
Route::post('/campaigns', [CampaignController::class, 'store'])
    ->middleware('rate.limit:100/60'); // 100 requests por 60 segundos
```

**Identificadores**:
- Usuario autenticado: `user:{user_id}`
- Usuario anónimo: `ip:{ip_address}`

**Response Cuando Límite Excedido**:
```json
{
  "success": false,
  "error": "Too many requests",
  "code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 30,
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Headers de Response**:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1704110400
Retry-After: 30
```

## Middleware de Validación (Tenant & Permissions)

### 6. ValidateTenantMiddleware
**Ubicación**: `app/Http/Middleware/ValidateTenantMiddleware.php`

**Responsabilidades**:
- Extrae `tenant_id` de:
  1. Route parameter: `{tenant_id}`
  2. Header: `X-Tenant-ID`
- Valida que tenant exista y esté activo
- Ataching tenant object a request

**Respuesta Cuando Tenant Inválido**:
```json
{
  "success": false,
  "error": "Tenant not found or inactive",
  "code": "INVALID_TENANT"
}
```

**Uso**:
```php
// En routes/api.php
Route::group(['prefix' => 'tenants/{tenant_id}', 'middleware' => 'validate.tenant'], function () {
    Route::post('/campaigns', [CampaignController::class, 'store']);
});
```

### 7. ValidateUserPermissionsMiddleware
**Ubicación**: `app/Http/Middleware/ValidateUserPermissionsMiddleware.php`

**Roles y Permisos**:

| Role | Permisos |
|------|----------|
| `admin` | `campaigns.create`, `campaigns.read`, `campaigns.update`, `campaigns.delete`, `campaigns.send`, `users.manage`, `settings.configure`, `reports.view` |
| `manager` | `campaigns.create`, `campaigns.read`, `campaigns.update`, `campaigns.send`, `reports.view` |
| `operator` | `campaigns.read`, `campaigns.send` |
| `viewer` | `campaigns.read`, `reports.view` |

**Responsabilidades**:
- Valida que usuario esté autenticado
- Valida que usuario pertenezca al tenant
- Valida que usuario tenga permisos requeridos

**Uso**:
```php
// Requerir cualquiera de estos permisos
Route::post('/users', [UserController::class, 'store'])
    ->middleware('validate.permissions:users.manage,settings.configure');

// Con autenticación
Route::post('/campaigns', [CampaignController::class, 'store'])
    ->middleware('auth:sanctum')
    ->middleware('validate.permissions:campaigns.create');
```

**Respuesta Cuando Usuario no Autenticado**:
```json
{
  "success": false,
  "error": "Unauthenticated",
  "code": "UNAUTHENTICATED"
}
```

**Respuesta Cuando Permisos Insuficientes**:
```json
{
  "success": false,
  "error": "Insufficient permissions",
  "code": "INSUFFICIENT_PERMISSIONS",
  "required_permissions": ["users.manage"]
}
```

## 8. ResponseTransformMiddleware
**Ubicación**: `app/Http/Middleware/ResponseTransformMiddleware.php`

**Formato Estandarizado**:
```json
{
  "success": true,
  "data": { /* response data */ },
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

## Orden de Ejecución

El middleware se ejecuta en este orden:

1. **RequestLoggingMiddleware** - Inicia logging
2. **SecurityHeaders** - Adjunta security headers
3. **CorsMiddleware** - Maneja CORS
4. **ValidateApiVersionMiddleware** - Valida versión
5. **RateLimitMiddleware** - Rate limiting
6. **ResponseTransformMiddleware** - Transforma response

## Configuración en Routes

### Rutas de API Tenant
```php
Route::group([
    'prefix' => 'tenants/{tenant_id}',
    'middleware' => ['validate.tenant', 'auth:sanctum'],
], function () {
    Route::post('/campaigns', [CampaignController::class, 'store'])
        ->middleware('validate.permissions:campaigns.create');
});
```

### Rutas Administrativas
```php
Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:sanctum', 'admin'],
], function () {
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
```

## Environment Variables

```bash
# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com

# Rate Limiting (configurables en rutas)
# Ej: 1000 requests por 60 segundos
RATE_LIMIT_DEFAULT=1000/60

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

## Testing

Ejecuta los tests de middleware:

```bash
php artisan test tests/Unit/Http/Middleware/MiddlewareTest.php
```

## Mejores Prácticas

1. **CORS**: Especifica orígenes permitidos, nunca uses `*` en producción
2. **Rate Limiting**: Ajusta límites según tus casos de uso
3. **Tenant Validation**: Siempre valida tenant en rutas multi-tenant
4. **Permissions**: Usa middleware granular para control de acceso
5. **Logging**: Monitorea requestId para debug

## Troubleshooting

### CORS Bloqueado
- Verifica `CORS_ALLOWED_ORIGINS` en `.env`
- Checkea `Origin` header en request

### Rate Limit Alcanzado
- Verificar `X-RateLimit-Remaining` header
- Respetar `Retry-After` header

### Tenant no Encontrado
- Verifica que tenant exists en database
- Checkea que `is_active` es true

### Permisos Insuficientes
- Verificar rol de usuario con tenant
- Revisa rol tiene permiso requerido
