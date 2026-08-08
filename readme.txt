=== Parish Alerts ===
Contributors: twitchd8
Tags: alerts, announcements, cancellations, parish
Requires at least: 6.7
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish scheduled parish alerts with an always-available alert center, unseen counts, and priority acknowledgments.

== Description ==

Parish Alerts adds an Alerts content area for cancellations, closures, emergencies, and other time-sensitive notices.

An alert is active when it is published, its optional start time has passed, and its optional end time has not passed. Visitors always see a floating Alerts button. Its number shows only active alert revisions that have not been read in that browser.

The alert center provides individual and "Mark all as read" controls. Important and Emergency alerts also open once in an acknowledgment modal. Notice alerts remain available without interrupting the visitor.

== Installation ==

1. Upload the `parish-alerts` folder to `/wp-content/plugins/`.
2. Activate Parish Alerts.
3. Add and publish alerts from Alerts in the WordPress dashboard.

== Changelog ==

= 0.2.0 =
* Keep the Alerts button available even when no alerts are active.
* Count only unseen active alert revisions in the badge.
* Add individual and bulk acknowledgment controls stored per browser.
* Show unseen Important and Emergency alerts in an accessible combined modal.

= 0.1.0 =
* Initial custom post type, scheduling, active-alert archive, and accessible sitewide popover.
