# ContactSass - AWS Infrastructure Architecture

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              INTERNET / USERS                                │
└────────────────────────────────┬────────────────────────────────────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │   Route 53 (DNS)        │
                    │   - contactsass.com     │
                    │   - api.contactsass.com │
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │  CloudFront (CDN)       │
                    │  - SSL/TLS Termination  │
                    │  - DDoS Protection      │
                    │  - WAF Rules            │
                    └────────────┬────────────┘
                                 │
┌────────────────────────────────▼─────────────────────────────────────────────┐
│                              AWS REGION (us-east-1)                           │
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                         VPC (10.0.0.0/16)                            │    │
│  │                                                                      │    │
│  │  ┌──────────────────────────────────────────────────────────────┐  │    │
│  │  │              PUBLIC SUBNETS (2 AZs)                          │  │    │
│  │  │                                                              │  │    │
│  │  │  ┌─────────────────────────────────────────────────────┐   │  │    │
│  │  │  │  Application Load Balancer (ALB)                    │   │  │    │
│  │  │  │  - HTTPS Listener (443)                             │   │  │    │
│  │  │  │  - Health Checks                                    │   │  │    │
│  │  │  │  - Target Groups: API, Workers                      │   │  │    │
│  │  │  └──────────────────┬──────────────────────────────────┘   │  │    │
│  │  └─────────────────────┼──────────────────────────────────────┘  │    │
│  │                        │                                          │    │
│  │  ┌─────────────────────▼──────────────────────────────────────┐  │    │
│  │  │              PRIVATE SUBNETS (2 AZs)                       │  │    │
│  │  │                                                            │  │    │
│  │  │  ┌──────────────────────────────────────────────────┐    │  │    │
│  │  │  │  ECS Fargate Cluster                             │    │  │    │
│  │  │  │                                                  │    │  │    │
│  │  │  │  ┌────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │  API Service (Auto Scaling 2-10)       │     │    │  │    │
│  │  │  │  │  - Laravel API                         │     │    │  │    │
│  │  │  │  │  - JWT Auth + RBAC                     │     │    │  │    │
│  │  │  │  │  - Tenant Context Middleware           │     │    │  │    │
│  │  │  │  │  - CPU: 1 vCPU, RAM: 2GB              │     │    │  │    │
│  │  │  │  └────────────────────────────────────────┘     │    │  │    │
│  │  │  │                                                  │    │  │    │
│  │  │  │  ┌────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │  Campaign Worker (Auto Scaling 2-20)   │     │    │  │    │
│  │  │  │  │  - Queue: campaign-batch               │     │    │  │    │
│  │  │  │  │  - CPU: 0.5 vCPU, RAM: 1GB            │     │    │  │    │
│  │  │  │  └────────────────────────────────────────┘     │    │  │    │
│  │  │  │                                                  │    │  │    │
│  │  │  │  ┌────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │  Email Worker (Auto Scaling 5-50)      │     │    │  │    │
│  │  │  │  │  - Queue: email-send                   │     │    │  │    │
│  │  │  │  │  - CPU: 0.25 vCPU, RAM: 512MB         │     │    │  │    │
│  │  │  │  └────────────────────────────────────────┘     │    │  │    │
│  │  │  │                                                  │    │  │    │
│  │  │  │  ┌────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │  SMS Worker (Auto Scaling 3-30)        │     │    │  │    │
│  │  │  │  │  - Queue: sms-send                     │     │    │  │    │
│  │  │  │  │  - CPU: 0.25 vCPU, RAM: 512MB         │     │    │  │    │
│  │  │  │  └────────────────────────────────────────┘     │    │  │    │
│  │  │  │                                                  │    │  │    │
│  │  │  │  ┌────────────────────────────────────────┐     │    │  │    │
│  │  │  │  │  Voice Worker (Auto Scaling 2-20)      │     │    │  │    │
│  │  │  │  │  - Queue: voice-send                   │     │    │  │    │
│  │  │  │  │  - CPU: 0.5 vCPU, RAM: 1GB            │     │    │  │    │
│  │  │  │  └────────────────────────────────────────┘     │    │  │    │
│  │  │  └──────────────────────────────────────────────────┘    │  │    │
│  │  └─────────────────────────────────────────────────────────┘  │    │
│  │                                                                 │    │
│  │  ┌─────────────────────────────────────────────────────────┐  │    │
│  │  │              DATA TIER (PRIVATE SUBNETS)                │  │    │
│  │  │                                                         │  │    │
│  │  │  ┌──────────────────────────────────────────────────┐ │  │    │
│  │  │  │  RDS PostgreSQL (Multi-AZ)                       │ │  │    │
│  │  │  │  - Instance: db.r6g.xlarge                       │ │  │    │
│  │  │  │  - Storage: 500GB gp3 (Auto Scaling to 2TB)     │ │  │    │
│  │  │  │  - Read Replicas: 2 (for reporting)             │ │  │    │
│  │  │  │  - Automated Backups: 7 days retention          │ │  │    │
│  │  │  │  - Encryption: AES-256 at rest                  │ │  │    │
│  │  │  └──────────────────────────────────────────────────┘ │  │    │
│  │  │                                                         │  │    │
│  │  │  ┌──────────────────────────────────────────────────┐ │  │    │
│  │  │  │  ElastiCache Redis (Cluster Mode)                │ │  │    │
│  │  │  │  - Node Type: cache.r6g.large                    │ │  │    │
│  │  │  │  - Nodes: 3 (1 primary + 2 replicas)            │ │  │    │
│  │  │  │  - Multi-AZ with Auto Failover                   │ │  │    │
│  │  │  │  - Encryption: In-transit + At-rest              │ │  │    │
│  │  │  │  - Use: Queues, Cache, Rate Limiting             │ │  │    │
│  │  │  └──────────────────────────────────────────────────┘ │  │    │
│  │  └─────────────────────────────────────────────────────────┘  │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│                                                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                    EXTERNAL AWS SERVICES                         │    │
│  │                                                                  │    │
│  │  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐ │    │
│  │  │  Amazon SES    │  │  Amazon SNS    │  │  Amazon S3       │ │    │
│  │  │  - Email Send  │  │  - SMS Send    │  │  - Audio Files   │ │    │
│  │  │  - 62K/sec     │  │  - 20K/sec     │  │  - Attachments   │ │    │
│  │  └────────────────┘  └────────────────┘  └──────────────────┘ │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                    MONITORING & LOGGING                          │    │
│  │                                                                  │    │
│  │  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐ │    │
│  │  │  CloudWatch    │  │  CloudWatch    │  │  AWS X-Ray       │ │    │
│  │  │  Logs          │  │  Metrics       │  │  - Tracing       │ │    │
│  │  │  - 30d retain  │  │  - Alarms      │  │  - Performance   │ │    │
│  │  └────────────────┘  └────────────────┘  └──────────────────┘ │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                    SECURITY SERVICES                             │    │
│  │                                                                  │    │
│  │  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐ │    │
│  │  │  AWS WAF       │  │  AWS Shield    │  │  Secrets Manager │ │    │
│  │  │  - SQL Inject  │  │  - DDoS Std    │  │  - DB Creds      │ │    │
│  │  │  - XSS Rules   │  │  - L3/L4       │  │  - API Keys      │ │    │
│  │  └────────────────┘  └────────────────┘  └──────────────────┘ │    │
│  │                                                                  │    │
│  │  ┌────────────────┐  ┌────────────────┐  ┌──────────────────┐ │    │
│  │  │  AWS KMS       │  │  IAM Roles     │  │  Security Groups │ │    │
│  │  │  - Encryption  │  │  - Least Priv  │  │  - Network ACLs  │ │    │
│  │  │  - Key Rotate  │  │  - RBAC        │  │  - VPC Isolation │ │    │
│  │  └────────────────┘  └────────────────┘  └──────────────────┘ │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────┐
│                    EXTERNAL VOICE PROVIDER                                 │
│                                                                            │
│  ┌──────────────────────────────────────────────────────────────────┐    │
│  │  FreeSWITCH Server (EC2 or External SIP Provider)               │    │
│  │  - Instance: c5.2xlarge (8 vCPU, 16GB RAM)                      │    │
│  │  - ESL Connection from Voice Workers                             │    │
│  │  - SIP Trunking for outbound calls                               │    │
│  └──────────────────────────────────────────────────────────────────┘    │
└───────────────────────────────────────────────────────────────────────────┘
```

## Network Architecture

### VPC Configuration
- **CIDR Block**: 10.0.0.0/16
- **Availability Zones**: 2 (us-east-1a, us-east-1b)
- **Public Subnets**: 10.0.1.0/24, 10.0.2.0/24
- **Private Subnets (App)**: 10.0.10.0/24, 10.0.11.0/24
- **Private Subnets (Data)**: 10.0.20.0/24, 10.0.21.0/24

### Security Groups

#### ALB Security Group
- **Inbound**: 
  - Port 443 (HTTPS) from 0.0.0.0/0
  - Port 80 (HTTP) from 0.0.0.0/0 (redirect to 443)
- **Outbound**: All traffic to ECS Security Group

#### ECS Security Group
- **Inbound**: 
  - Port 8080 from ALB Security Group
- **Outbound**: 
  - Port 5432 to RDS Security Group
  - Port 6379 to Redis Security Group
  - Port 443 to 0.0.0.0/0 (AWS APIs, SES, SNS)

#### RDS Security Group
- **Inbound**: 
  - Port 5432 from ECS Security Group
- **Outbound**: None

#### Redis Security Group
- **Inbound**: 
  - Port 6379 from ECS Security Group
- **Outbound**: None

## Auto Scaling Configuration

### API Service
- **Min**: 2 tasks
- **Max**: 10 tasks
- **Target CPU**: 70%
- **Target Memory**: 80%
- **Scale-out cooldown**: 60s
- **Scale-in cooldown**: 300s

### Campaign Worker
- **Min**: 2 tasks
- **Max**: 20 tasks
- **Target**: Queue depth (100 messages per task)
- **Scale-out cooldown**: 60s
- **Scale-in cooldown**: 180s

### Email Worker
- **Min**: 5 tasks
- **Max**: 50 tasks
- **Target**: Queue depth (500 messages per task)
- **Scale-out cooldown**: 30s
- **Scale-in cooldown**: 120s

### SMS Worker
- **Min**: 3 tasks
- **Max**: 30 tasks
- **Target**: Queue depth (300 messages per task)
- **Scale-out cooldown**: 30s
- **Scale-in cooldown**: 120s

### Voice Worker
- **Min**: 2 tasks
- **Max**: 20 tasks
- **Target**: Queue depth (50 messages per task)
- **Scale-out cooldown**: 60s
- **Scale-in cooldown**: 180s


## Cost Estimation (Monthly)

### Compute (ECS Fargate)

#### API Service
- **Configuration**: 2-10 tasks, 1 vCPU, 2GB RAM
- **Average**: 4 tasks running 24/7
- **Cost**: 4 tasks × $0.04048/hour × 730 hours = **$118.20/month**

#### Campaign Worker
- **Configuration**: 2-20 tasks, 0.5 vCPU, 1GB RAM
- **Average**: 6 tasks running 24/7
- **Cost**: 6 tasks × $0.02024/hour × 730 hours = **$88.65/month**

#### Email Worker
- **Configuration**: 5-50 tasks, 0.25 vCPU, 512MB RAM
- **Average**: 15 tasks running 24/7
- **Cost**: 15 tasks × $0.01012/hour × 730 hours = **$110.82/month**

#### SMS Worker
- **Configuration**: 3-30 tasks, 0.25 vCPU, 512MB RAM
- **Average**: 10 tasks running 24/7
- **Cost**: 10 tasks × $0.01012/hour × 730 hours = **$73.88/month**

#### Voice Worker
- **Configuration**: 2-20 tasks, 0.5 vCPU, 1GB RAM
- **Average**: 5 tasks running 24/7
- **Cost**: 5 tasks × $0.02024/hour × 730 hours = **$73.88/month**

**Total Compute**: **$465.43/month**

---

### Database (RDS PostgreSQL)

#### Primary Instance
- **Instance**: db.r6g.xlarge (4 vCPU, 32GB RAM)
- **Multi-AZ**: Yes
- **Cost**: $0.48/hour × 730 hours × 2 (Multi-AZ) = **$700.80/month**

#### Read Replicas
- **Instances**: 2 × db.r6g.large (2 vCPU, 16GB RAM)
- **Cost**: 2 × $0.24/hour × 730 hours = **$350.40/month**

#### Storage
- **Type**: gp3
- **Size**: 500GB
- **Cost**: 500GB × $0.115/GB = **$57.50/month**
- **IOPS**: 12,000 provisioned (included in gp3)
- **Throughput**: 500 MB/s (included in gp3)

#### Backup Storage
- **Size**: 500GB (7 days retention)
- **Cost**: 500GB × $0.095/GB = **$47.50/month**

**Total Database**: **$1,156.20/month**

---

### Cache (ElastiCache Redis)

#### Redis Cluster
- **Node Type**: cache.r6g.large (2 vCPU, 13.07GB RAM)
- **Nodes**: 3 (1 primary + 2 replicas)
- **Multi-AZ**: Yes
- **Cost**: 3 × $0.226/hour × 730 hours = **$495.18/month**

**Total Cache**: **$495.18/month**

---

### Load Balancing

#### Application Load Balancer
- **Fixed Cost**: $0.0225/hour × 730 hours = **$16.43/month**
- **LCU Cost**: ~10 LCUs average × $0.008/LCU × 730 hours = **$58.40/month**

**Total Load Balancing**: **$74.83/month**

---

### Networking

#### Data Transfer
- **Outbound to Internet**: 5TB/month × $0.09/GB = **$450.00/month**
- **Inter-AZ Transfer**: 2TB/month × $0.01/GB = **$20.00/month**

#### NAT Gateway
- **2 NAT Gateways**: 2 × $0.045/hour × 730 hours = **$65.70/month**
- **Data Processing**: 3TB × $0.045/GB = **$135.00/month**

**Total Networking**: **$670.70/month**

---

### Storage (S3)

#### Audio Files & Attachments
- **Storage**: 1TB × $0.023/GB = **$23.00/month**
- **PUT Requests**: 1M × $0.005/1000 = **$5.00/month**
- **GET Requests**: 10M × $0.0004/1000 = **$4.00/month**

**Total Storage**: **$32.00/month**

---

### Messaging Services

#### Amazon SES (Email)
- **Volume**: 10 million emails/month
- **Cost**: 10M × $0.10/1000 = **$1,000.00/month**
- **Dedicated IP**: 1 × $24.95 = **$24.95/month** (optional)

#### Amazon SNS (SMS)
- **Volume**: 1 million SMS/month
- **Cost (US)**: 1M × $0.00645 = **$6,450.00/month**
- **Note**: SMS costs vary significantly by country

**Total Messaging**: **$7,474.95/month** (without dedicated IP)

---

### Voice (FreeSWITCH)

#### EC2 Instance (if self-hosted)
- **Instance**: c5.2xlarge (8 vCPU, 16GB RAM)
- **Cost**: $0.34/hour × 730 hours = **$248.20/month**
- **EBS Storage**: 100GB gp3 × $0.08/GB = **$8.00/month**

#### SIP Trunking (External Provider)
- **Per-minute cost**: $0.01/minute
- **Volume**: 100,000 minutes/month = **$1,000.00/month**

**Total Voice**: **$1,256.20/month** (self-hosted + SIP)

---

### Monitoring & Logging

#### CloudWatch Logs
- **Ingestion**: 100GB × $0.50/GB = **$50.00/month**
- **Storage**: 500GB × $0.03/GB = **$15.00/month**

#### CloudWatch Metrics
- **Custom Metrics**: 100 × $0.30 = **$30.00/month**
- **API Requests**: 1M × $0.01/1000 = **$10.00/month**

#### CloudWatch Alarms
- **Alarms**: 50 × $0.10 = **$5.00/month**

#### AWS X-Ray
- **Traces**: 1M × $5.00/1M = **$5.00/month**
- **Trace Retrieval**: 100K × $0.50/1M = **$0.05/month**

**Total Monitoring**: **$115.05/month**

---

### Security

#### AWS WAF
- **Web ACL**: 1 × $5.00 = **$5.00/month**
- **Rules**: 10 × $1.00 = **$10.00/month**
- **Requests**: 100M × $0.60/1M = **$60.00/month**

#### AWS Shield Standard
- **Cost**: **$0.00/month** (included)

#### Secrets Manager
- **Secrets**: 10 × $0.40 = **$4.00/month**
- **API Calls**: 100K × $0.05/10K = **$0.50/month**

#### AWS KMS
- **Keys**: 5 × $1.00 = **$5.00/month**
- **Requests**: 1M × $0.03/10K = **$3.00/month**

**Total Security**: **$87.50/month**

---

### DNS & CDN

#### Route 53
- **Hosted Zones**: 2 × $0.50 = **$1.00/month**
- **Queries**: 100M × $0.40/1M = **$40.00/month**

#### CloudFront
- **Data Transfer**: 2TB × $0.085/GB = **$170.00/month**
- **Requests**: 100M × $0.0075/10K = **$75.00/month**

**Total DNS & CDN**: **$286.00/month**

---

## Total Monthly Cost Summary

| Category | Monthly Cost |
|----------|--------------|
| Compute (ECS Fargate) | $465.43 |
| Database (RDS PostgreSQL) | $1,156.20 |
| Cache (ElastiCache Redis) | $495.18 |
| Load Balancing | $74.83 |
| Networking | $670.70 |
| Storage (S3) | $32.00 |
| Messaging (SES + SNS) | $7,474.95 |
| Voice (FreeSWITCH + SIP) | $1,256.20 |
| Monitoring & Logging | $115.05 |
| Security | $87.50 |
| DNS & CDN | $286.00 |
| **TOTAL** | **$12,114.04/month** |

### Cost Breakdown by Percentage

- **Messaging (SES + SNS)**: 61.7%
- **Voice**: 10.4%
- **Database**: 9.5%
- **Networking**: 5.5%
- **Cache**: 4.1%
- **Compute**: 3.8%
- **DNS & CDN**: 2.4%
- **Monitoring**: 0.9%
- **Security**: 0.7%
- **Load Balancing**: 0.6%
- **Storage**: 0.3%

### Cost Optimization Opportunities

1. **Reserved Instances**: Save 30-40% on RDS and ElastiCache with 1-year commitment
2. **Savings Plans**: Save 20-30% on ECS Fargate with compute savings plans
3. **S3 Intelligent Tiering**: Automatic cost optimization for infrequently accessed files
4. **CloudFront**: Reduce origin requests with longer TTLs
5. **SES Dedicated IPs**: Only if needed for reputation management
6. **SMS Optimization**: Use short codes or toll-free numbers for better rates
7. **Voice Provider**: Compare SIP trunk providers for better per-minute rates

### Scaling Considerations

**At 100M messages/month** (10x scale):
- Messaging costs: ~$74,000/month
- Compute: ~$1,500/month (more workers)
- Database: ~$2,500/month (larger instances)
- **Total**: ~$80,000/month

**At 1B messages/month** (100x scale):
- Messaging costs: ~$740,000/month
- Compute: ~$5,000/month
- Database: ~$8,000/month (Aurora Serverless recommended)
- **Total**: ~$760,000/month


## Security Architecture

### 1. Network Security

#### VPC Isolation
- **Private Subnets**: All application and data tier resources in private subnets with no direct internet access
- **Public Subnets**: Only ALB and NAT Gateways in public subnets
- **Network ACLs**: Stateless firewall rules at subnet level
- **Security Groups**: Stateful firewall rules at resource level (principle of least privilege)

#### Network Flow Control
```
Internet → CloudFront → ALB (Public) → ECS (Private) → RDS/Redis (Private)
                                     ↓
                                 NAT Gateway → Internet (for AWS APIs)
