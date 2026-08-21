# Parish Alerts Repository Rules

- Authoritative plugin/version file: `parish-alerts.php`.
- Preserve `mc_alert` and the legacy `parish_alert` migration contract.
- Run PHP syntax checks only on changed PHP files by default.
- Web Push browser permission and live delivery require a trusted HTTPS origin; plain HTTP LocalWP can cover only server-side behavior.
- Uninstall removes the Web Push subscription table and VAPID options but intentionally retains Alert posts and schedule metadata.
- Treat page requests, REST routes, service-worker responses, scheduling, notification diagnostics, permissions, and live delivery as smoke tests governed by the inherited approval gate.
