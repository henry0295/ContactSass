# Technology Stack

## Core Technologies

- **Language:** PHP 8.2 with strict types (`declare(strict_types=1)`)
- **Framework:** Laravel (queue-based architecture)
- **Database:** PostgreSQL with UUID primary keys
- **Queue/Cache:** Redis (Laravel queues + rate limiting)
- **Storage:** AWS S3 (audio files and attachments)

## External Providers

- **Email:** Amazon SES
- **SMS:** Amazon SNS  
- **Voice:** FreeSWITCH via ESL (Event Socket Library)

## Infrastructure

- AWS-based deployment
- Horizontal worker scaling per queue/channel
- Multi-tenant data isolation

## Key Libraries & Patterns

- Laravel Facades (DB, Redis)
- AWS SDK for PHP (SesClient)
- Queue workers with channel-specific queues
- DTO pattern for data transfer
- Repository/Service layer separation
- Contract interfaces for provider abstraction

## Common Commands

### Queue Workers
```bash
# Start all queue workers
php artisan queue:work

# Start channel-specific workers
php artisan queue:work --queue=campaign-batch
php artisan queue:work --queue=email-send
php artisan queue:work --queue=sms-send
php artisan queue:work --queue=voice-send
```

### Database
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed
```

### Development
```bash
# Start development server
php artisan serve

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan queue:clear
```

## Configuration

Key environment variables in `.env`:
- `CAMPAIGN_BATCH_SIZE` (default: 1000)
- `AWS_DEFAULT_REGION`
- `SES_DEFAULT_FROM`
- `SNS_DEFAULT_SENDER_ID`
- `FREESWITCH_HOST`, `FREESWITCH_PORT`, `FREESWITCH_PASSWORD`

Configuration file: `config/messaging.php`