```

#### DDoS Protection
- **AWS Shield Standard**: Automatic protection against L3/L4 attacks (included)
- **CloudFront**: Geographic restrictions, rate limiting
- **WAF**: Application-layer protection (L7)

---

### 2. Application Security

#### Authentication & Authorization

**JWT Authentication**
- **Token Expiry**: 1 hour access tokens, 7 days refresh tokens
- **Algorithm**: RS256 (asymmetric encryption)
- **Key Rotation**: Every 90 days
- **Storage**: Private keys in AWS Secrets Manager

**RBAC (Role-Based Access Control)**
- **Roles**: 
  - `platform_admin`: Full system access
  - `tenant_admin`: Full tenant scope access
  - `operator`: Limited tenant operations
- **Enforcement**: Laravel Gates and Policies
- **Audit**: All role changes logged to CloudWatch

**Tenant Context Middleware**
```php
// Validates user belongs to tenant
// Sets tenant scope for all queries
// Prevents cross-tenant data access
// Returns 403 if unauthorized
```

#### Input Validation
- **Request Validation**: Laravel Form Requests with strict rules
- **SQL Injection**: Eloquent ORM with parameterized queries
- **XSS Prevention**: Blade template escaping, CSP headers
- **CSRF Protection**: Laravel CSRF tokens for state-changing operations
- **Rate Limiting**: Per-user and per-tenant API rate limits

#### Data Sanitization
- **Email Addresses**: RFC 5322 validation
- **Phone Numbers**: E.164 format validation
- **File Uploads**: MIME type validation, virus scanning (ClamAV)
- **Template Content**: HTML purification (HTMLPurifier)

---

### 3. Data Security

#### Encryption at Rest

**Database (RDS)**
- **Method**: AES-256 encryption using AWS KMS
- **Key Management**: Customer-managed CMK with automatic rotation
- **Backup Encryption**: All automated backups encrypted
- **Snapshot Encryption**: All manual snapshots encrypted

**Cache (ElastiCache)**
- **Method**: AES-256 encryption at rest
- **Key Management**: AWS-managed keys
- **Replication**: Encrypted replication between nodes

**Storage (S3)**
- **Method**: SSE-KMS (Server-Side Encryption with KMS)
- **Key Management**: Customer-managed CMK
- **Bucket Policy**: Enforce encryption on all uploads
- **Versioning**: Enabled for audit trail

**ECS Task Storage**
- **Method**: EFS encryption with KMS
- **Ephemeral Storage**: Encrypted by default in Fargate

#### Encryption in Transit

**External Communication**
- **HTTPS Only**: TLS 1.3 minimum (TLS 1.2 fallback)
- **Certificate**: AWS Certificate Manager (ACM) with auto-renewal
- **Cipher Suites**: Strong ciphers only (ECDHE-RSA-AES256-GCM-SHA384)
- **HSTS**: Strict-Transport-Security header (max-age=31536000)

**Internal Communication**
- **RDS**: SSL/TLS required for all connections
- **Redis**: TLS enabled for all connections
- **Service-to-Service**: mTLS for ECS service mesh (optional)

#### Data Classification

| Data Type | Classification | Encryption | Retention |
|-----------|---------------|------------|-----------|
| User Credentials | Critical | KMS + Bcrypt | Indefinite |
| Contact PII | Sensitive | KMS | Per tenant policy |
| Message Content | Sensitive | KMS | 90 days |
| Campaign Metadata | Internal | KMS | 1 year |
| Delivery Events | Internal | KMS | 1 year |
| Audit Logs | Internal | KMS | 7 years |
| Billing Data | Sensitive | KMS | 7 years |

---

### 4. Access Control

#### IAM Roles & Policies

**ECS Task Role** (Principle of Least Privilege)
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "ses:SendEmail",
        "ses:SendRawEmail"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "ses:FromAddress": "noreply@contactsass.com"
        }
      }
    },
    {
      "Effect": "Allow",
      "Action": [
        "sns:Publish"
      ],
      "Resource": "arn:aws:sns:*:*:sms-*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject"
      ],
      "Resource": "arn:aws:s3:::contactsass-audio/*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue"
      ],
      "Resource": "arn:aws:secretsmanager:*:*:secret:contactsass/*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "kms:Decrypt"
      ],
      "Resource": "arn:aws:kms:*:*:key/*",
      "Condition": {
        "StringEquals": {
          "kms:ViaService": [
            "secretsmanager.us-east-1.amazonaws.com",
            "rds.us-east-1.amazonaws.com"
          ]
        }
      }
    }
  ]
}
```

