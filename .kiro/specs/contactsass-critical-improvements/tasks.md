# Implementation Plan: ContactSass Critical Improvements

## Overview

This implementation plan covers 8 critical improvements to the ContactSass multi-tenant messaging platform:
1. Message Payload Population
2. Tenant Context Enforcement
3. Sliding Window Rate Limiting
4. Provider Error Handling
5. Eloquent Model Implementation
6. Transaction Safety
7. Template Rendering System
8. Idempotency Guarantees

The implementation follows a phased approach over 6 weeks, starting with foundational models, then infrastructure components, core logic enhancements, message sending improvements, comprehensive testing, and finally deployment.

## Tasks

- [ ] 1. Phase 1: Foundation - Eloquent Models and Traits
  - [x] 1.1 Create HasTenantScope trait for global tenant scoping
    - Create `app/Models/Concerns/HasTenantScope.php`
    - Implement `bootHasTenantScope()` method to add global scope
    - Implement `scopeForTenant()` query scope method
    - _Requirements: 5.10_
    - _Design: Section 4.4 - Base Model Trait_

  - [x] 1.2 Write unit tests for HasTenantScope trait
    - Test global scope filters by tenant_id when context is set
    - Test scopeForTenant() method
    - Test behavior when no tenant context is set
    - _Requirements: 5.10_

  - [x] 1.3 Create Tenant model with relationships
    - Create `app/Models/Tenant.php`
    - Define fillable fields: name, slug, timezone, is_active
    - Add UUID trait and casts
    - Define relationships: users, campaigns, contacts, contactLists, messages, settings, limits
    - _Requirements: 5.1, 5.4_
    - _Design: Section 4.4 - Tenant Model_

  - [x] 1.4 Create User model with authentication
    - Create `app/Models/User.php`
    - Extend Laravel Authenticatable
    - Add HasApiTokens and HasUuids traits
    - Define fillable fields: name, email, password_hash
    - Define relationships: tenants, campaigns
    - _Requirements: 5.5_
    - _Design: Section 4.4 - User Model_

  - [x] 1.5 Create TenantUser pivot model
    - Create `app/Models/TenantUser.php`
    - Define fillable fields: tenant_id, user_id, role
    - Add UUID trait
    - _Requirements: 5.1, 5.5_
    - _Design: Section 4.4 - Supporting Models_

  - [-] 1.6 Create Campaign model with tenant scope
    - Create `app/Models/Campaign.php`
    - Use HasUuids and HasTenantScope traits
    - Define fillable fields: tenant_id, created_by, name, channel, status, contact_list_id, subject, template_body, sms_body, voice_audio_s3_key, scheduled_at, metadata
    - Add casts for metadata (array), timestamps
    - Define relationships: tenant, creator, contactList, messages, batches
    - _Requirements: 5.1, 5.7, 5.8, 5.9, 5.10_
    - _Design: Section 4.4 - Campaign Model_

  - [ ] 1.7 Create Contact model with tenant scope
    - Create `app/Models/Contact.php`
    - Use HasUuids and HasTenantScope traits
    - Define fillable fields: tenant_id, external_ref, first_name, last_name, email, phone_e164, attributes
    - Add casts for attributes (array)
    - Define relationships: tenant, lists, messages
    - _Requirements: 5.3, 5.7, 5.8, 5.9, 5.10_
    - _Design: Section 4.4 - Contact Model_

  - [ ] 1.8 Create ContactList model with tenant scope
    - Create `app/Models/ContactList.php`
    - Use HasUuids and HasTenantScope traits
    - Define fillable fields: tenant_id, name, segmentation_rule
    - Add casts for segmentation_rule (array)
    - Define relationships: tenant, contacts, campaigns
    - _Requirements: 5.6, 5.7, 5.8, 5.9, 5.10_
    - _Design: Section 4.4 - ContactList Model_

  - [ ] 1.9 Create Message model with tenant scope
    - Create `app/Models/Message.php`
    - Use HasUuids and HasTenantScope traits
    - Define fillable fields: tenant_id, campaign_id, batch_id, contact_id, channel, provider_message_id, message_uuid, status, error_code, error_message, payload, queued_at, sent_at
    - Add casts for payload (array), timestamps
    - Define relationships: tenant, campaign, contact, batch, deliveryEvents
    - _Requirements: 5.2, 5.7, 5.8, 5.9, 5.10_
    - _Design: Section 4.4 - Message Model_

  - [ ] 1.10 Create supporting models (TenantSetting, TenantLimit, CampaignBatch)
    - Create `app/Models/TenantSetting.php` with tenant relationship
    - Create `app/Models/TenantLimit.php` with rate limit fields
    - Create `app/Models/CampaignBatch.php` with campaign relationship
    - _Requirements: 5.1_
    - _Design: Section 4.4 - Supporting Models_

  - [ ] 1.11 Write unit tests for all Eloquent models
    - Test model relationships load correctly
    - Test UUID casting works
    - Test tenant scope applies automatically
    - Test fillable/guarded protection
    - Test casts (array, datetime, boolean)
    - _Requirements: 5.7, 5.8, 5.9, 5.10_

