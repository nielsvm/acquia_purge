<?php

namespace Drupal\acquia_purge;

/**
 * Helper class that centralizes string hashing for security and maintenance.
 */
class Hash {

  /**
   * Create unique hashes for a list of cache tag strings.
   *
   * @param string[] $tags
   *   Non-associative array cache tags.
   *
   * @return string[]
   *   Non-associative array with hashed copies of the given cache tags.
   */
  static public function cacheTags(array $tags) {
    $hashes = [];
    foreach ($tags as $tag) {
      $hashes[] = substr(md5($tag), 0, 4);
    }
    return $hashes;
  }

  /**
   * Create a unique hash that identifies this site.
   *
   * @param string $site_name
   *   The identifier of the site on Acquia Cloud.
   * @param string $site_path
   *   The path of the site, e.g. 'site/default' or 'site/database_a'.
   *
   * @return string
   *   Cryptographic hash with a length of 8.
   */
  static public function siteIdentifier($site_name, $site_path) {
    return substr(md5($site_name . $site_path), 0, 8);
  }

}
