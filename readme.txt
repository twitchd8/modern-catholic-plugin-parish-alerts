=== Parish Alerts ===
Contributors: twitchd8
Tags: alerts, announcements, cancellations, parish
Requires at least: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish scheduled parish alerts with an always-available alert center, priority acknowledgments, and opt-in browser notifications.

== Description ==

Parish Alerts adds an Alerts content area for cancellations, closures, emergencies, and other time-sensitive notices.

An alert is active when it is published, its optional start time has passed, and its optional end time has not passed. Visitors always see a floating Alerts button. Its number shows only active alert revisions that have not been read in that browser.

The alert center provides individual and "Mark all as read" controls. Important and Emergency alerts also open once in an acknowledgment modal. Notice alerts remain available without interrupting the visitor.

On HTTPS sites, visitors may explicitly enable browser notifications from the alert center. Important and Emergency alerts can then send a Web Push notification when they become active; Notice alerts never send push notifications. Push delivery is self-hosted using the site's VAPID identity and WordPress cron. No third-party notification account is required.

Administrators can review privacy-safe subscription counts, configuration state, the next scheduled delivery, and the last delivery result under Alerts > Notifications. For timely production delivery, configure the host to run WordPress cron reliably.

== Privacy ==

When a visitor opts in, the plugin stores that browser's anonymous push endpoint and public encryption keys. It does not store a name, email address, account, notification history, or raw IP address. Invalid subscriptions are removed automatically. Deleting the plugin removes subscription records and the site's VAPID keys, while retaining Alert posts and their editorial schedule data.

== Installation ==

1. Upload the `parish-alerts` folder to `/wp-content/plugins/`.
2. Activate Parish Alerts.
3. Add and publish alerts from Alerts in the WordPress dashboard.

== Changelog ==

= 1.0.0 =
* Promote the tested alert center, acknowledgments, priority modal, scheduling, and opt-in Web Push delivery as the first stable release.

= 0.3.0 =
* Add explicit per-browser Web Push opt-in controls on secure sites.
* Allow Important and Emergency alerts to notify subscribers immediately or at their scheduled start time.
* Add anonymous subscription storage, automatic expired-subscription cleanup, and bounded retries.
* Add an Alerts > Notifications diagnostics screen.
* Bundle the audited open-source Web Push runtime and generate a site-specific VAPID identity.

= 0.2.0 =
* Keep the Alerts button available even when no alerts are active.
* Count only unseen active alert revisions in the badge.
* Add individual and bulk acknowledgment controls stored per browser.
* Show unseen Important and Emergency alerts in an accessible combined modal.

= 0.1.0 =
* Initial custom post type, scheduling, active-alert archive, and accessible sitewide popover.
