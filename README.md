# Modern Catholic Plugin Suite

Part of **Modern Catholic** — modular WordPress tools for Catholic parish websites.

---

# Modern Catholic – Parish Alerts

![License: GPL-3.0-only](https://img.shields.io/badge/License-GPL--3.0--only-blue.svg)
![WordPress: 6.7+](https://img.shields.io/badge/WordPress-6.7%2B-21759b.svg)
![PHP: 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bbb.svg)

Scheduled parish alerts with an always-available alert center, priority acknowledgments, and optional self-hosted Web Push notifications.

---

## Features

- Standardized `mc_alert` custom post type with automatic migration from `parish_alert`
- Optional start and end times for scheduled notices, closures, cancellations, and emergencies
- Persistent alert-center button with unread counts stored per browser
- Accessible acknowledgment modal for unseen Important and Emergency alerts
- Individual and bulk “Mark as read” controls
- Explicit browser opt-in for self-hosted Web Push on secure sites
- WordPress cron delivery, bounded retries, expired-subscription cleanup, and administrator diagnostics

---

## Privacy and notifications

Web Push is optional and requires HTTPS. When a visitor opts in, the plugin stores an anonymous browser endpoint and public encryption keys. It does not store a name, email address, raw IP address, or notification history.

Notice alerts never send push notifications. Important and Emergency alerts may notify subscribers immediately or when their scheduled start time arrives.

---

## Installation

1. Upload or clone `modern-catholic-plugin-parish-alerts` into `wp-content/plugins/`.
2. Activate **Modern Catholic – Parish Alerts**.
3. Create and publish alerts from **Alerts** in the WordPress dashboard.
4. For Web Push, serve the production site over HTTPS and review **Alerts → Notifications**.

---

## Changelog

### 1.0.3

- Adopt the Modern Catholic semantic color contract for actions, surfaces, text, borders, focus, and alert status roles.
- Preserve WordPress preset and literal fallbacks when the Modern Catholic theme is unavailable.

### 1.0.2

- Standardize the GitHub README with Modern Catholic branding, compatibility badges, installation guidance, and GPL-3.0-only licensing.

### 1.0.1

- Standardize the post type key as `mc_alert` and migrate existing Alert posts.

### 1.0.0

- Promote the tested alert center, acknowledgments, priority modal, scheduling, and opt-in Web Push delivery as the first stable release.

---

## License

Licensed under the GNU General Public License version 3.0 only (`GPL-3.0-only`). Third-party dependencies retain their original licenses; see `THIRD-PARTY-NOTICES.md`.
