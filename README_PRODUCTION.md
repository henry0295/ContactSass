# ContactSass - Multi-Tenant Broadcasting SaaS Platform

Una plataforma SaaS completa para enviar campañas de Email, SMS y Voice Broadcasting con arquitectura multi-tenant, escalable y segura.

## 🎯 Características

### Backend (Laravel 11 + PHP 8.2)
- ✅ **Multi-tenant architecture** - Aislamiento de datos por tenant
- ✅ **Campaign management** - Crear, programar y ejecutar campañas
- ✅ **Contact management** - Importar y gestionar contactos
- ✅ **Multi-channel messaging** - Email (SES), SMS (SNS), Voice (FreeSWITCH)
- ✅ **Real-time delivery tracking** - Webhooks para eventos de entrega
- ✅ **Rate limiting** - Sliding window con Redis
- ✅ **Template rendering** - Personalización con {{variables}}
- ✅ **API REST** - Endpoints completamente documentados
- ✅ **Authentication** - Laravel Sanctum + JWT tokens

### Frontend (Vue 3 + Vite)
- ✅ **Responsive UI** - Tailwind CSS
- ✅ **State management** - Pinia stores
- ✅ **TypeScript** - Type-safe components
- ✅ **Vue Router** - Client-side routing
- ✅ **Reusable components** - Button, FormInput, DataTable, etc.

### Infrastructure
- AWS ECS Fargate (containers)
- RDS PostgreSQL (database)
- ElastiCache Redis (cache/queues)
- Amazon SES (email)
- Amazon SNS (SMS)
- FreeSWITCH (voice)

## 📋 Estructura del Proyecto

```
ContactSass/
├── app/
│   ├── Campaign/               # Campaign orchestration
│   ├── Http/                   # Controllers, middleware, kernels
│   ├── Integrations/           # AWS SES, SNS, FreeSWITCH
│   ├── Messaging/              # Message jobs & workers
│   ├── Models/                 # Eloquent models (7 core)
│   ├── Policies/               # Role-based access control
│   ├── Providers/              # Service providers
│   └── Support/                # TemplateRenderer, RateLimiter
├── config/
│   ├── auth.php                # Sanctum configuration
│   └── messaging.php           # Messaging config
├── database/
│   ├── migrations/             # 12 Eloquent migrations
│   └── schema.sql              # Full schema reference
├── routes/
│   └── api.php                 # API endpoints
├── src/ (Vue 3 Frontend)
│   ├── api/                    # Axios client
│   ├── components/             # Reusable Vue components
│   ├── layouts/                # Layout components
│   ├── pages/                  # Page components
│   ├── router/                 # Vue Router
│   ├── stores/                 # Pinia stores
│   └── styles/                 # CSS files
├── tests/                      # Test suite
├── bootstrap/                  # Laravel bootstrap
├── .env.example                # Environment template
├── composer.json               # PHP dependencies
├── package.json                # Node dependencies
├── vite.config.ts              # Vite configuration
├── tailwind.config.js          # Tailwind configuration
└── README.md                   # This file
```

## 🚀 Instalación Rápida

### Backend Setup

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Copiar archivo de configuración
cp .env.example .env

# 3. Generar clave de aplicación
php artisan key:generate

# 4. Ejecutar migraciones
php artisan migrate

# 5. Iniciar servidor Laravel
php artisan serve
```

### Frontend Setup

```bash
# 1. Instalar dependencias Node.js
npm install

# 2. Copiar archivo de configuración
cp .env.frontend .env.local