**RDS IAM Authentication**
- **Method**: IAM database authentication (no passwords)
- **Token Lifetime**: 15 minutes
- **Rotation**: Automatic

**Secrets Manager Access**
- **Rotation**: Automatic every 30 days
- **Versioning**: Previous versions retained for 7 days
- **Access Logging**: All secret access logged to CloudTrail

#### Multi-Factor Authentication (MFA)
- **Required For**: Platform admins, tenant admins
- **Methods**: TOTP (Google Authenticator, Authy), SMS backup
- **Enforcement**: Laravel middleware for sensitive operations

---

### 5. Web Application Firewall (WAF)

#### Managed Rule Groups
- **AWS Core Rule Set**: OWASP Top 10 protection
- **Known Bad Inputs**: Common attack patterns
- **SQL Injection**: SQL injection prevention
- **XSS**: Cross-site scripting prevention

#### Custom Rules

**Rate Limiting**
```
Rule: API Rate Limit
- Condition: URI path starts with /api/
- Action: Block if > 1000 requests per 5 minutes per IP
- Priority: 1
```

**Geographic Restrictions**
```
Rule: Block High-Risk Countries
- Condition: Country in [list of high-risk countries]
- Action: Block
- Priority: 2
```

**Bot Protection**
```
Rule: Block Known Bots
- Condition: User-Agent matches bot patterns
- Action: Challenge (CAPTCHA)
- Priority: 3
```

