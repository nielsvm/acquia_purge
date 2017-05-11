[//]: # ( clear&&curl -s -F input_files[]=@DOMAINS.md -F from=markdown -F to=html http://c.docverter.com/convert|tail -n+11|head -n-2 )
[//]: # ( curl -s -F input_files[]=@DOMAINS.md -F from=markdown -F to=pdf http://c.docverter.com/convert>PROJECTPAGE.pdf )

# Domain detection

Whenever Acquia Purge tries to wipe pages (e.g. "``news``" or "``news/``") from
your site it will reconstruct URLs later and determine which domains associated
to your site should be cleaned. On most simple Drupal sites running on Acquia
Cloud it should work right away by just purging your primary domain name.

However, with even just a redirecting www- domain for instance, it becomes
necessary to tune the configuration by telling Acquia Purge what domain names
should really be cleared. Only those domains that really receive traffic should
be cleared as each domain comes with a serious performance cost. This cost
translates in Acquia Purge automatically throttling itself, the less domains
you receive traffic on and clear caches for, the better.

Another common cause and need for tuning, is when your site is part of a
multi-site configuration that Acquia Purge didn't properly detect or when you
simply have more than 8 domains. To protect your site and servers you should
aim to purge no more than 1-4 domains, but the module will self-shutdown
whenever it detects more then 8 domains.

### Manual domain name configuration

Whenever the domains aren't right, put this in ``settings.php``:

```
/**
 * Override domain detection in Acquia Purge.
 */
if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  if ($_ENV['AH_SITE_ENVIRONMENT'] == 'prod') {
    $conf['acquia_purge_domains'] = array(
      'www.site.com',
      'www.site.nl',
      'www.site.de',
    );
  }
}
```

### How domain detection works

The module does a good job in detecting 80% of the right domains and performs a
series of checks and tests automatically. To better understand what data it uses
to determine the domains, it's good to know what things it checks:

1. Take all hardcoded domains in ``$conf['acquia_purge_domains']`` and stop.

If not manually configured, these steps happen:
2. Take the current ``HTTP_HOST`` the user is using to visit the site right now.
3. Take the domain name found in ``$base_url`` when it differs with 2.
4. Interpret any domains (without path) found in ``sites/sites.php``.
5. Add all domains on the "_Domains_" page of the Acquia Cloud subscription.

### Listing the domains

You can easily list the domains that will be purged using "``drush ap-domains``"
or its alias "``drush apdo``". When you didn't hardcode domains in
``settings.php`` or in ``sites.php``, the detection will use environment
parameters. Therefore it's important to always pass ``--uri`` to Drush to
simulate real-world usage:

```
  $ drush ap-domains --uri="http://www.site.com"
  www.site.com
```

Seeing domains that shouldn't be cleared? Configure manually!

### How to configure multi-sites?

A multi-site is a shared document root in which multiple sites are hosted and
thus multiple unrelated domain names connect to. The difficulty here is for
Acquia Purge to distinguish which domains belong to what connected site, and
might or might not need help in doing so.

In any case, you will need to follow the testing steps here. If you don't, you
end up risking that Acquia Purge is going to purge paths on the wrong sites or
worse, shuts down as it reached its domains safety limit. Even before you start,
you should set up a file called ``sites/sites.php``, if you haven't done this
already. Drupal can work without this file, but Acquia Purge MUST have it in
order to be able to reverse map sites back to domain names.

The second immediate recommendation, is that you leverage an environment
variable named ``$_ENV['AH_SITE_ENVIRONMENT']`` to dynamically list the right
domains according to the environment the site is running on:

```
if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  switch ($_ENV['AH_SITE_ENVIRONMENT']) {
    case 'test':
      $sites['dev.fruit.com'] = 'fruit';
      $sites['editorial.dev.fruit.com'] = 'fruit';
      $sites['dev.apples.fruit.com'] = 'apples';
      $sites['dev.oranges.fruit.com'] = 'oranges';
      break;

    case 'test':
      $sites['test.fruit.com'] = 'fruit';
      $sites['editorial.test.fruit.com'] = 'fruit';
      $sites['test.apples.fruit.com'] = 'apples';
      $sites['test.oranges.fruit.com'] = 'oranges';
      break;

    case 'prod':
      $sites['fruit.com'] = 'fruit';
      $sites['editorial.fruit.com'] = 'fruit';
      $sites['apples.fruit.com'] = 'apples';
      $sites['oranges.fruit.com'] = 'oranges';
      break;
  }
}
```

Please note that Acquia Purge might have difficulty working with the complexer
syntaxes that sites.php supports, like ports and subdirectories:

```
$sites['8080.www.drupal.org.mysite.test'] = 'example.com';
```

It is now time for testing that the right domains are picked up everywhere:

1. On your DEV-environment:
1.1 Run drush ap-diagnosis --uri="http://www.site.com" FOR EACH site!
1.2 Ensure the listed domains make sense for each site.
1.3 Make sure no amber warnings or red errors appear.

2. On your TEST-environment:
2.1 Run drush ap-diagnosis --uri="http://www.site.com" FOR EACH site!
2.2 Ensure the listed domains make sense for each site.
2.3 Make sure no amber warnings or red errors appear.

3. On your PROD-environment:
3.1 Run drush ap-diagnosis --uri="http://www.site.com" FOR EACH site!
3.2 Ensure the listed domains make sense for each site.
3.3 Make sure no amber warnings or red errors appear.

Repeat for any remaining environment(s) you may have.

If the results aren't satisfactory, you want to manually override your cleared
domain name list for one or more sites within your multi-site. Look at the
documentation under "Manual domain name configuration" on how to do that, just
make sure to use the right ``settings.php`` file for the site in question.
