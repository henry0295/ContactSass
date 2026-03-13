# 🚀 ContactSass Setup Guide

Guía completa para configurar y ejecutar ContactSass en tu máquina local.

## 📋 Requisitos Previos

Asegúrate de tener instalado:
- PHP 8.2+ (`php --version`)
- Composer (`composer --version`)
- Node.js 16+ (`node --version`)
- npm (`npm --version`)
- PostgreSQL 12+ (local o Docker)
- Redis (local o Docker)
- Git

## 🐳 Opción 1: Quick Start con Docker

### 1.1 Usar docker-compose (Recomendado)

```bash
# Clonar repositorio
git clone https://github.com/tu-usuario/ContactSass.git
cd ContactSass

# Copiar archivo de entorno
cp .env.example .env

# Iniciar servicios (Backend + PostgreSQL + Redis)
docker-compose up -d

# Ejecutar migraciones
docker-compose exec app php artisan migrate

# Instalar dependencias frontend
npm install

# Iniciar servidor frontend
npm run dev
```

La app estará en:
- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api
- Database: localhost:5432

---

## 🖥️ Opción 2: Instalación Manual Local

### 2.1 Backend (Laravel)

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-usuario/ContactSass.git
cd ContactSass

# 2. Instalar dependencias PHP
composer install

# 3. Copiar archivo de entorno
cp .env.example .env

# 4. Actualizar variables de entorno
nano .env
# Cambiar:
# - DB_HOST=127.0.0.1
# - DB_DATABASE=contactsass_dev
# - APP_KEY=base64:... (se genera abajo)

# 5. Generar clave de aplicación
php artisan key:generate

# 6. Crear base de datos
createdb contactsass_dev  # En PostgreSQL

# 7. Ejecutar migraciones
php artisan migrate

# 8. Iniciar servidor Laravel
php artisan serve
# El backend estará en http://localhost:8000
```

### 2.2 Frontend (Vue 3)

```bash
# 1. En otra terminal, instalar dependencias
npm install

# 2. Copiar configuración frontend
cp .env.frontend .env.local

# 3. Iniciar servidor Vite
npm run dev
# El frontend estará en http://localhost:5173
```

### 2.3 Queue Worker (para jobs)

```bash
# En otra terminal, iniciar el worker de colas
php artisan queue:work redis --queue=campaign-batch,email-send,sms-send,voice-send

# O para development:
php artisan queue:listen
```

---

## ⚙️ Configuración de Servicios

### 3.1 PostgreSQL Local

```bash
# Instalación en macOS (Homebrew)
brew install postgresql
brew services start postgresql

# Crear usuario y base de datos
psql postgres
CREATE ROLE contactsass_user WITH LOGIN PASSWORD 'password';
CREATE DATABASE contactsass_dev OWNER contactsass_user;
GRANT ALL PRIVILEGES ON DATABASE contactsass_dev TO contactsass_user;
\q
```

### 3.2 Redis Local

```bash
# Instalación en macOS (Homebrew)
brew install redis
brew services start redis

# Verificar conexión
redis-cli ping
# Debe responder: PONG
```

### 3.3 AWS Services (Opcional para Testing)

Para usar Email, SMS, Voice necesitas credenciales AWS:

```bash
# 1. Crear cuenta AWS (free tier disponible)
# 2. Obtener Access Key + Secret Key
# 3. Actualizar en .env:
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1

# 4. Configurar SNS Topics (en AWS Console)
SES_SNS_TOPIC_ARN=arn:aws:sns:us-east-1:xxx:ses-events
SNS_TOPIC_ARN=arn:aws:sns:us-east-1:xxx:sms-events
```

---

## 🧪 Testing

### Crear usuario de prueba

```bash
# Opción 1: Via artisan tinker
php artisan tinker
> User::factory()->create(['email' => 'test@example.com', 'password' => 'password'])
> exit

# Opción 2: Usar API
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password",
    "password_confirmation": "password",
    "tenant_name": "Test Tenant"
  }'