# 3. Iniciar servidor de desarrollo
npm run dev
```

La aplicación estará disponible en:
- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api

## 📚 Modelos de Datos

### Core Models (7 models)
- **Tenant** - Multi-tenant segregation
- **User** - User accounts con Sanctum tokens
- **TenantUser** - User-Tenant relationship con roles
- **Campaign** - Email/SMS/Voice campaigns
- **Contact** - Individual recipients
- **ContactList** - Grouped contacts
- **Message** - Individual message records
- **CampaignBatch** - Batch progress tracking
- **TenantSetting** - Tenant delivery preferences
- **TenantLimit** - Rate limit configuration
- **DeliveryEvent** - Provider webhook events

## 🔌 API Endpoints

### Authentication
```
POST   /api/auth/register          # Register new user
POST   /api/auth/login             # Login and get token
POST   /api/auth/logout            # Logout (revoke token)
POST   /api/auth/refresh           # Refresh token
GET    /api/auth/me                # Current user profile
```

### Campaigns (Multi-tenant)
```
GET    /api/tenants/{id}/campaigns                 # List campaigns
POST   /api/tenants/{id}/campaigns                 # Create campaign
GET    /api/tenants/{id}/campaigns/{campaignId}    # Get campaign
PUT    /api/tenants/{id}/campaigns/{campaignId}    # Update campaign
DELETE /api/tenants/{id}/campaigns/{campaignId}    # Delete campaign
POST   /api/tenants/{id}/campaigns/{campaignId}/start   # Start campaign
POST   /api/tenants/{id}/campaigns/{campaignId}/pause   # Pause campaign
```

### Contacts
```
GET    /api/tenants/{id}/contacts                  # List contacts
POST   /api/tenants/{id}/contacts                  # Create contact
PUT    /api/tenants/{id}/contacts/{contactId}      # Update contact
DELETE /api/tenants/{id}/contacts/{contactId}      # Delete contact
POST   /api/tenants/{id}/contacts/import           # Bulk import CSV/JSON
```

### Reports
```
GET    /api/tenants/{id}/reports/campaigns/{campaignId}  # Campaign analytics
GET    /api/tenants/{id}/reports/usage                   # Usage by channel
```

### Webhooks (Unprotected)
```
POST   /api/webhooks/ses           # Amazon SES events
POST   /api/webhooks/sns           # Amazon SNS events
POST   /api/webhooks/freeswitch    # FreeSWITCH call events
```

## 🔐 Security

- **Multi-tenant isolation** - EnsureTenantContext middleware
- **Role-based access** - Policies for Campaign/Contact operations
- **Token-based auth** - Laravel Sanctum with 90-day expiration
- **Webhook verification** - Signature validation from providers
- **Rate limiting** - Sliding window algorithm (Redis)
- **Input validation** - Form request validation
- **Exception handling** - Sanitized error responses

## 📞 Channels Supported

### Email (Amazon SES)
- Recipients: Up to 1000/min per tenant
- Template variables: {{first_name}}, {{last_name}}, {{email}}, custom fields
- Events: sent, bounce, complaint, delivery, open, click

### SMS (Amazon SNS)
- Recipients: Up to 600/min per tenant
- Supports E.164 phone format
- Events: success, failure

### Voice (FreeSWITCH)
- Recipients: Up to 120/min per tenant
- IVR scripts with {{variables}}
- Events: answered, busy, no_answer, failed

## 🗄️ Database Schema

12 Eloquent migrations handle:
- **Base tables**: tenants, users, tenant_users
- **Business tables**: campaigns, contacts, contact_lists
- **Messaging tables**: messages, campaign_batches, delivery_events
- **Settings tables**: tenant_settings, tenant_limits

All tables include:
- UUID primary keys
- Proper foreign keys with cascades
- Soft deletes for audit trail
- Timestamps (created_at, updated_at)
- Strategic indexes for performance

## 📊 Queue System

Job names and handlers:
- `campaign-batch` - DispatchCampaignBatchJob
- `email-send` - SendEmailMessageJob
- `sms-send` - SendSmsMessageJob  
- `voice-send` - SendVoiceMessageJob

All jobs include:
- Error classification (transient/permanent)
- Automatic retries for transient errors
- Delivery event recording
- Rate limit compliance

## 🛠️ Configuration

### .env Variables

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=postgres.default.svc.cluster.local
DB_PORT=5432
DB_DATABASE=contactsass

# Redis
REDIS_HOST=redis.default.svc.cluster.local
REDIS_PORT=6379

# AWS
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1

# Rate Limits (per minute)
RATE_LIMIT_EMAILS=1000
RATE_LIMIT_SMS=600
RATE_LIMIT_CALLS=120

# FreeSWITCH
FREESWITCH_HOST=freeswitch.default.svc.cluster.local
FREESWITCH_PORT=8021
FREESWITCH_PASSWORD=ClueCon
```

## 🧪 Testing

```bash
# Run test suite
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test
php artisan test tests/Unit/Campaign/CampaignEngineServiceTest.php
```

## 📦 Deployment

### Docker
```bash
# Build image
docker build -t contactsass:latest .

# Run container
docker run -p 8000:8000 contactsass:latest
```

### AWS ECS/Fargate
```bash
# Push image to ECR
aws ecr get-login-password | docker login --username AWS --password-stdin <account-id>.dkr.ecr.us-east-1.amazonaws.com
docker tag contactsass:latest <account-id>.dkr.ecr.us-east-1.amazonaws.com/contactsass:latest
docker push <account-id>.dkr.ecr.us-east-1.amazonaws.com/contactsass:latest

# Deploy with CloudFormation or Terraform
# (See infrastructure/ directory)
```

## 🔄 Campaign Workflow

1. **Create Campaign**
   - Set channel (email/sms/voice)
   - Create template with {{variables}}
   - Select contact list

2. **Schedule/Start**
   - API call to `/campaigns/{id}/start`
   - CampaignEngineService slices into batches

3. **Dispatch Batches**
   - DispatchCampaignBatchJob loads contacts
   - Renders templates with contact data
   - Builds channel-specific payloads
   - Enqueues send jobs

4. **Send Messages**
   - SendEmailMessageJob / SendSmsMessageJob / SendVoiceMessageJob
   - Rate-limited by SlidingWindowRateLimiter
   - Updates message status
   - Catches and classifies provider errors

5. **Track Delivery**
   - Webhooks from SES/SNS/FreeSWITCH
   - Update message status
   - Record DeliveryEvent

6. **Generate Reports**
   - Query delivery statistics
   - Calculate success rates
   - Track usage for billing

## 📖 Documentation

- [Architecture](docs/architecture.md)
- [AWS Infrastructure](docs/aws-infrastructure.md)
- [Project Structure](docs/project-structure.md)

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing`)
5. Create Pull Request

## 📄 License

MIT License - see LICENSE file for details

## 📧 Support

Para soporte, contacta a: support@contactsass.com

---

**Completado por:** GitHub Copilot  
**Fecha:** 2024  
**Stack:** Laravel 11 + Vue 3 + PostgreSQL + Redis + AWS
