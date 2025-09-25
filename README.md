# TextYess Integration (Magento 2)

**Module:** `TextYess_Integration`  
**Version:** 1.0.0  

## Overview
This Magento 2 module integrates with [TextYess](https://textyess.com) to send **real-time webhooks** for key order events:

- **Order Created** → `order.created`
- **Order Shipment / Tracking Added** → `order.tracking`

### Webhook Request Headers
Each webhook includes:
- `x-magento-hmac-sha256`: Base64-encoded HMAC-SHA256 signature of the payload  
- `x-magento-topic`: Event topic name (`order.created` or `order.tracking`)  
- `x-textyess-user`: Your TextYess User ID  

Payloads are sent as JSON (`Content-Type: application/json`).

---

## Requirements
- **Magento:** 2.4.x (tested)  
- **PHP:** `>=7.4 <8.3`  
- No external dependencies (uses Magento’s built-in `Curl` client).  

---

## Installation

This module is available on [Packagist](https://packagist.org/packages/textyess/module-integration).

From your Magento 2 root directory:

```bash
composer require textyess/module-integration
php bin/magento module:enable TextYess_Integration
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush

```

Composer will download the module into vendor/textyess/module-integration automatically.

## Configuration

Navigate to:

Admin Panel → Stores → Configuration → General →TextYess Integration → General Settings

Settings:

Enable Integration = Yes

Webhook Base URL = https://gateway.textyess.com/webhooks/magento/orders

TextYess User ID = (provided by TextYess)

HMAC Secret = (provided by TextYess)

Enable Debug Logging = Optional (recommended for staging/troubleshooting)

Save configuration and clear cache.

## Uninstallation

To remove the module completely:

```bash
php bin/magento module:disable TextYess_Integration
composer remove textyess/module-integration
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```