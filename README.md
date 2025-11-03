# FreePBX VoIP Platform

A comprehensive enterprise-grade VoIP platform built with Laravel that integrates with FreePBX for call management, billing, and customer portal functionality.

## 🚀 Features

### Core Functionality
- **User Authentication & Authorization** - Role-based access control (Admin, Customer, Operator)
- **FreePBX Integration** - Complete API integration for call management and CDR processing
- **Billing Engine** - Advanced billing system with rate management and cost calculation
- **Payment Processing** - Multi-gateway support (PayPal, NowPayments cryptocurrency)
- **Customer Portal** - Self-service dashboard for customers
- **Admin Panel** - Comprehensive management interface
- **RESTful API** - Complete API for mobile and third-party integration

### Advanced Features
- **Security** - Enterprise-grade security with audit logging, encryption, and monitoring
- **Monitoring** - Real-time system health monitoring and alerting
- **Performance** - Database optimization, caching, and performance monitoring
- **Deployment** - Production-ready deployment configurations
- **Testing** - Comprehensive test suite (Unit, Integration, Feature tests)

### Production Features
- **Advanced Monitoring** - DID inventory monitoring, billing accuracy checks, real-time performance monitoring
- **Multi-Channel Alerting** - Email, Slack, SMS, and webhook notifications for critical issues
- **Database Optimization** - Advanced indexes, partitioning for high-volume tables, automated maintenance
- **Cron Job Management** - Comprehensive monitoring, health checks, backup and recovery systems
- **Production Deployment** - Complete cron job configuration, log rotation, and maintenance scripts
- **Advanced Logging** - Specialized logging channels with rotation and cleanup procedures

## 📋 Requirements

- PHP 8.1+
- Laravel 12.x
- MySQL/MariaDB or SQLite
- Redis (optional, for production)
- Composer
- Node.js & NPM (for frontend assets)

## 🛠 Installation

### Quick Start

```bash
# Clone the repository
git clone https://github.com/maikama8/freepbxapiportal.git
cd freepbxapiportal

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Storage link
php artisan storage:link

# Start development server
php artisan serve
```

### Production Setup

For production deployment, use the provided deployment scripts and configuration:

```bash
# Production environment setup
sudo ./deployment/scripts/setup-production.sh

# Database optimization and maintenance
sudo ./deployment/scripts/database-maintenance.sh /var/www/html/voip-platform /usr/bin/php

# Cron job monitoring setup
sudo ./deployment/scripts/setup-cron-monitoring.sh /var/www/html/voip-platform /usr/bin/php admin@yourdomain.com

# Database replication (optional)
sudo ./deployment/scripts/setup-database-replication.sh
```

#### Cron Job Configuration

The platform requires several cron jobs for optimal operation. Choose your setup method:

**For cPanel hosting:**
```bash
# Use the cPanel-specific configuration
cat deployment/cron/cpanel-cron-setup.txt
# Follow the instructions to add each job in cPanel
```

**For direct server access:**
```bash
# Install the production crontab
crontab deployment/cron/production-crontab.txt
# Edit paths and email addresses as needed
```

**For systemd-based systems:**
```bash
# Generate and install systemd services
php artisan cron:generate-setup --type=systemd --output=/tmp/laravel-scheduler
sudo cp /tmp/laravel-scheduler.* /etc/systemd/system/
sudo systemctl enable laravel-scheduler.timer
sudo systemctl start laravel-scheduler.timer
```

#### Database Optimization

Run database optimization for production:

```bash
# Apply advanced performance indexes
php artisan migrate --force

# Run database performance monitoring
php artisan db:performance-monitor --report --optimize

# Set up automated maintenance
php artisan db:maintenance --cleanup --optimize --analyze
```

## ⚙️ Configuration

### Environment Variables

Key configuration options in `.env`:

```env
# Application
APP_NAME="FreePBX VoIP Platform"
APP_ENV=production
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=voip_platform
DB_USERNAME=your_username
DB_PASSWORD=your_password

# FreePBX API
FREEPBX_API_URL=https://your-freepbx.com/admin/api
FREEPBX_API_KEY=your_api_key
FREEPBX_API_SECRET=your_api_secret

# Payment Gateways
NOWPAYMENTS_API_KEY=your_nowpayments_key
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_secret

# Email
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
```

## 🎯 Usage

### Admin Panel
Access the admin panel at `/admin/dashboard` with admin credentials:
- Customer management
- Rate management
- System monitoring
- Payment configuration
- Audit logs

### Customer Portal
Customers can access their portal at `/dashboard`:
- Account balance and call history
- Make calls through the interface
- Payment processing
- Invoice management

### API Documentation
API documentation is available at `/api/docs` with endpoints for:
- Authentication
- Call management
- Payment processing
- Account management

## 🔧 Console Commands

The platform includes several useful Artisan commands:

### System Monitoring
```bash
# Advanced system monitoring with alerts
php artisan system:advanced-monitor --alert --report

# Database performance monitoring
php artisan db:performance-monitor --report --optimize

# Cron job health monitoring
php artisan cron:health-monitor --alert

# System health check
php artisan system:health-check --alert
```