- [ ] 2. Phase 2: Infrastructure - Rate Limiter, Template Renderer, Middleware
  - [ ] 2.1 Implement SlidingWindowRateLimiter service
    - Create `app/Support/SlidingWindowRateLimiter.php`
    - Implement `acquire(string $key, int $limitPerMinute): bool` method
    - Use Redis sorted sets with ZREMRANGEBYSCORE, ZCARD, ZADD, EXPIRE
    - Implement `getCurrentCount(string $key): int` method
    - Use Redis pipeline for atomic operations
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_
    - _Design: Section 3.2 - Sliding Window Rate Limiter_

  - [ ] 2.2 Write unit tests for SlidingWindowRateLimiter
    - Test single request within limit is allowed
    - Test request at exact limit boundary
    - Test request exceeding limit is rejected
    - Test window expiration and cleanup
    - Test getCurrentCount() accuracy
    - _Requirements: 3.1, 3.2, 3.3, 3.6_

  - [ ] 2.3 Write property test for sliding window request counting
    - **Property 5: Sliding Window Request Counting**
    - **Validates: Requirements 3.1, 3.6**
    - Generate random sequences of requests with timestamps
    - Verify count only includes requests within last 60 seconds
    - _Design: Property 5_

  - [ ] 2.4 Write property test for rate limit enforcement
    - **Property 6: Rate Limit Enforcement**
    - **Validates: Requirements 3.2, 3.3**
    - Generate random request sequences and limits
    - Verify requests are rejected when limit is reached
    - _Design: Property 6_

  - [ ] 2.5 Write property test for rate limit isolation
    - **Property 7: Rate Limit Isolation**
    - **Validates: Requirements 3.4**
    - Generate requests for different tenants/channels
    - Verify rate limits are independent
    - _Design: Property 7_

  - [ ] 2.6 Write property test for sliding window boundary protection
    - **Property 8: Sliding Window Boundary Protection**
    - **Validates: Requirements 3.7**
    - Generate burst requests at window boundaries
    - Verify sliding window prevents bursts that fixed windows would allow
    - _Design: Property 8_

  - [ ] 2.7 Implement TemplateRenderer service
    - Create `app/Support/TemplateRenderer.php`
    - Implement `render(string $template, array $contactData, bool $escapeHtml): string` method using Blade
    - Implement `prepareData(array $contactData, bool $escapeHtml): array` for data preparation
    - Implement `extractContactData(object $contact): array` to flatten contact data
    - Handle missing variables by replacing with empty string
    - Escape HTML when $escapeHtml is true
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9_
    - _Design: Section 3.3 - Template Renderer_

  - [ ] 2.8 Write unit tests for TemplateRenderer
    - Test variable substitution with valid data
    - Test missing variable handling (empty string)
    - Test HTML escaping in email bodies
    - Test no escaping for plain text (SMS, subject)
    - Test custom field extraction from JSONB
    - Test extractContactData() method
    - _Requirements: 7.1, 7.6, 7.7_

  - [ ] 2.9 Write property test for template variable substitution
    - **Property 19: Template Variable Substitution**
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**
    - Generate random templates and contact data
    - Verify all variables in contact data are substituted
    - _Design: Property 19_

  - [ ] 2.10 Write property test for missing variable handling
    - **Property 20: Missing Variable Handling**
    - **Validates: Requirements 7.6**
    - Generate templates with variables not in contact data
    - Verify missing variables are replaced with empty string
    - _Design: Property 20_

  - [ ] 2.11 Write property test for HTML escaping
    - **Property 21: HTML Escaping in Email Bodies**
    - **Validates: Requirements 7.7**
    - Generate contact data with HTML special characters
    - Verify characters are escaped when escapeHtml=true
    - _Design: Property 21_

  - [ ] 2.12 Create EnsureTenantContext middleware
    - Create `app/Http/Middleware/EnsureTenantContext.php`
    - Extract tenant_id from route parameter or X-Tenant-ID header
    - Return 400 if tenant_id is missing
    - Return 401 if user is not authenticated
    - Check TenantUser relationship to verify user belongs to tenant
    - Return 403 if user doesn't belong to tenant
    - Set tenant context in request attributes and app container
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
    - _Design: Section 3.1 - Tenant Context Middleware_

  - [ ] 2.13 Write unit tests for EnsureTenantContext middleware
    - Test valid tenant access (user belongs to tenant)
    - Test invalid tenant access returns 403
    - Test missing tenant_id returns 400
    - Test unauthenticated request returns 401
    - Test tenant context is set in app container
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ] 2.14 Write property test for tenant access control
    - **Property 3: Tenant Access Control**
    - **Validates: Requirements 2.1, 2.2**
    - Generate random user-tenant relationships
    - Verify access is granted if and only if user belongs to tenant
    - _Design: Property 3_

  - [ ] 2.15 Write property test for tenant context propagation
    - **Property 4: Tenant Context Propagation**
    - **Validates: Requirements 2.3**
    - Generate valid API requests with tenant authentication
    - Verify tenant context is accessible throughout request lifecycle
    - _Design: Property 4_

  - [ ] 2.16 Register middleware and update configuration
    - Register EnsureTenantContext in `app/Http/Kernel.php` as 'tenant' alias
    - Update `routes/api.php` to apply 'tenant' middleware to tenant-scoped routes
    - Create/update `config/messaging.php` with rate limits, retry settings, channel defaults
    - _Requirements: 2.4_
    - _Design: Section 6 - Configuration Updates_

  - [ ] 2.17 Register services in AppServiceProvider
    - Register SlidingWindowRateLimiter as singleton in `app/Providers/AppServiceProvider.php`
    - Register TemplateRenderer as singleton
    - Bind AmazonSesProvider with SesClient configuration
    - Bind AmazonSnsProvider with SnsClient configuration
    - Bind FreeSwitchEslProvider with ESL client configuration
    - _Design: Section 6 - Service Provider Registration_

