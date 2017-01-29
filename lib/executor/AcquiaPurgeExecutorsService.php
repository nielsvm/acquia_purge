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
    '_acquia_purge_executor_ah',
    '_acquia_purge_executor_page_cache',
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
    _acquia_purge_load('_acquia_purge_executor_interface');
    _acquia_purge_load('_acquia_purge_executor_base');

    // Initialize the executors that advertize themselves as enabled.
    foreach ($this->backends as $service) {
      $class = _acquia_purge_load($service);
      if ($class::isEnabled()) {
        $this->executors[$class] = new $class($this->service);
      }
    }
  }

}
