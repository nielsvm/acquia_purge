 ACQUIA PURGE - README FILE - Niels van Mourik <niels.vanmourik@acquia.com>
--------------------------------------------------------------------------------

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
 * Transparent purging of cached pages in Drupal's page cache.
 * Fully automatic domain detection as configured on the Domains pane on your
   Acquia Network workflow page. Each domain attached to your site and active
   environment will be automatically purged.
 * Flexible multi-site support with domains overriding, see MULTISITE.txt.
 * Integration with Rules allowing you to purge arbitrary paths like /news.
 * Detailed status-report tests which advocates the best possible configuration.

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

How about the purge module then?
--------------------------------------------------------------------------------
The purge module has been created for this very purpose yet isn't currently
designed with the Acquia platform in mind and makes it therefore difficult
to setup purging. While the purge module contributor and I work together to
improve version 2.x of Purge this module serves as the go-to point for the
interim. This module will get a dependency on purge and become its plugin in the
future.

Installation
================================================================================
Installing the project is fairly simple, just download the project as any other
Drupal.org project and enable it on whichever environment you are. Please *note*
that the module only works when using your site on Acquia Cloud and stays
silent and harmless elsewhere. By default the cache purging notifications are
enabled allowing you to immediately test the purging when enabled. You will also
need to install the expire module.

Maximizing the effects of proactive purging
================================================================================
When you install this module together with the expire module about 90% of all
changing objects within your site are detected, such as nodes, taxonomy tags
and menu items. Although the expire module does a hard job on detecting
everything in your site it might not always be aware of your site specific
features like news sections or dynamic blocks on your front page.

A couple of examples you could run into:
 * Nodes of type news get purged yet the paged view on /news is missed. You
   would probably also like to purge the first few sub pages, e.g.:
   /news?page=1, /news?page=2, /news?page=3
 * You have job descriptions in a tagcloud on your front page ("<front>") and
   whenever new taxonomy tags are added to the jobs vocabulary the front page
   is not purged because the expire module doesn't detect it for you.
 * Whenever a new user account is added to Drupal the "about our team" page
   on "team" is not being refreshed.

These scenarios are examples of the remaining 10% of things that can't be auto
detected with the expire module and for those custom needs the Acquia Purge
module comes with rules integration built in. By defining a standard rule that
listens to the event you need - for instance changing content of type X - you
can fire the "Purge a path from Varnish on Acquia Cloud" action on paths like
"news" and "news?page=1". By enabling the on-screen logging facility you can
quickly test your rules or rely on your log files.

Once it works and runs in production
--------------------------------------------------------------------------------
With Varnish load balancing your site and proactive purging in place you can
start increasing the "Minimum cache lifetime" and "Expiration of cached pages"
values. For instance by putting these settings to a couple of hours or even a
day your web servers will only work for authenticated traffic and pages that
have just been purged and thus increases the effective usage of what your
platform provides significantly.

API level integration
--------------------------------------------------------------------------------
In case you need code level integration for some reason we either advise you
to contribute to the expire module or if your needs are very site specific, to
leverage the API functions that Acquia Purge exposes. If you need it you can
send your path or list of paths to these helper functions:

 * acquia_purge_purge_path($path, $domains = NULL)
 * acquia_purge_purge_paths($paths, $domains = NULL)
 * acquia_purge_purge_node(&$node, $domains = NULL)
