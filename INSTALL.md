[//]: # ( clear&&curl -s -F input_files[]=@INSTALL.md -F from=markdown -F to=html http://c.docverter.com/convert|tail -n+11|head -n-2 )
[//]: # ( curl -s -F input_files[]=@INSTALL.md -F from=markdown -F to=pdf http://c.docverter.com/convert>INSTALL.pdf )

# Installation

Acquia Purge has been designed from the ground up to be extremely simple and
almost failsafe to use. The module provides the _means_ to purge all load
balancers on Acquia Cloud and focuses on doing that well, where the Expire
module remains responsible for telling what needs to be purged. Setting up both
modules shouldn't take long:

1. Ensure you are not running incompatible modules like purge, varnish or boost.

2. Download expire and acquia_purge into your site.
   e.g.: ``drush dl expire acquia_purge``

3. Enable both modules.
   e.g.: ``drush en expire acquia_purge``

4. Assign the ``purge on-screen`` permission to editor roles to give them a
   visually indicative progress bar upon saving content. Purges still happen
   without this permission on the background (triggered via AJAX).

5. It's highly recommended to visit the status report page or to run
   ``drush ap-diagnosis``, in which Acquia Purge will perform diagnostic tests.

Do you have any questions, bugs or comments? Feel free to lookup common
questions in the ``FAQ.md`` file or file a issue on Drupal.org.

### Tuning

By strict design and principle, this module doesn't have any UI exposed settings
or configuration forms. The reason behind this philosophy is that - as a pure -
utility module only site administrators should be able to change anything and if
they do, things should be traceable in ``settings.php``. Although Acquia Purge
attempts to stay as turnkey and zeroconf as possible, the following options
exist as of this version and documented below:

```
╔══════════════════════════╦═══════╦═══════════════════════════════════════════╗
║      $conf setting       ║ Deflt ║               Description                 ║
╠══════════════════════════╬═══════╬═══════════════════════════════════════════╣
║ acquia_purge_domains     ║ FALSE ║ Allows you to control which domains will  ║
║                          ║       ║ get purged, see DOMAINS.md                ║
║                          ║       ║                                           ║
║ acquia_purge_sphpskippath║ TRUE  ║ By default, the sites.php domain detection║
║                          ║       ║ skips records that do not end on a known  ║
║                          ║       ║ TLD, as it assumes the remainder to be a  ║
║                          ║       ║ path. Disabling this, enables experimental║
║                          ║       ║ support for supporting all of sites.php.  ║
║                          ║       ║ $conf['acquia_purge_sphpskippath'] = FALSE║
║                          ║       ║                                           ║
║ acquia_purge_stripports  ║ 80,443║ Ports stripped from records in sites.php  ║
║                          ║       ║ that start with a first octet that is     ║
║                          ║       ║ numeric, e.g.: '443.domain.com'. Ports    ║
║                          ║       ║ outside this setting are not stripped     ║
║                          ║       ║ from detected domains.                    ║
║                          ║       ║ $conf['acquia_purge_stripports'] = [80];  ║
║                          ║       ║                                           ║
║ acquia_purge_cron        ║ FALSE ║ Once enabled, this will process queue     ║
║                          ║       ║ items during cron. The client side AJAX   ║
║                          ║       ║ processor cannot be disabled, but it will ║
║                          ║       ║ run less, especially combined with late   ║
║                          ║       ║ runtime processing enabled.               ║
║                          ║       ║ $conf['acquia_purge_cron'] = TRUE;        ║
║                          ║       ║                                           ║
║ acquia_purge_lateruntime ║ FALSE ║ When enabled, processing of the queue will║
║                          ║       ║ start during the same request items got   ║
║                          ║       ║ added to it. Queues clear quicker and the ║
║                          ║       ║ role of the client-side AJAX processor    ║
║                          ║       ║ reduces drastically. However, this does   ║
║                          ║       ║ add RISK since pages can time/out or run  ║
║                          ║       ║ out of memory! Test carefully!            ║
║                          ║       ║ $conf['acquia_purge_lateruntime'] = TRUE; ║
║                          ║       ║                                           ║
║ acquia_purge_http        ║ TRUE  ║ Purging of http:// schemes, which is      ║
║                          ║       ║ the default behavior. You can disable     ║
║                          ║       ║ it with FALSE, as long as you then do     ║
║                          ║       ║ purge https://. Else the system will      ║
║                          ║       ║ shut itself down and report an error.     ║
║                          ║       ║ $conf['acquia_purge_http'] = FALSE;       ║
║                          ║       ║                                           ║
║ acquia_purge_https       ║ FALSE ║ EXPERIMENTAL https:// scheme support,     ║
║                          ║       ║ disabled by default. Once enabled the     ║
║                          ║       ║ total amount of work done will double,    ║
║                          ║       ║ so monitor your system closely and        ║
║                          ║       ║ consider disabling http:// if your site   ║
║                          ║       ║ is fully https:// based (redirecting).    ║
║                          ║       ║ $conf['acquia_purge_https'] = TRUE;       ║
║                          ║       ║                                           ║
║ acquia_purge_token       ║ FALSE ║ If set, this allows you to set a custom   ║
║                          ║       ║ X-Acquia-Purge header value. This helps   ║
║                          ║       ║ offset DDOS style attacks but requires    ║
║                          ║       ║ balancer level configuration chances for  ║
║                          ║       ║ you need to contact Acquia Support.       ║
║                          ║       ║ $conf['acquia_purge_token'] = 'secret';   ║
║                          ║       ║                                           ║
║ acquia_purge_base_path   ║(auto) ║ In some cases Drupal isn't served on the  ║
║                          ║       ║ same URL as where it's edited, which will ║
║                          ║       ║ cause different paths to be purged than   ║
║                          ║       ║ necessary. By overriding this setting,    ║
║                          ║       ║ Drupal's base_path() will no longer be    ║
║                          ║       ║ used to construct purges. Use only when   ║
║                          ║       ║ you know what you are doing.              ║
║                          ║       ║ $conf['acquia_purge_base_path'] = '/sub/';║
║                          ║       ║                                           ║
║ acquia_purge_errorlimit  ║ TRUE  ║ The system shuts down when it counted too ║
║                          ║       ║ many HTTP errors. When TRUE, this limit is║
║                          ║       ║ calculated with "slowdown factor^3", use  ║
║                          ║       ║ 'drush apd' to see the actual factor. If  ║
║                          ║       ║ you want a static limit instead, this has ║
║                          ║       ║ to be set as integer value, e.g:          ║
║                          ║       ║ $conf['acquia_purge_errorlimit'] = 500;   ║
║                          ║       ║                                           ║
║ acquia_purge_log_success ║ TRUE  ║ By default this module will log both      ║
║                          ║       ║ successes and failure, which is helpful   ║
║                          ║       ║ for those setting the module up. But once ║
║                          ║       ║ implemented and working fine, it can      ║
║                          ║       ║ be heavy on your log files. By setting    ║
║                          ║       ║ this to FALSE, only failure will be put   ║
║                          ║       ║ into your logs and thus reduce queries    ║
║                          ║       ║ or disk writes (for log files).           ║
║                          ║       ║ $conf['acquia_purge_log_success'] = FALSE;║
║                          ║       ║                                           ║
║ acquia_purge_variations  ║ TRUE  ║ If enabled, this aids administrators using║
║                          ║       ║ 'drush ap-purge' or the manual purge form ║
║                          ║       ║ as it will automatically purge common     ║
║                          ║       ║ variations of the paths to be purged. For ║
║                          ║       ║ instance, versions with ?page parameters  ║
║                          ║       ║ and paths with trailing slashes are       ║
║                          ║       ║ made up for every manually purged path but║
║                          ║       ║ this behavior can be disabled with:       ║
║                          ║       ║ $conf['acquia_purge_variations'] = FALSE; ║
║                          ║       ║                                           ║
║ acquia_purge_memcache    ║ TRUE  ║ Determines whether Acquia Purge needs to  ║
║                          ║       ║ store its state data in memory when       ║
║                          ║       ║ $conf['cache_default_class'] is set to use║
║                          ║       ║ it. This reduces I/O activity drastically ║
║                          ║       ║ compared to the fallback file based state ║
║                          ║       ║ storage and also improves deduplication of║
║                          ║       ║ queue items drastically. If you are seeing║
║                          ║       ║ issues with queuing and purging items, you║
║                          ║       ║ can consider disabling it followed by     ║
║                          ║       ║ 'drush ap-forget' to see if that works.   ║
║                          ║       ║ $conf['acquia_purge_memcache'] = FALSE;   ║
║                          ║       ║                                           ║
║ acquia_purge_trim_slashes║ TRUE  ║ When set to FALSE, this will cause the    ║
║                          ║       ║ hook_expire_cache() implementation to stop║
║                          ║       ║ trimming / characters from URLs coming    ║
║                          ║       ║ from the expire module.                   ║
║                          ║       ║ $conf['acquia_purge_trim_slashes'] =FALSE;║
║                          ║       ║                                           ║
║ acquia_purge_passivemode ║ FALSE ║ When set to TRUE, this will cause the     ║
║                          ║       ║ hook_expire_cache() implementation to stop║
║                          ║       ║ working and effectively allows the module ║
║                          ║       ║ to remain enabled in local environments   ║
║                          ║       ║ without actually purging automatically.   ║
║                          ║       ║ $conf['acquia_purge_passivemode'] = TRUE; ║
║                          ║       ║                                           ║
║ acquia_purge_silentmode  ║ FALSE ║ TRUE hides the client-side AJAX processor ║
║                          ║       ║ regardless of what the "purge on-screen"  ║
║                          ║       ║ permission is set to. It is not possible  ║
║                          ║       ║ to disable the processor, but its role is ║
║                          ║       ║ highly reduced in combination with cron   ║
║                          ║       ║ mode and late runtime processing.         ║
║                          ║       ║ $conf['acquia_purge_silentmode'] = TRUE;  ║
║                          ║       ║                                           ║
║ acquia_purge_allriskmode ║ FALSE ║ When set to TRUE, this disables full      ║
║                          ║       ║ blocking checks for too high queue volumes║
║                          ║       ║ and too many domain names. Using this mode║
║                          ║       ║ excludes your support SLA entitlement and ║
║                          ║       ║ rules out support on these checks from the║
║                          ║       ║ Acquia Purge issue queue.                 ║
║                          ║       ║ $conf['acquia_purge_allriskmode'] = TRUE; ║
║                          ║       ║                                           ║
║ acquia_purge_smartqueue  ║ FALSE ║ When set to TRUE, the smart queue backend ║
║                          ║       ║ will be loaded instead. It automatically  ║
║                          ║       ║ disregards items that Varnish has already ║
║                          ║       ║ dropped and this backend can be a big     ║
║                          ║       ║ efficiency improvement on sites with TTLs ║
║                          ║       ║ not set to weeks or months. One BIG FAT   ║
║                          ║       ║ WARNING: if your site dynamically sets the║
║                          ║       ║ page_cache_maximum_age variable or max-age║
║                          ║       ║ Cache-Control header value, using this    ║
║                          ║       ║ backend will make purging VERY UNRELIABLE!║
║                          ║       ║ $conf['acquia_purge_smartqueue'] = TRUE;  ║
║                          ║       ║                                           ║
╚══════════════════════════╩═══════╩═══════════════════════════════════════════╝
```
