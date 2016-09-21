<?php

/**
 * @file
 * Contains AcquiaPurgeHostingInfo.
 */

/**
 * Provides technical information accessors for the Acquia Cloud environment.
 */
class AcquiaPurgeHostingInfo {

  /**
   * The load balancer IP adresses installed in front of this site.
   *
   * @var string[]
   */
  protected $balancerAddresses = array();

  /**
   * The token used to authenticate cache invalidations with
   *
   * @var string
   */
  protected $balancerToken = '';

  /**
   * The list of defined domains that get purged.
   *
   * @var string[]
   */
  protected $domains = array();

  /**
   * The list of protocol schemes to be purged.
   *
   * @var string[]
   */
  protected $schemes = [];

  /**
   * The Acquia site environment.
   *
   * @var string
   */
  protected $siteEnvironment = '';

  /**
   * The Acquia site group.
   *
   * @var bool|string
   */
  protected $siteGroup = FALSE;

  /**
   * The Acquia site name.
   *
   * @var bool|string
   */
  protected $siteName = FALSE;

  /**
  * Whether the current hosting environment is Acquia Cloud or not.
  *
  * @var bool
  */
  protected $isThisAcquiaCloud = FALSE;

  /**
   * Constructs a AcquiaPurgeHostingInfo object.
   */
  public function __construct() {

    // Fetch the sitename and sitegroup from the environment.
    if (isset($_ENV['AH_SITE_ENVIRONMENT']) && !empty($_ENV['AH_SITE_ENVIRONMENT'])) {
      $this->siteEnvironment = $_ENV['AH_SITE_ENVIRONMENT'];
    }
    if (isset($_ENV['AH_SITE_NAME']) && !empty($_ENV['AH_SITE_NAME'])) {
      $this->siteName = $_ENV['AH_SITE_NAME'];
    }
    if (isset($_ENV['AH_SITE_GROUP']) && !empty($_ENV['AH_SITE_GROUP'])) {
      $this->siteGroup = $_ENV['AH_SITE_GROUP'];
    }

    // Determine the balancer token and IP addresses.
    if (is_array($reverse_proxies = variable_get('reverse_proxies'))) {
      foreach ($reverse_proxies as $reverse_proxy) {
        if ($reverse_proxy && strpos($reverse_proxy, '.')) {
          $this->balancerAddresses[] = $reverse_proxy;
        }
      }
    }
    $this->balancerToken = $this->siteName;
    $this->balancerToken = '';
    if ($token_configured = _acquia_purge_variable('acquia_purge_token')) {
      $this->balancerToken = (string) trim($token_configured);
    }

    // Determine what the protocol schemes are based on settings.
    if (_acquia_purge_variable('acquia_purge_http') === TRUE) {
      $this->schemes[] = 'http';
    }
    if (_acquia_purge_variable('acquia_purge_https') === TRUE) {
      $this->schemes[] = 'https';
    }

    // Test the gathered information to determine if this is/isn't Acquia Cloud.
    $this->isThisAcquiaCloud =
      count($this->balancerAddresses)
      && $this->balancerToken
      && $this->siteEnvironment
      && $this->siteName
      && $this->siteGroup
      && function_exists('curl_init');

    // Gather the domains to be purged.
    $this->domains();
  }

  /**
   * Initialize $this->domains.
   */
  protected function domains() {

    // Avoid automatic detection when 'acquia_purge_domains' contains hardcodes.
    $hardcodes = _acquia_purge_variable('acquia_purge_domains');
    if (is_array($hardcodes) && count($hardcodes)) {
      foreach ($hardcodes as $hardcoded_domain) {
        $this->domains[] = $hardcoded_domain;
      }
    }

    // Automatically source the domains from various locations.
    else {
      $this->domainsAddFromDrupal();
      $this->domainsAddFromSitesPhp();

      // Source domains from the Acquia Cloud platform. However, only source
      // them for the 'default' site so that multisite configs don't blow up.
      if ($this->isThisAcquiaCloud() && (conf_path() == 'sites/default')) {
        $this->domainsAddFromAcquiaCloud();
      }
    }

    // Allow alteration of the domains list and assure that our list is clean.
    $this->domainsAlter();
    $this->domainsNormalize();
  }

