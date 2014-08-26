<?php

/**
 * @file
 * Hooks provided by the Acquia Purge module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of domains Acquia Purge operates on.
 *
 * Modules may implement this hook to influence the domain names Acquia Purge
 * is purging and have more narrow control over it. Although it is generally
 * discouraged to implement this, it does make in complexer scenarios with many
 * domains that need contextual reduction.
 *
 * Adding domains MUST always happen through _acquia_purge_get_domains_add()
 * as this guards domain normalization and de-duplication, and removing domains
 * is as simple as calling unset() on array items. Hook implementations get
 * called both when $conf['acquia_purge_domains'] has been set and when it has
 * not been set, its up to you to be aware of the data you are operating on.
 *
 * @param $domains
 *   The entity info array, keyed by entity name.
 *
 * @see _acquia_purge_get_domains()
 * @see _acquia_purge_get_domains_add()
 * @see _acquia_purge_get_diagnosis_domains()
 */
function hook_acquia_purge_domains_alter(&$domains) {
  $blacklist = array('domain_a', 'domain_b');
  foreach ($domains as $i => $domain) {
    if (in_array($domain, $blacklist)) {
      unset($domains[$i]);
    }
  }

  _acquia_purge_get_domains_add('my_domain', $domains);
}

/**
 * @} End of "addtogroup hooks".
 */
