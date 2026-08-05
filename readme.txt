=== Parish Alerts ===
Contributors: twitchd8
Tags: alerts, announcements, cancellations, parish
Requires at least: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish scheduled parish alerts and show a sitewide alert popover only while alerts are active.

== Description ==

Parish Alerts adds an Alerts content area for cancellations, closures, emergencies, and other time-sensitive notices.

An alert is active when it is published, its optional start time has passed, and its optional end time has not passed. When one or more alerts are active, visitors see a floating Alerts button. The button opens a popover with the newest alerts and a link to the complete active-alert list at `/alerts/`.

== Installation ==

1. Upload the `parish-alerts` folder to `/wp-content/plugins/`.
2. Activate Parish Alerts.
3. Add and publish alerts from Alerts in the WordPress dashboard.

== Changelog ==

= 0.1.0 =
* Initial custom post type, scheduling, active-alert archive, and accessible sitewide popover.