```

### Acceder a la aplicación

1. Abre http://localhost:5173
2. Selecciona "Register"
3. Ingresa datos del usuario
4. ¡Hecho! Dashboard abierto

---

## 📝 Variables de Entorno Clave

```env
# App
APP_NAME=ContactSass
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=contactsass_dev
DB_USERNAME=contactsass_user
DB_PASSWORD=password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queues
QUEUE_CONNECTION=redis

# AWS (opcional)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1

# Rate Limits
RATE_LIMIT_EMAILS=1000
RATE_LIMIT_SMS=600
RATE_LIMIT_CALLS=120

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

---

## 🐛 Troubleshooting

### Error: "Connection refused" Database

```bash
# Verificar que PostgreSQL está corriendo
ps aux | grep postgres
# Si no aparece:
brew services start postgresql
```

### Error: "Redis connection refused"

```bash
# Verificar que Redis está corriendo
ps aux | grep redis
# Si no aparece:
brew services start redis
```

### Error: "Class not found"

```bash
# Regenerar autoloader de Composer
composer dump-autoload

# O completo:
composer install --no-interaction
```

### Error: "npm packages not found"

```bash
# Reinstalar con limpieza
rm -rf node_modules package-lock.json
npm install
```

### Frontend no se conecta al Backend

```bash
# Verificar que Backend está corriendo en puerto 8000
lsof -i :8000

# Si no está, inicia:
php artisan serve

# Actualizar proxy en vite.config.ts si es necesario
```

---

## 📊 Verificación de Setup

### Checklist de funcionamiento

```bash
# 1. Backend corriendo
curl http://localhost:8000/api/auth/me
# Debe retornar: 401 (no autenticado - esperado)

# 2. Redis funcionando
redis-cli ping
# Respuesta: PONG

# 3. Database funcionando
psql contactsass_dev -c "SELECT 1"
# Respuesta: 1

# 4. Frontend accesible
curl http://localhost:5173
# Respuesta: HTML index

# 5. Migraciones correctas
php artisan migrate:status
# Debe mostrar todas las migraciones "Yes"
```

---

## 🎮 Próximos Pasos

### Si todo funciona:

1. **Crear una campaña:**
   - Dashboard → New Campaign
   - Selecciona channel (email/sms/voice)
   - Escribe template con {{first_name}}

2. **Importar contactos:**
   - Contacts → Import Contacts
   - Sube CSV con: first_name,last_name,email,phone_e164

3. **Ejecutar campaña:**
   - Campaigns → Selecciona tu campaña
   - Click "Start"
   - Monitor en dashboard

4. **Ver eventos de entrega:**
   - Reports → Campaign Analytics
   - Observa sent/bounce/failed counts

---

## 📚 Documentación Adicional

- [README Principal](README_PRODUCTION.md) - Descripción general
- [Estructura del Proyecto](docs/project-structure.md) - Layout de archivos
- [Arquitectura](docs/architecture.md) - Diseño técnico
- [API Endpoints](README_PRODUCTION.md#-api-endpoints) - Full endpoint list

---

## 🆘 Soporte

Si encuentras problemas:

1. **Revisa logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Activa debug mode:**
   ```env
   APP_DEBUG=true
   ```

3. **Ejecuta tinker:**
   ```bash
   php artisan tinker
   > DB::connection()->getPDO()
   > exit
   ```

4. **Crea issue en GitHub** con:
   - Tu SO
   - Versión PHP/Node
   - Error exacto
   - Pasos para reproducir

---

## ✅ Checklist Final

- [ ] PHP 8.2+ instalado
- [ ] Composer actualizado (`composer self-update`)
- [ ] Node.js 16+ instalado  
- [ ] PostgreSQL corriendo
- [ ] Redis corriendo
- [ ] `.env` configurado
- [ ] `composer install` ejecutado
- [ ] `php artisan migrate` ejecutado
- [ ] `npm install` ejecutado
- [ ] Backend corriendo en :8000
- [ ] Frontend corriendo en :5173
- [ ] Puedo acceder a http://localhost:5173
- [ ] Puedo hacer login/register

---

**Happy coding! 🚀**

¿Preguntas? Crea un issue en GitHub o contacta support@contactsass.com