**IP Reputation**
```
Rule: Block Malicious IPs
- Condition: IP in AWS Managed IP Reputation List
- Action: Block
- Priority: 4
```

#### WAF Logging
- **Destination**: S3 bucket with lifecycle policy (90 days)
- **Format**: JSON with full request details
- **Analysis**: Athena queries for threat intelligence

---

### 6. Monitoring & Incident Response

#### Security Monitoring

**CloudWatch Alarms**
- Failed login attempts > 10 in 5 minutes
- Unauthorized API calls (403/401) > 100 in 5 minutes
- Database connection failures > 5 in 1 minute
- Unusual data transfer volume (> 10GB in 1 hour)
- KMS key usage anomalies

**AWS GuardDuty** (Optional, +$30/month)
- Threat detection for AWS accounts
- Malicious IP detection
- Compromised instance detection
- Unusual API activity

**AWS Security Hub** (Optional, +$10/month)
- Centralized security findings
- Compliance checks (PCI-DSS, HIPAA, CIS)
- Automated remediation

#### Audit Logging

**CloudTrail**
- **Enabled**: All regions
- **Log File Validation**: Enabled
- **S3 Bucket**: Encrypted, versioned, MFA delete
- **Retention**: 7 years
- **Events Logged**:
  - IAM changes
  - Security group changes
  - KMS key usage
  - S3 bucket policy changes
  - RDS configuration changes

