<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorsService.
 */

/**
 * Service that loads and provides access to executor backends.
 */
class AcquiaPurgeExecutorsService {

  /**
   * The _acquia_purge_load() services in load and execution order.
   *
   * @var string[]
   */
  protected $backends = array(
    'executor_ah',
    'executor_pagecache',
  );

  /**
   * The loaded backends.
   *
   * @var AcquiaPurgeExecutorInterface
   */
  protected $executors = array();

  /**
   * The Acquia Purge service object.
   *
   * @var AcquiaPurgeService
   */
  protected $service;

  /**
   * Construct a new AcquiaPurgeExecutorsService instance.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service object.
   */
  public function __construct(AcquiaPurgeService $service) {
    $this->service = $service;

    // Make sure the base class is available in case it gets referenced.
    _acquia_purge_load('executor_base');

    // Initialize the executors that advertize themselves as enabled.
    foreach ($this->backends as $service) {
      $class = _acquia_purge_load($service);
      if ($class::isEnabled()) {
        $this->executors[$class] = new $class($this->service);
      }
    }
  }

}