  /**
   * Fire off hook_acquia_purge_domains_alter().
   */
  protected function domainsAlter() {
    foreach (module_implements('acquia_purge_domains_alter') as $module) {
      $function = $module . '_acquia_purge_domains_alter';
      $function($this->domains);
    }
  }

  /**
   * Normalize $this->domains, so that it contains no:
   *  - uppercase strings.
   *  - empty strings
   *  - asterisks
   *  - duplicates
   *  - leading or traling spaces
   */
  protected function domainsNormalize() {
    if (!function_exists('drupal_strtolower')) {
      include_once DRUPAL_ROOT . '/includes/unicode.inc';
    }
    $domains = array();
    foreach ($this->domains as $domain) {
      $domain = trim(drupal_strtolower($domain));
      $domain = str_replace('.*.', '', $domain);
      $domain = str_replace('*.', '', $domain);
      $domain = str_replace('*', '', $domain);
      if (!empty($domain) && !in_array($domain, $domains)) {
        $domains[] = $domain;
      }
    }
    $this->domains = $domains;
  }

  /**
   * Extract domain names from HTTP_HOST and Drupal's url() function.
   */
  protected function domainsAddFromDrupal() {
    $this->domains[] = $_SERVER['HTTP_HOST'];
    if ($parsed_url = parse_url(url('', array('absolute' => TRUE)))) {
      $this->domains[] = $parsed_url['host'];
    }
  }

  /**
   * Source domains from sites/sites.php by reverse parsing it.
   *
   * @warning
   *   The way the sites/sites.php array was designed was to make it a
   *   lookup map with the current active URI as lookup resource, it makes
   *   that relatively easy to do. However, we want to get all domains that
   *   point to the currently chosen site. As the array keys are in the
   *   format of '<port>.<domain>.<path>' this is relatively hackish.
   */
  protected function domainsAddFromSitesPhp() {
    $sitesphpskippath = _acquia_purge_variable('acquia_purge_sphpskippath');
    $stripports = _acquia_purge_variable('acquia_purge_stripports');
    $sitedir = str_replace('sites/', '', conf_path());
    $tlds = array();

    // Only interpret the $sites array if the file sites/sites.php exists.
    if (!file_exists('sites/sites.php')) {
      return;
    }

    // Define the full list of TLD's we have to check against to determine if a
    // embedded domain name in '<port>.<domain>.<path>' seems valid for us.
    include drupal_get_path('module', 'acquia_purge') . '/acquia_purge.tlds.inc';

    // Include the file which will (re)propagate the $sites array for us.
    $sites = array();
    include 'sites/sites.php';

    // Protect ourselves against badly written code inside sites.php.
    if ((!isset($sites)) || empty($sites)) {
      return;
    }

    // Iterate and validate each record in the resulting $sites array.
    foreach ($sites as $site => $directory) {

      // Skip those that point to a different site directory then we are on.
      if ($directory != $sitedir) {
        continue;
      }

      // Split $site that can be defined in the form of '<port>.<domain>.<path>'.
      $site = explode('.', $site);

      // Strip TCP port's in '<port>....'.
      if (is_numeric($site[0]) && (in_array((int) $site[0], $stripports))) {
        unset($site[0]);
      }

      // Every record in sites.php that has a TLD in the middle of it, are assumed
      // to be in the '<domain>.<path>' format, which means we have a path we
      // really need to get rid of.  When the last octet isn't in $tlds, we
      // assume this scenario to be a reality and start scanning each octet. If
      // on of the middle octets then matches a valid TLD, we rewrite the domain.
      if (!in_array(end($site), $tlds)) {
        if ($sitesphpskippath) {
          continue;
        }
        else {
          $replacement = array();
          foreach ($site as $octet) {
            $replacement[] = $octet;
            if (in_array($octet, $tlds) && ($octet !== end($site))) {
              $site = $replacement;
              break;
            }
          }
        }
      }

      // What's left should be a 99.99% correct domain name we want to see purged.
      $this->domains[] = implode('.', $site);
    }
  }