**Application Logs**
- **Format**: JSON structured logs
- **Fields**: timestamp, tenant_id, user_id, action, resource, ip_address, user_agent
- **Retention**: 30 days in CloudWatch, 1 year in S3
- **Events Logged**:
  - Authentication attempts
  - Authorization failures
  - Data access (PII)
  - Configuration changes
  - Campaign operations

#### Incident Response Plan

**Detection**
1. CloudWatch Alarms trigger SNS notifications
2. Security team receives alerts via PagerDuty
3. Automated runbooks execute initial triage

**Containment**
1. Isolate affected resources (security group changes)
2. Revoke compromised credentials
3. Enable additional logging
4. Snapshot affected instances/databases

**Eradication**
1. Identify root cause
2. Remove malicious code/access
3. Patch vulnerabilities
4. Update WAF rules

**Recovery**
1. Restore from clean backups
2. Verify system integrity
3. Monitor for reinfection
4. Gradual traffic restoration

**Post-Incident**
1. Document timeline and actions
2. Update runbooks
3. Implement preventive controls
4. Conduct team retrospective

---

### 7. Compliance & Governance

#### Data Privacy

**GDPR Compliance**
- **Right to Access**: API endpoint for data export
- **Right to Erasure**: Soft delete with 30-day retention
- **Data Portability**: JSON export format
- **Consent Management**: Explicit opt-in for marketing
- **Data Processing Agreement**: With all tenants

