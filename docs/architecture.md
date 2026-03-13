# ContactSass Backend Architecture (Laravel + PostgreSQL + Redis)

## 1) High-level architecture

```text
┌──────────────────────────────────────────────────────────────────┐
│                          Web Dashboard / API Clients             │
└───────────────────────────────┬──────────────────────────────────┘
                                │ HTTPS + JWT
┌───────────────────────────────▼──────────────────────────────────┐
│                         Laravel API Layer                        │
│  - Auth + RBAC (platform_admin, tenant_admin, operator)         │
│  - Tenant context resolution                                     │
│  - Contacts, campaigns, billing, reports                         │
└───────────────┬──────────────────────┬───────────────────────────┘
                │                      │
                │                      │
     ┌──────────▼──────────┐   ┌──────▼─────────────────────────┐
     │   PostgreSQL         │   │ Redis (queue + cache + locks) │
     │ tenant-isolated data │   │ batch queues + rate limiter   │
     └──────────┬──────────┘   └──────┬─────────────────────────┘
                │                     │
                │                     │ queued jobs
                │             ┌───────▼─────────────────────────────────────┐
                │             │ Worker Cluster (horizontally scalable)      │
                │             │ - Email workers (SES)                        │
                │             │ - SMS workers (SNS)                          │
                │             │ - Voice workers (FreeSWITCH ESL)             │
                │             └───────┬──────────────┬───────────────────────┘
                │                     │              │
                │          ┌──────────▼───────┐ ┌────▼───────────────┐
                │          │ Amazon SES       │ │ Amazon SNS         │
                │          └──────────────────┘ └────────────────────┘
                │
                │          ┌──────────────────┐
                └─────────►│ FreeSWITCH (ESL) │
                           └──────────────────┘
```

### Core runtime responsibilities
- **API layer** validates requests, resolves tenant, persists campaign definition.
- **Campaign engine** slices contacts into deterministic batches (default 1,000), enqueues `campaign-batch` jobs, and tracks throughput.
- **Workers** are channel-specific and independent for isolated scaling.
- **Integrations** are modular adapters (SES/SNS/FreeSWITCH).
- **Delivery events** are persisted asynchronously and feed usage + analytics.

### Scaling strategy
- Horizontal worker autoscaling per queue depth.
- Queue partitioning by channel (`email`, `sms`, `voice`).
- Provider-level token bucket limits stored in Redis.
- Idempotent job processing with `message_uuid` unique constraint.
- Retry with exponential backoff and dead-letter queues.

---

## 2) Laravel project structure (proposed)

```text
app/
  Campaign/
    DTO/
    Enums/
    Jobs/
    Services/
  Contacts/
  Messaging/
    Contracts/
    DTO/
    Enums/
    Jobs/
    Workers/
  Billing/
  Reports/
  Integrations/
  Support/
config/
  messaging.php
database/
  migrations/
  schema.sql
routes/
  api.php
```

### Module ownership
- `Campaign`: campaign definition, scheduling, batching, orchestration.
- `Messaging`: channel-specific send jobs + worker logic.
- `Integrations`: provider APIs only, no business logic.
- `Billing`: usage aggregation and billing cycle calculations.
- `Reports`: query services and reporting DTOs.

---

## 3) Queue topology

- `campaign-batch` queue: campaign slicing + message generation jobs.
- `email-send`, `sms-send`, `voice-send`: channel send jobs.
- `delivery-events`: provider callbacks/webhook normalization.

Each queue can be deployed with dedicated worker pools and autoscaling policies.

---

## 4) Security and tenancy

- JWT auth for APIs.
- RBAC gates/policies based on role in `tenant_users`.
- Every domain query filtered by `tenant_id`.
- Optional PostgreSQL RLS can enforce tenant boundary in depth.

---

## 5) Operational recommendations

- Use Horizon for Redis queue observability.
- Emit structured logs with `tenant_id`, `campaign_id`, `message_id`, `channel`.
- Expose metrics: queue lag, send latency, provider error ratio, retry counts.
- Add circuit breakers per provider adapter.