  /**
   * Source the domain names configured on Acquia Cloud.
   */
  protected function domainsAddFromAcquiaCloud() {
    $detected_domains = array();

    // Source the domain names from config.json.
    $config_json = '/var/www/site-php' . $this->siteName . '/config.json';
    if (file_exists($config_json) && is_readable($config_json)) {
      $config = json_decode(file_get_contents($config_json, TRUE));
      if (is_array($config) && count($config)) {
        foreach ($config as $detected_domain) {
          $detected_domains[] = $detected_domain;
        }
      }
    }

    // If config.json wasn't readable, fall back to the old implementation.
    elseif (file_exists('/etc/apache2/conf.d')) {
      $server_name = shell_exec("grep -r 'ServerName' /etc/apache2/conf.d/" . $this->siteName . "-*.conf");
      foreach (explode('ServerName', $server_name) as $testable) {
        foreach (explode(' ', trim($testable)) as $domain) {
          $detected_domains[] = $domain;
        }
      }
      $server_alias = shell_exec("grep -r 'ServerAlias' /etc/apache2/conf.d/" . $this->siteName . "-*.conf");
      foreach (explode('ServerAlias', $server_alias) as $testable) {
        foreach (explode(' ', trim($testable)) as $domain) {
          $detected_domains[] = $domain;
        }
      }
    }

    // Remove the amazonaws.com domain as we don't want that one purged.
    if (count($detected_domains)) {
      foreach ($detected_domains as $i => $detected_domain) {
        if (strpos($detected_domain, 'amazonaws.com') !== FALSE) {
          unset($detected_domains[$i]);
        }
      }
    }

    // Remove the acquia-sites.com domain if we have found more then 1 domain. The
    // less domains found, the faster the end user experience will be.
    if (count($detected_domains) > 1) {
      foreach ($detected_domains as $i => $detected_domain) {
        if (strpos($detected_domain, 'acquia-sites.com') !== FALSE) {
          unset($detected_domains[$i]);
        }
      }
    }

    // Register all detected domain names.
    foreach ($detected_domains as $i => $detected_domain) {
      $this->domains[] = $detected_domain;
    }
  }

  /**
   * Get the load balancer IP adresses installed in front of this site.
   *
   * @return string[]
   *   Unassociative list of adresses in the form of 'I.P.V.4', or empty array.
   */
  public function getBalancerAddresses() {
    return $this->balancerAddresses;
  }

  /**
   * Get the token used in the X-Acquia-Purge header.
   *
   * @return string[]
   *   Token string, e.g. 'oursecret' or 'sitedev'.
   */
  public function getBalancerToken() {
    return $this->balancerToken;
  }

  /**
   * Get a list of defined domains that we can purge for.
   *
   * @return string[]
   *   String values for each DNS domain name being purged.
   */
  public function getDomains() {
    return $this->domains;
  }

  /**
   * Get a list of protocol schemes that will be purged.
   *
   * @return string[]
   *   Array with scheme strings like 'http' and 'https'.
   */
  public function getSchemes() {
    return $this->schemes;
  }

  /**
   * Get the Acquia site environment.
   *
   * @return string
   *   The site environment, e.g. 'dev'.
   */
  public function getSiteEnvironment() {
    return $this->siteEnvironment;
  }

  /**
   * Get the Acquia site group.
   *
   * @return false|string
   *   The site group, e.g. 'site' or '' when unavailable.
   */
  public function getSiteGroup() {
    return $this->siteGroup;
  }

  /**
   * Get the Acquia site name.
   *
   * @return false|string
   *   The site group, e.g. 'sitedev' or '' when unavailable.
   */
  public function getSiteName() {
    return $this->siteName;
  }

  /**
   * Determine whether the current hosting environment is Acquia Cloud or not.
   *
   * @return true|false
   *   Boolean expression where 'true' indicates Acquia Cloud or 'false'.
   */
  public function isThisAcquiaCloud() {
    return $this->isThisAcquiaCloud;
  }

}
