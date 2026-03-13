# Laravel Project Structure (Step 3)

```text
app/
  Campaign/
    DTO/CampaignBatchPayload.php
    Enums/CampaignChannel.php
    Jobs/DispatchCampaignBatchJob.php
    Services/CampaignEngineService.php
  Messaging/
    Contracts/ChannelProvider.php
    DTO/OutboundMessage.php
    Enums/MessageChannel.php
    Jobs/AbstractSendMessageJob.php
    Jobs/SendEmailMessageJob.php
    Jobs/SendSmsMessageJob.php
    Jobs/SendVoiceMessageJob.php
    Workers/EmailWorker.php
    Workers/SmsWorker.php
    Workers/VoiceWorker.php
  Integrations/
    AmazonSesProvider.php
    AmazonSnsProvider.php
    FreeSwitchEslClient.php
    FreeSwitchEslProvider.php
  Support/
    RedisTokenBucketRateLimiter.php
  Http/Controllers/Api/
    CampaignController.php
config/
  messaging.php
database/
  schema.sql
docs/
  architecture.md
  project-structure.md
routes/
  api.php
```

This base keeps channel providers modular, campaign orchestration isolated, and queue workers independently scalable.