- [ ] 3. Checkpoint - Verify foundation and infrastructure
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Phase 3: Core Logic - Campaign Engine Enhancements
  - [ ] 4.1 Enhance CampaignEngineService with payload population
    - Modify `app/Campaign/Services/CampaignEngineService.php`
    - Inject TemplateRenderer in constructor
    - Update `dispatchCampaign()` to load campaign with relationships (contactList, tenant.settings)
    - Update campaign status to 'running' with started_at timestamp
    - Process contacts in batches using chunk()
    - Create CampaignBatch records for tracking
    - Dispatch DispatchCampaignBatchJob to 'campaign-batch' queue
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
    - _Design: Section 4.5 - Enhanced CampaignEngineService_

  - [ ] 4.2 Enhance DispatchCampaignBatchJob with transactions and payload building
    - Modify `app/Campaign/Jobs/DispatchCampaignBatchJob.php`
    - Inject TemplateRenderer in handle() method
    - Update batch status to 'running' with started_at timestamp
    - Load campaign with tenant.settings relationship
    - Load all contacts in batch using whereIn()
    - Wrap message creation in DB::transaction()
    - For each contact: extract contact data, render templates, build channel-specific payload
    - Implement `buildPayload()` method with match expression for email/sms/voice channels
    - Create Message records with populated payload field
    - Dispatch channel-specific send jobs (SendEmailMessageJob, SendSmsMessageJob, SendVoiceMessageJob)
    - Update batch status to 'completed' with completed_at timestamp
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 6.1, 6.2_
    - _Design: Section 4.6 - Enhanced DispatchCampaignBatchJob_

  - [ ] 4.3 Write property test for channel-specific payload completeness
    - **Property 1: Channel-Specific Payload Completeness**
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5**
    - Generate random campaigns for each channel (email, sms, voice)
    - Verify email payloads have: to, from, subject, body
    - Verify SMS payloads have: to, from, body
    - Verify voice payloads have: to, from, audio_url, caller_id
    - _Design: Property 1_

  - [ ] 4.4 Write property test for payload JSON round-trip
    - **Property 2: Payload JSON Round-Trip**
    - **Validates: Requirements 1.6**
    - Generate random message payloads
    - Serialize to JSON and deserialize
    - Verify data structure is equivalent
    - _Design: Property 2_

  - [ ] 4.5 Write property test for batch creation atomicity
    - **Property 16: Batch Creation Atomicity**
    - **Validates: Requirements 6.1, 6.2**
    - Simulate message creation failures during batch processing
    - Verify all messages in batch are rolled back on failure
    - _Design: Property 16_

  - [ ] 4.6 Write integration test for campaign dispatch flow
    - Test end-to-end campaign dispatch
    - Verify batch creation and message generation
    - Verify payload population with real data
    - Verify queue job dispatching
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [ ] 5. Phase 4: Message Sending - Job and Provider Enhancements
  - [ ] 5.1 Enhance AbstractSendMessageJob with error handling and idempotency
    - Modify `app/Messaging/Jobs/AbstractSendMessageJob.php`
    - Inject SlidingWindowRateLimiter in handle() method
    - Load message with tenant.limits relationship
    - Implement idempotency check: skip if status is 'sent'
    - Get rate limit from tenant limits or use defaults
    - Build rate limit key: "{tenant_id}:{metric}"
    - Check rate limit using rateLimiter->acquire()
    - Release job with 5 second delay if rate limit exceeded
    - Update message status to 'sending' before provider call
    - Wrap provider send in try-catch block
    - On success: update status to 'sent', store provider_message_id, log delivery event in transaction
    - On error: call handleProviderError() method
    - Implement `handleProviderError()` to classify errors as transient or permanent
    - Implement `extractErrorCode()` to get error code from exceptions
    - Implement `isTransientError()` to check for timeout, connection, throttle, rate limit patterns
    - Update message status to 'failed' with error_code and error_message
    - Log delivery event (failed) in transaction
    - Fail job permanently if error is not transient, otherwise throw to allow retry
    - _Requirements: 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 6.3, 6.4, 6.5, 8.1, 8.2, 8.3_
    - _Design: Section 4.7 - Enhanced AbstractSendMessageJob_

  - [ ] 5.2 Update channel-specific send jobs to use enhanced base class
    - Modify `app/Messaging/Jobs/SendEmailMessageJob.php`
    - Implement `getProvider()` to return AmazonSesProvider
    - Implement `getRateLimitMetric()` to return 'emails_per_minute'
    - Repeat for `SendSmsMessageJob.php` (sms_per_minute) and `SendVoiceMessageJob.php` (calls_per_minute)
    - _Requirements: 3.4_
    - _Design: Section 4.8 - Channel-Specific Send Jobs_

  - [ ] 5.3 Enhance AmazonSesProvider with structured error handling
    - Modify `app/Integrations/AmazonSesProvider.php`
    - Wrap sendEmail() call in try-catch for AwsException
    - On success: return array with provider_message_id and status
    - On error: throw RuntimeException with formatted message including AWS error details
    - _Requirements: 4.1, 4.2, 4.3, 4.6_
    - _Design: Section 4.9 - Enhanced Provider Error Handling_

  - [ ] 5.4 Enhance AmazonSnsProvider with structured error handling
    - Modify `app/Integrations/AmazonSnsProvider.php`
    - Wrap publish() call in try-catch for AwsException
    - On success: return array with provider_message_id and status
    - On error: throw RuntimeException with formatted message including AWS error details
    - _Requirements: 4.1, 4.2, 4.3, 4.6_
    - _Design: Section 4.9 - Enhanced Provider Error Handling_

  - [ ] 5.5 Enhance FreeSwitchEslProvider with structured error handling
    - Modify `app/Integrations/FreeSwitchEslProvider.php`
    - Wrap originate() call in try-catch for Throwable
    - On success: return array with provider_message_id (call_id) and status
    - On error: throw RuntimeException with formatted message
    - _Requirements: 4.1, 4.2, 4.3, 4.6_
    - _Design: Section 4.9 - Enhanced Provider Error Handling_

  - [ ] 5.6 Write property test for provider error capture
    - **Property 9: Provider Error Capture**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.6**
    - Generate random provider errors
    - Verify errors are caught, logged with context, and message status updated to 'failed'
    - _Design: Property 9_

  - [ ] 5.7 Write property test for transient error retry
    - **Property 10: Transient Error Retry**
    - **Validates: Requirements 4.4**
    - Generate transient errors (timeout, connection, throttle)
    - Verify job allows retry
    - _Design: Property 10_

  - [ ] 5.8 Write property test for permanent error failure
    - **Property 11: Permanent Error Failure**
    - **Validates: Requirements 4.5**
    - Generate permanent errors (invalid credentials, malformed request)
    - Verify job fails immediately without retry
    - _Design: Property 11_

  - [ ] 5.9 Write property test for successful send recording
    - **Property 12: Successful Send Recording**
    - **Validates: Requirements 4.7**
    - Generate successful provider responses
    - Verify message status updated to 'sent' with provider_message_id
    - _Design: Property 12_

  - [ ] 5.10 Write property test for status update atomicity
    - **Property 17: Status Update Atomicity**
    - **Validates: Requirements 6.3**
    - Generate message status updates
    - Verify status and metadata (sent_at, error_code, provider_message_id) updated atomically
    - _Design: Property 17_

  - [ ] 5.11 Write property test for transaction failure recovery
    - **Property 18: Transaction Failure Recovery**
    - **Validates: Requirements 6.5**
    - Simulate transaction failures
    - Verify error is logged and operation is retryable
    - _Design: Property 18_

  - [ ] 5.12 Write property test for idempotency check
    - **Property 22: Idempotency Check**
    - **Validates: Requirements 8.1, 8.2**
    - Generate messages with duplicate message_uuid and status 'sent'
    - Verify processing skips sending and returns success
    - _Design: Property 22_

  - [ ] 5.13 Write property test for failed message retry
    - **Property 23: Failed Message Retry**
    - **Validates: Requirements 8.3**
    - Generate messages with duplicate message_uuid and status 'failed'
    - Verify processing attempts to resend
    - _Design: Property 23_

  - [ ] 5.14 Write property test for unique message UUID generation
    - **Property 24: Unique Message UUID Generation**
    - **Validates: Requirements 8.5**
    - Generate multiple messages
    - Verify all message_uuid values are unique
    - _Design: Property 24_

  - [ ] 5.15 Write property test for duplicate insert handling
    - **Property 25: Duplicate Insert Handling**
    - **Validates: Requirements 8.7**
    - Attempt to insert messages with duplicate message_uuid
    - Verify unique constraint violation is handled gracefully
    - _Design: Property 25_

  - [ ] 5.16 Write unit tests for provider error handling
    - Test AWS exception handling in SES provider
    - Test AWS exception handling in SNS provider
    - Test exception handling in FreeSWITCH provider
    - Test error code extraction
    - Test transient vs permanent error classification
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [ ] 5.17 Write integration test for message send flow
    - Test message retrieval and processing
    - Test rate limiting integration
    - Test provider interaction (mocked)
    - Test database transaction handling
    - Test idempotency behavior
    - _Requirements: 3.2, 3.3, 4.1, 4.7, 6.3, 8.1, 8.2_

