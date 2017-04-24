<?php

/**
 * @file
 * Contains AcquiaPurgeOddities.
 */

/**
 * Tracks suspicious behavior through string flags.
 */
class AcquiaPurgeOddities {

  /**
   * List of string flags that point to detected runtime specific conditions.
   *
   * @var string[]
   */
  protected $oddities = array();

  /**
   * Name of the Drupal variable used to store the flags in.
   *
   * @var string
   */
  protected $variable = 'acquia_purge_oddities';

  /**
   * Constructs a AcquiaPurgeOddities object.
   */
  public function __construct() {
    $this->oddities = variable_get($this->variable, array());
  }

  /**
   * Add an odd behavior flag.
   *
   * @param string $oddity
   *   Short and simple string describing the behavior, e.g. '403' or 'geoip'.
   */
  public function add($oddity) {
    $oddity = (string) $oddity;
    if (!in_array($oddity, $this->oddities)) {
      $this->oddities[] = $oddity;
      variable_set($this->variable, $this->oddities);
    }
  }

  /**
   * Check if the given odd behavior flag has been reported.
   *
   * @param string $oddity
   *   Short and simple string describing the behavior, e.g. '403' or 'geoip'.
   *
   * @return boolean
   *   TRUE when found, FALSE otherwise.
   */
  public function has($oddity) {
    return in_array($oddity, $this->oddities);
  }

  /**
   * Wipe all oddities.
   */
  public function wipe() {
    variable_del($this->variable);
  }

}
