# Project Structure

## Directory Organization

```
app/
  Campaign/          # Campaign orchestration and batching
    DTO/             # Data transfer objects
    Enums/           # Campaign-specific enums
    Jobs/            # Queue jobs for campaign processing
    Services/        # Business logic services
  Messaging/         # Channel-specific messaging
    Contracts/       # Provider interfaces
    DTO/             # Message data structures
    Enums/           # Message/channel enums
    Jobs/            # Send jobs per channel
    Workers/         # Worker implementations per channel
  Integrations/      # External provider adapters
  Support/           # Shared utilities (rate limiting, etc.)
  Http/Controllers/Api/  # API endpoints
config/              # Laravel configuration
  messaging.php      # Messaging-specific config
database/
  schema.sql         # PostgreSQL schema definition
  migrations/        # Laravel migrations (to be added)
docs/                # Architecture and design docs
routes/
  api.php            # API route definitions
```

## Module Responsibilities

### Campaign Module
- Campaign definition and lifecycle management
- Contact list batching (default 1000 per batch)
- Campaign orchestration via `CampaignEngineService`
- Enqueues `DispatchCampaignBatchJob` to `campaign-batch` queue

### Messaging Module
- Channel-specific send jobs: `SendEmailMessageJob`, `SendSmsMessageJob`, `SendVoiceMessageJob`
- Worker implementations: `EmailWorker`, `SmsWorker`, `VoiceWorker`
- Abstract base job: `AbstractSendMessageJob` for shared logic
- Channel provider contract: `ChannelProvider` interface

### Integrations Module
- Provider adapters only, no business logic
- `AmazonSesProvider`, `AmazonSnsProvider`, `FreeSwitchEslProvider`
- Implements `ChannelProvider` contract

### Support Module
- Cross-cutting utilities
- `RedisTokenBucketRateLimiter` for rate limiting

## Queue Topology

- `campaign-batch`: Campaign slicing and message generation
- `email-send`: Email delivery jobs
- `sms-send`: SMS delivery jobs
- `voice-send`: Voice call jobs
- `delivery-events`: Provider callback processing (future)

Each queue can scale independently with dedicated worker pools.

## Naming Conventions

- **Classes:** PascalCase with descriptive suffixes (`Service`, `Job`, `Worker`, `Provider`, `Controller`)
- **Files:** Match class names exactly
- **Namespaces:** Follow PSR-4 directory structure
- **Database:** snake_case for tables and columns, UUID primary keys
- **Enums:** Backed enums with string values matching database types

## Code Organization Principles

- Strict type declarations in all files
- Final classes by default (prevent inheritance unless designed for it)
- Readonly properties where applicable
- DTOs for data transfer between layers
- Service layer for business logic
- Jobs for async processing
- Workers for channel-specific send logic
- Providers for external API integration only