**CCPA Compliance**
- **Do Not Sell**: Opt-out mechanism
- **Data Disclosure**: Annual privacy report
- **Consumer Rights**: Access, delete, opt-out

**CAN-SPAM Compliance**
- **Unsubscribe Link**: Required in all emails
- **Sender Identification**: Clear from address
- **Subject Line**: No deceptive subjects
- **Physical Address**: Required in footer

**TCPA Compliance** (Voice/SMS)
- **Prior Express Consent**: Required for all contacts
- **Opt-Out Mechanism**: STOP keyword for SMS
- **Time Restrictions**: No calls before 8am or after 9pm
- **DNC List**: Integration with National Do Not Call Registry

#### Security Standards

**SOC 2 Type II** (Recommended)
- **Trust Principles**: Security, Availability, Confidentiality
- **Audit Frequency**: Annual
- **Controls**: 100+ security controls
- **Cost**: ~$50,000 initial, ~$25,000 annual

**PCI-DSS** (If storing payment data)
- **Level**: Depends on transaction volume
- **Requirements**: 12 requirements, 78 sub-requirements
- **Scope**: Payment processing components only
- **Cost**: ~$30,000 initial, ~$15,000 annual

#### Vulnerability Management

**Scanning**
- **Frequency**: Weekly automated scans
- **Tools**: AWS Inspector, Snyk, OWASP ZAP
- **Scope**: Container images, dependencies, infrastructure

