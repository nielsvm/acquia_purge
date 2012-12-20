What?
================================================================================
The Acquia Purge module fills in the gap for customers running on Acquia Cloud
products such as Acquia Dev Cloud and Acquia Managed Cloud that are in need of
an effective proactive purging solution for their site. This module offers a
turn-key experience in the sense that it automatically purges your content upon
content updates and creation without any necessary technical configuration.

Features:
 * Simple yet-effective proactive purging of nodes.
 * Turn-key installation, no configuration needed.
 * Optionally on-screen reporting of the purged resources.
 * Automatic purging of the front page when necessary.
 * Automatic purging of /rss.xml when necessary.
 * Transparent purging of the Drupal Page Cache as well.
 * Fully automatic domain detection as configured on the Domains pane on your
   Acquia Network workflow page. Each domain attached to your site and active
   environment will be automatically purged.
 * Optional integration with the expire project, allowing you to purge a lot
   more context-sensitive URL's proactively like terms, menu links and many more.

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

Installation?
================================================================================
Installing the project is fairly simple, just download the project as any other
Drupal.org project and enable it on whichever environment you are. Please *note*
that the module only works when using your site on Acquia Cloud and stays
silent and harmless elsewhere. By default the cache purging notifications are
enabled allowing you to immediately test the purging when enabled.

How?
================================================================================
This module provides the means to purge a list of paths from Varnish without
having to deal with any technical details and basically provides the following
API level components that other modules or your custom tailored integration
module can leverage:
 * acquia_purge_purge_path($path, $domains = NULL)
 * acquia_purge_purge_paths($paths, $domains = NULL)
 * acquia_purge_purge_node(&$node, $domains = NULL)

In addition to that the module works with or without the expire module, the
choice is up to you. What's great about the expire project is that it provides a
generic framework that other modules can integrate with whenever they feel like
one or several HTTP url's should expire from cache, for instance when a
particular node updated it's title while it's also inside a node queue. In many
of those cases the expire module will send out a signal to purge a set of URLs
which the Acquia Purge module will perform for you. In any case you could also
extend the expire module with your own custom needs and leverage the power of
both modules.

Future plans?
================================================================================
There are several ideas and plans on a relatively vague roadmap in addition to
further testing, code cleanups and fixing bugs:
 * Code cleanups and adherence to every relevant policy or standard required.
 * Writing tests as far as this is technically possible without access to Varnish.
 * Enabling full site cache clears from the performance page, requires Cloud API
   access to be enabled for your subscription.
 * More fine tuning and catching more purging cases out-of-the-box.
 * Upstream contributions to improve the expire module even more.
 * Module backport to D6, although possibilities work there like the purge module.
