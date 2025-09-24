# Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2025-09-24
### Added
- Initial release of `TextYess_Integration` module.
- Real-time webhook event support for:
  - `order.created`
  - `order.tracking`
- Secure HMAC-SHA256 payload signing.
- Configurable settings:
  - Webhook Base URL  
  - User ID  
  - HMAC Secret  
  - Enable/Disable Integration  
  - Optional Debug Logging
- Logging of webhook payloads (when debug mode is enabled).

### Changed
- N/A (initial release)

### Fixed
- N/A (initial release)

---

## Upcoming
- Additional webhook events (e.g., `checkout.abandoned`, `product.updated`, `contact.created`).