- [ ] 6. Checkpoint - Verify core logic and message sending
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Phase 5: Testing & Validation - Property-Based and Model Tests
  - [ ] 7.1 Install Eris property-based testing library
    - Run `composer require --dev giorgiosironi/eris`
    - Verify installation
    - _Design: Section 7 - Testing Strategy_

  - [ ] 7.2 Write property test for model validation
    - **Property 13: Model Validation**
    - **Validates: Requirements 5.7**
    - Generate invalid model data (missing required fields, wrong types)
    - Verify save fails with validation error
    - _Design: Property 13_

  - [ ] 7.3 Write property test for UUID type casting
    - **Property 14: UUID Type Casting**
    - **Validates: Requirements 5.8**
    - Generate models with UUID fields
    - Verify fields are cast to string UUIDs when accessed
    - _Design: Property 14_

  - [ ] 7.4 Write property test for tenant scope isolation
    - **Property 15: Tenant Scope Isolation**
    - **Validates: Requirements 5.10**
    - Generate queries with tenant context set
    - Verify only records belonging to that tenant are returned
    - _Design: Property 15_

  - [ ] 7.5 Write integration test for tenant isolation
    - Test multi-tenant data access
    - Test scope enforcement across models
    - Test middleware integration with controllers
    - _Requirements: 2.1, 2.2, 2.3, 5.10_

  - [ ] 7.6 Run full test suite with coverage analysis
    - Execute `php artisan test --coverage --min=80`
    - Verify all unit tests pass
    - Verify all property tests pass (100+ iterations each)
    - Verify all integration tests pass
    - Verify code coverage exceeds 80%
    - _Design: Section 7 - Test Execution_

  - [ ] 7.7 Performance testing with realistic data volumes
    - Create test campaign with 10,000 contacts
    - Measure campaign dispatch time
    - Measure message send throughput per channel
    - Verify target: 10,000+ messages/minute per channel
    - Monitor Redis memory usage during rate limiting
    - Monitor database connection pool usage
    - _Design: Section 6 - Performance Optimization_

  - [ ] 7.8 Security audit of tenant isolation
    - Test cross-tenant data access attempts
    - Verify middleware blocks unauthorized access
    - Verify global scopes prevent data leakage
    - Test mass assignment protection
    - Test XSS prevention in template rendering
    - _Design: Section 8 - Security Considerations_

