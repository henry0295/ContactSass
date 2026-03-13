# Middleware Architecture Summary

## 📋 Overview

Implementamos un sistema completo de middleware HTTP que proporciona:

✅ **Seguridad**: Headers de seguridad, CORS, validación de versiones  
✅ **Validación**: Tenant, permisos, autenticación  
✅ **Performance**: Rate limiting, logging  
✅ **Transformación**: Respuestas estandarizadas  

---

## 📂 Archivos Creados

### Middlewares (8 archivos)
```
app/Http/Middleware/
├── RequestLoggingMiddleware.php      # Logging y tracking
├── SecurityHeaders.php                # Headers de seguridad
├── CorsMiddleware.php                 # CORS handling
├── ValidateApiVersionMiddleware.php   # Validación de versión
├── RateLimitMiddleware.php            # Rate limiting (token bucket)
├── ValidateTenantMiddleware.php       # Validación de tenant
├── ValidateUserPermissionsMiddleware.php  # Validación de permisos
└── ResponseTransformMiddleware.php    # Transformación de respuestas
```

### Configuración
```
config/
└── cors.php                           # Configuración CORS
```

### Kernel
```
app/Http/
└── HttpKernel.php                     # Registro y organización
```

### Tests
```
tests/Unit/Http/Middleware/
└── MiddlewareTest.php                 # Suite de tests
```

### Documentación
```
docs/
└── middleware.md                      # Documentación completa
```

---

## 🔄 Flujo de Ejecución

```
Request
  ↓
1. RequestLoggingMiddleware (inicia tracking)
  ↓
2. SecurityHeaders (adjunta security headers)
  ↓
3. CorsMiddleware (valida CORS)
  ↓
4. ValidateApiVersionMiddleware (valida versión)
  ↓
5. RateLimitMiddleware (rate limiting)
  ↓
6. [Route-specific middleware: auth, tenant, permissions]
  ↓
7. Controller
  ↓
8. ResponseTransformMiddleware (estandariza response)
  ↓
9. RequestLoggingMiddleware (completa logging)
  ↓
Response
```

---

## 🛡️ Características Principales

### 1️⃣ Request Logging
- ID único para cada request (ULID)
- Tracking completo: method, path, IP, user-agent
- Cálculo de duración
- Niveles de log basados en status code

### 2️⃣ Seguridad
- Headers anti-clickjacking
- MIME type sniffing prevention
- CSP (Content Security Policy)
- Referrer policy
- Permissions policy

### 3️⃣ CORS
- Whitelist de orígenes permitidos
- Soporte para patrones wildcard
- Manejo de preflight requests
- Configuración por environment

### 4️⃣ Versioning
- Soporta 1.0 y 2.0
- Múltiples formas de especificar versión
- Validación automática
- Respuestas informativas

### 5️⃣ Rate Limiting
- Implementación token bucket con Redis
- Identificación por usuario o IP
- Diferentes límites por ruta
- Headers informativos

### 6️⃣ Multi-Tenancy
- Validación de tenant en cada request
- Asignación a request object
- Comprobación de estado activo
- Extrae de route param o header

### 7️⃣ Control de Acceso
- Validación de autenticación
- Roles basados (admin, manager, operator, viewer)
- Permisos granulares
- Validación de tenant scope

### 8️⃣ Respuestas Estandarizadas
- Formato consistente: success, data, timestamp
- Códigos de error normalizados
- Manejo de errores centralizado

---

## 💻 Uso en Routes

### Básico
```php
Route::post('/campaigns', [CampaignController::class, 'store']);
```

### Con Rate Limiting
```php
Route::post('/campaigns', [CampaignController::class, 'store'])
    ->middleware('rate.limit:100/60');
```

### Con Tenant
```php
Route::group(['prefix' => 'tenants/{tenant_id}', 'middleware' => 'validate.tenant'], function () {
    Route::post('/campaigns', [CampaignController::class, 'store']);
});
```

### Con Autenticación y Permisos
```php
Route::post('/campaigns', [CampaignController::class, 'store'])
    ->middleware('auth:sanctum')
    ->middleware('validate.permissions:campaigns.create');
```

### Combinado
```php
Route::group([
    'prefix' => 'tenants/{tenant_id}',
    'middleware' => ['validate.tenant', 'auth:sanctum'],
], function () {
    Route::post('/campaigns', [CampaignController::class, 'store'])
        ->middleware('validate.permissions:campaigns.create')
        ->middleware('rate.limit:200/60');
});
```

---

## 📊 Response Examples

### Success
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "My Campaign"
  },
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### Error
```json
{
  "success": false,
  "error": "Insufficient permissions",
  "code": "INSUFFICIENT_PERMISSIONS",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

### Rate Limited
```json
{
  "success": false,
  "error": "Too many requests",
  "code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 30,
  "timestamp": "2024-01-01T12:00:00Z"
}
```

---

## 🔧 Configuración Environment

```bash
# config/.env o config/.env.example
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
LOG_CHANNEL=stack
LOG_LEVEL=debug
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## ✅ Tests Incluidos

```bash
# Ejecutar todos los tests
php artisan test tests/Unit/Http/Middleware/MiddlewareTest.php

# Tests cubiertos:
- Security headers adjuntos correctamente
- CORS validación de origenes
- Versión API validación
- Tenant validación y estado
- Rate limiting funcionamiento
- Rate limit exceeded (429)
```

---

## 🚀 Próximos Pasos

1. **Integración en routes/api.php**: Aplicar middleware a las rutas
2. **Environment Setup**: Configurar CORS_ALLOWED_ORIGINS
3. **Redis Setup**: Configurar redis para rate limiting
4. **Monitoring**: Configurar logs y alertas
5. **Load Testing**: Probar bajo carga

---

## 📚 Referencias

- [docs/middleware.md](middleware.md) - Documentación completa
- [app/Http/HttpKernel.php](../app/Http/HttpKernel.php) - Registro de middleware
- [config/cors.php](../../config/cors.php) - Configuración CORS
- Tests: [tests/Unit/Http/Middleware/MiddlewareTest.php](../../../tests/Unit/Http/Middleware/MiddlewareTest.php)

---

## 🎯 Beneficios Alcanzados

✨ **Seguridad Mejorada**: Headers seguros, CORS validado, rate limiting  
✨ **Debugging Facilitado**: Request IDs únicos para tracking  
✨ **Multi-tenancy**: Validación automática de tenant  
✨ **Control de Acceso**: RBAC implementado y listo para usar  
✨ **Consistencia**: Respuestas estandarizadas en toda la API  
✨ **Performance**: Rate limiting y logging preparado  

---