**Patching**
- **OS Patches**: Automated via ECS task definition updates
- **Application Dependencies**: Dependabot alerts, weekly reviews
- **Database**: Automated minor version updates, manual major versions
- **Critical Vulnerabilities**: 24-hour SLA for patching

**Penetration Testing**
- **Frequency**: Quarterly
- **Scope**: External attack surface, API endpoints
- **Provider**: Third-party security firm
- **Remediation**: 30-day SLA for high/critical findings

---

### 8. Disaster Recovery & Business Continuity

#### Backup Strategy

**Database Backups**
- **Automated Backups**: Daily, 7-day retention
- **Manual Snapshots**: Before major changes
- **Cross-Region Replication**: To us-west-2 (DR region)
- **Backup Testing**: Monthly restore drills

**Application State**
- **ECS Task Definitions**: Versioned in Git
- **Configuration**: Stored in Parameter Store
- **Infrastructure**: Terraform state in S3 with versioning

**Recovery Objectives**
- **RPO (Recovery Point Objective)**: 1 hour (max data loss)
- **RTO (Recovery Time Objective)**: 4 hours (max downtime)

#### High Availability

**Multi-AZ Deployment**
- **RDS**: Synchronous replication to standby
- **ElastiCache**: Automatic failover to replica
- **ECS**: Tasks distributed across AZs
- **ALB**: Health checks with automatic failover

