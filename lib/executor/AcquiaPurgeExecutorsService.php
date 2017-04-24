<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorsService.
 */

/**
 * Service that loads and provides access to executor backends.
 */
class AcquiaPurgeExecutorsService implements \Iterator {

  /**
   * Core executor backends that always run regardless what.
   *
   * @var string[]
   */
  protected $core_backends = array(
    '_acquia_purge_executor_page_cache',
    '_acquia_purge_executor_ah',
  );

  /**
   * The loaded backends.
   *
   * @var AcquiaPurgeExecutorInterface
   */
  protected $executors = array();

  /**
   * Current iterator position.
   *
   * @var int
   */
  protected $position = 0;

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
    foreach ($this->getRegisteredBackends() as $service_or_path) {
      $class = _acquia_purge_load($service_or_path);
      if ($class::isEnabled($this->service)) {
        $instance = new $class($this->service);
        if (!($instance instanceof AcquiaPurgeExecutorInterface)) {
          throw new \RuntimeException("$class != AcquiaPurgeExecutorInterface");
        }
        $this->executors[] = $instance;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function current() {
    if ($this->valid()) {
      return $this->executors[$this->position];
    }
    return FALSE;
  }

  /**
   * Retrieve all loadable backends.
   *
   * @return string[]
   *   List of paths or _acquia_purge_load() service names.
   */
  protected function getRegisteredBackends() {
    $external_backends = array();
    foreach (module_implements('acquia_purge_executors') as $module) {
      $function = $module . '_acquia_purge_executors';
      $function($external_backends);
    }
    return array_merge($this->core_backends, $external_backends);
  }

  /**
   * {@inheritdoc}
   */
  public function key() {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    ++$this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->position = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return isset($this->executors[$this->position]);
  }

}