### Database Management
```bash
# Database maintenance and optimization
php artisan db:maintenance --optimize --cleanup --analyze

# Database performance monitoring
php artisan db:performance-monitor --report

# Clean up old records
php artisan db:cleanup --table=audit_logs --days=90
```

### Billing and CDR Processing
```bash
# Automated CDR processing
php artisan cdr:automated-processing --batch-size=50

# Real-time billing monitoring
php artisan billing:monitor-realtime --terminate

# Monthly DID billing
php artisan billing:monthly-did-charges --suspend-insufficient

# FreePBX synchronization
php artisan freepbx:automated-sync --sync-mode=incremental
```

### Cron Job Management
```bash
# Monitor cron job status
php artisan cron:monitor status

# Generate cron setup scripts
php artisan cron:generate-setup --type=cpanel

# Kill stuck cron jobs
php artisan cron:monitor kill-stuck --max-runtime=120

# Performance reporting
php artisan cron:performance-report --days=7
```

## 📊 Monitoring & Alerting

### Web Dashboards
- **Admin Monitoring**: `/admin/monitoring` - System health and performance metrics
- **Cron Job Management**: `/admin/cron-jobs` - Cron job status and history
- **Billing Configuration**: `/admin/billing/configuration` - Advanced billing settings
- **Automation Monitoring**: `/admin/automation/monitoring` - Automated process monitoring

### Alerting Channels
Configure alerting in your `.env` file:

```env
# Email Alerts
ALERT_EMAIL_ENABLED=true
ALERT_EMAIL_RECIPIENTS=admin@yourdomain.com,ops@yourdomain.com

# Slack Alerts
ALERT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
ALERT_SLACK_USERNAME="VoIP Platform Alerts"
ALERT_SLACK_EMOJI=":warning:"

# Log Retention
LOG_BILLING_DAYS=90
LOG_SECURITY_DAYS=365
LOG_PAYMENTS_DAYS=365
LOG_ALERTS_DAYS=365
```

### Monitoring Features
- **DID Inventory Monitoring** - Alerts when DID inventory runs low
- **Billing Accuracy Monitoring** - Detects billing discrepancies and unprocessed calls
- **Real-time Billing Monitoring** - Monitors active calls and balance enforcement
- **System Performance Monitoring** - CPU, memory, disk usage, and response time monitoring
- **Cron Job Monitoring** - Tracks job execution, failures, and performance
- **Database Performance Monitoring** - Query performance, index usage, and optimization recommendations

# System backup
php artisan backup:system --compress

# Security audit
php artisan security:audit

# Email testing
php artisan email:test user@example.com

# Generate monthly invoices
php artisan invoices:generate-monthly

# Send low balance warnings
php artisan balance:send-warnings
```

## 🧪 Testing

Run the comprehensive test suite:

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Integration

# Run with coverage
php artisan test --coverage
```

## 📊 Monitoring

The platform includes built-in monitoring accessible at `/admin/monitoring`:
- System health metrics
- Database performance
- Call and payment statistics
- Real-time logs
- Performance analytics

## 🔒 Security Features

- **Authentication** - Laravel Sanctum with role-based access
- **Input Validation** - Comprehensive form validation and sanitization
- **Encryption** - Database field encryption for sensitive data
- **Audit Logging** - Complete audit trail of administrative actions
- **Security Headers** - CSRF, XSS, and other security protections
- **Rate Limiting** - API and login attempt rate limiting
- **Session Security** - Secure session management with timeout

## 🚀 Deployment

### Production Deployment

1. **Server Setup** - Use the provided Nginx and PHP-FPM configurations
2. **Database** - Set up MySQL/MariaDB with replication for high availability
3. **Redis** - Configure Redis clustering for caching and sessions
4. **SSL** - Enable HTTPS with proper SSL certificates
5. **Monitoring** - Set up automated health checks and alerting

### Docker Support

Docker configurations are available in the `deployment/` directory for containerized deployment.

## 📈 Performance

The platform is optimized for performance with:
- Database indexing and query optimization
- Redis caching and session management
- Optimized Nginx and PHP-FPM configurations
- Database replication support
- Automated maintenance and cleanup

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation in `/docs`
- Review the API documentation at `/api/docs`

## 🏗 Architecture

The platform follows Laravel best practices with:
- **MVC Architecture** - Clean separation of concerns
- **Service Layer** - Business logic in dedicated service classes
- **Repository Pattern** - Data access abstraction
- **Event-Driven** - Event listeners for notifications and logging
- **API-First** - RESTful API design with web interface

## 📊 Database Schema

Key database tables:
- `users` - User accounts with roles and VoIP settings
- `call_records` - Call detail records and billing information
- `call_rates` - Rate tables for call pricing
- `payment_transactions` - Payment processing and history
- `invoices` - Invoice generation and management
- `audit_logs` - Security and administrative audit trail

---

**Built with ❤️ using Laravel and modern web technologies**