**Failover Testing**
- **Frequency**: Quarterly
- **Scenarios**: AZ failure, RDS failover, Redis failover
- **Validation**: Application functionality, data integrity

#### Disaster Recovery Plan

**Scenario 1: Single AZ Failure**
- **Impact**: Reduced capacity, no data loss
- **Action**: Automatic failover (< 5 minutes)
- **Recovery**: AWS handles AZ restoration

**Scenario 2: Regional Failure**
- **Impact**: Full outage
- **Action**: Manual failover to DR region (us-west-2)
- **Recovery**: 4 hours RTO
- **Steps**:
  1. Promote RDS read replica in DR region
  2. Update Route 53 to point to DR region ALB
  3. Scale up ECS tasks in DR region
  4. Verify application functionality

**Scenario 3: Data Corruption**
- **Impact**: Partial data loss
- **Action**: Point-in-time restore from backup
- **Recovery**: 2 hours RTO
- **Steps**:
  1. Identify corruption timestamp
  2. Restore RDS snapshot to new instance
  3. Perform point-in-time recovery
  4. Validate data integrity
  5. Cutover to restored database

---

## Security Best Practices Checklist

### Infrastructure
- [x] VPC with private subnets for all sensitive resources
- [x] Security groups with least privilege rules
- [x] Network ACLs for additional layer of defense
- [x] NAT Gateways for outbound internet access
- [x] VPC Flow Logs enabled for network monitoring
- [x] AWS Shield Standard for DDoS protection
- [x] WAF with managed and custom rules

### Application
- [x] JWT authentication with short-lived tokens
- [x] RBAC with granular permissions
- [x] Tenant context middleware for isolation
- [x] Input validation on all user inputs
- [x] Output encoding to prevent XSS
- [x] CSRF protection on state-changing operations
- [x] Rate limiting per user and tenant
- [x] Secure session management

### Data
- [x] Encryption at rest (RDS, Redis, S3, ECS)
- [x] Encryption in transit (TLS 1.3)
- [x] KMS for key management with rotation
- [x] Secrets Manager for credential storage
- [x] IAM database authentication
- [x] Data classification and retention policies
- [x] Regular backup testing

### Monitoring
- [x] CloudWatch Alarms for security events
- [x] CloudTrail for audit logging
- [x] Application logging with sensitive data masking
- [x] WAF logging for threat analysis
- [x] Incident response plan documented
- [x] Security metrics dashboard

### Compliance
- [x] GDPR compliance (data privacy)
- [x] CAN-SPAM compliance (email)
- [x] TCPA compliance (voice/SMS)
- [x] Regular vulnerability scanning
- [x] Penetration testing quarterly
- [x] Security awareness training

### Operations
- [x] Automated patching for OS and dependencies
- [x] Multi-AZ deployment for high availability
- [x] Disaster recovery plan with tested failover
- [x] Backup strategy with cross-region replication
- [x] Change management process
- [x] Security incident response runbooks

---

## Security Recommendations

### Immediate (Month 1)
1. Enable AWS GuardDuty for threat detection
2. Implement MFA for all admin accounts
3. Configure CloudTrail with log file validation
4. Set up security monitoring dashboard
5. Document incident response procedures

### Short-term (Months 2-3)
1. Conduct initial penetration test
2. Implement automated vulnerability scanning
3. Enable AWS Config for compliance monitoring
4. Set up automated backup testing
5. Conduct disaster recovery drill

### Long-term (Months 4-6)
1. Pursue SOC 2 Type II certification
2. Implement AWS Security Hub
3. Deploy AWS WAF Bot Control
4. Implement service mesh with mTLS
5. Conduct security awareness training program