- [ ] 8. Phase 6: Deployment - Staging and Production Rollout
  - [ ] 8.1 Update environment configuration
    - Add new environment variables to `.env.example`
    - Document RATE_LIMIT_* variables (optional, have defaults)
    - Document JOB_MAX_ATTEMPTS and JOB_BACKOFF_SECONDS
    - Update deployment documentation with new configuration
    - _Design: Section 10 - Environment Variables_

  - [ ] 8.2 Configure queue workers for production
    - Update queue worker configuration for campaign-batch queue (tries=5, timeout=300)
    - Update queue worker configuration for email-send queue (tries=8, timeout=60)
    - Update queue worker configuration for sms-send queue (tries=8, timeout=60)
    - Update queue worker configuration for voice-send queue (tries=8, timeout=120)
    - Document worker concurrency recommendations per queue
    - _Design: Section 6 - Deployment Considerations_

  - [ ] 8.3 Configure Redis for rate limiting
    - Verify Redis memory allocation (minimum 1 GB recommended)
    - Configure Redis persistence settings
    - Set up Redis monitoring and alerts
    - _Design: Section 6 - Deployment Considerations_

  - [ ] 8.4 Configure database connection pooling
    - Set up PgBouncer or similar connection pooler
    - Configure pool size: 100-200 connections for production
    - Set transaction mode for queue workers
    - _Design: Section 6 - Deployment Considerations_

  - [ ] 8.5 Create database indexes for performance
    - Create index on messages.status for queued messages: `CREATE INDEX CONCURRENTLY idx_messages_status_queued ON messages (status) WHERE status = 'queued'`
    - Create index on messages.message_uuid: `CREATE INDEX CONCURRENTLY idx_messages_message_uuid ON messages (message_uuid)`
    - Create index on tenant_users lookup: `CREATE INDEX CONCURRENTLY idx_tenant_users_lookup ON tenant_users (tenant_id, user_id)`
    - Verify index creation completes successfully
    - _Design: Section 6 - Performance Optimization_

  - [ ] 8.6 Set up monitoring and observability
    - Configure structured logging with consistent context fields
    - Set up log aggregation (CloudWatch, ELK, etc.)
    - Configure metrics collection (Prometheus or similar)
    - Set up health check endpoint: GET /health
    - Configure critical alerts (provider error rate, queue depth, database pool, Redis memory)
    - Configure warning alerts (send latency, rate limit rejections, job retry rate)
    - _Design: Section 9 - Monitoring and Observability_

  - [ ] 8.7 Deploy to staging environment
    - Deploy code to staging
    - Run database migrations (if any)
    - Restart queue workers with new configuration
    - Clear application caches
    - _Design: Section 10 - Migration Path_

  - [ ] 8.8 Run smoke tests in staging
    - Create test campaign with small contact list (100 contacts)
    - Verify campaign dispatch works
    - Verify messages are created with populated payloads
    - Verify messages are sent successfully
    - Verify rate limiting works
    - Verify idempotency works (retry a job)
    - Verify tenant isolation works
    - Check logs for errors
    - _Design: Section 10 - Migration Path_

  - [ ] 8.9 Run load tests in staging
    - Create campaign with 10,000 contacts
    - Monitor queue processing
    - Monitor Redis memory usage
    - Monitor database connection pool
    - Verify throughput meets target (10,000+ messages/minute)
    - Check for errors or performance issues
    - _Design: Section 10 - Migration Path_

  - [ ] 8.10 Deploy to production with gradual rollout
    - Deploy code to production (blue-green deployment recommended)
    - Run database migrations (if any)
    - Restart queue workers with new configuration
    - Clear application caches
    - Monitor metrics and logs closely
    - Start with small campaigns to verify behavior
    - Gradually increase campaign sizes
    - _Design: Section 10 - Migration Path_

  - [ ] 8.11 Monitor production deployment
    - Monitor error rates (should be < 1%)
    - Monitor message send throughput
    - Monitor queue depths
    - Monitor Redis memory usage
    - Monitor database connection pool usage
    - Monitor rate limit rejections
    - Check logs for unexpected errors
    - Verify tenant isolation is working
    - _Design: Section 9 - Monitoring and Observability_

  - [ ] 8.12 Document rollback plan
    - Document steps to revert to previous deployment
    - Document how to stop processing new campaigns
    - Document how to let existing jobs complete
    - Document how to fall back to old rate limiter if needed
    - Test rollback procedure in staging
    - _Design: Section 10 - Migration Path - Rollback Plan_

- [ ] 9. Final checkpoint - Verify production deployment
  - Ensure all production metrics are healthy, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties using Eris library
- Unit tests validate specific examples and edge cases
- Integration tests validate component interactions
- Checkpoints ensure incremental validation at key milestones
- The implementation follows a phased approach: Foundation → Infrastructure → Core Logic → Message Sending → Testing → Deployment
- All code must use PHP 8.2 with strict types (`declare(strict_types=1)`)
- All classes should be final by default unless designed for inheritance
- Use readonly properties where applicable
- Follow Laravel conventions and PSR-4 autoloading
- Maintain backward compatibility with existing code where possible

## Success Criteria

The implementation will be considered successful when:

1. All 25 correctness properties pass with 100+ iterations each
2. Unit test coverage exceeds 80%
3. Integration tests pass for all critical flows
4. Campaign dispatch populates complete payloads for all channels
5. Tenant isolation prevents cross-tenant data access
6. Rate limiting prevents burst attacks and provider quota exhaustion
7. Provider errors are handled gracefully with appropriate retry logic
8. Message sends are idempotent across job retries
9. Performance meets target: 10,000+ messages/minute per channel
10. Zero security vulnerabilities in tenant isolation
11. Production deployment completes successfully with no critical issues
