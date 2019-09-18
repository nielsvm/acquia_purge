[//]: # ( clear&&curl -s -F input_files[]=@PROJECTPAGE.md -F from=markdown -F to=html http://c.docverter.com/convert|tail -n+11|head -n-2|sed 's/\&#39;/\"/g'|sed 's/\&amp;/\&/g'|sed 's/\&quot;/\"/g' )
[//]: # ( curl -s -F input_files[]=@PROJECTPAGE.md -F from=markdown -F to=pdf http://c.docverter.com/convert>PROJECTPAGE.pdf )

**_Top-notch cache invalidation on Acquia Cloud!_**

The ``acquia_purge`` module invalidates cached content on Acquia Cloud
and allows you to set Drupal's _time to live (TTL)_ to a high value like
a year. This makes your site more resilient, the stack do less work and
improves the performance of your site dramatically!

## When do I need this?
We recommend nearly **all Acquia customers to set this up**, but especially
if any of this sounds familiar:

* Traffic spikes quickly take down your site.
* Pages are often slow and take more than 2-3 seconds to load.
* Your site is constantly dealing with slow queries or other heavy processing.

## What time does it take?
The ``acquia_purge`` module works on top of the ``purge``
[cache invalidation framework](https://www.drupal.org/project/purge) and offers
a _turn-key_ experience.

Setting it up shouldn't take more then 15 minutes, see the
[installation instructions](http://cgit.drupalcode.org/acquia_purge/plain/INSTALL.md).

#### Drupal 7
Owners of Drupal 7 sites are advised to schedule _at least one day_ of testing
and tuning to ensure that every section of their site is covered, as the
``expire`` [module](https://www.drupal.org/project/expire) won't cover
everything and requires you to set up rules for missing coverage areas. See its
[README](http://cgit.drupalcode.org/acquia_purge/plain/README.md?h=7.x-1.x),
[installation instructions](http://cgit.drupalcode.org/acquia_purge/plain/INSTALL.md?h=7.x-1.x)
and especially its
[domains](http://cgit.drupalcode.org/acquia_purge/plain/DOMAINS.md?h=7.x-1.x)
documentation.
