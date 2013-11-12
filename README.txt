What?
================================================================================
The Acquia Purge module fills in the gap for customers running on Acquia hosting
products such as Acquia Cloud and Acquia Cloud Enterprise that are in need of
an effective proactive purging solution for their site. This module offers a
turn-key experience in the sense that it automatically purges your content upon
content updates and creation without any necessary technical configuration.

Features:
 * Simple and effective purging based on the expire module.
 * Turn-key installation, no configuration needed.
 * On-screen reporting of the purged resources.
 * Drupal watchdog logging to create a purged paths trail.
 * Queue (API) based processing ensuring scalability and accuracy.
 * Transparent purging of cached pages in Drupal's page cache.
 * Fully automatic domain detection as configured on the Domains pane on your
   Acquia Network workflow page. Each domain attached to your site and active
   environment will be automatically purged.
 * Support for Domain Access and multi-sites, see DOMAINS.txt.
 * Integration with Rules allowing you to purge arbitrary paths like /news.
 * Built-in tests on status report page and automatic alerts and warnings.
 * Drush: ap-diagnosis, ap-domains, ap-forget, ap-list, ap-process, ap-purge.

Why?
================================================================================
Many of our clients rely on Drupal's minimum cache lifetime- and expiration of
cached pages configuration settings and are used to setting these to very low
values, like several minutes. This causes all Drupal generated pages - which
excludes most static assets - to be kept in Varnish for a relatively short
amount of time regardless if they changed or not.

By applying the process of proactive purging these expiry times can be set to a
very long time - for instance once a day - and build on the assumption that
Drupal will actively tell Varnish what URL resources have to be removed from
cache. The Acquia Purge module understands our platform and notifies Varnish for
each possible URL representation in existence whenever you save a node for
instance. This empowers your site to make significantly more use of the platform
we provide and increases your cache effectiveness, thus allowing you to handle
more load on the same hardware.
