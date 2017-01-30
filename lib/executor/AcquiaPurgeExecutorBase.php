<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorBase.
 */

/**
 * Provides an executor, which is responsible for taking a set of invalidation
 * objects and wiping these paths/URLs from an external cache.
 */
abstract class AcquiaPurgeExecutorBase implements AcquiaPurgeExecutorInterface {

  /**
   * The Acquia Purge service object.
   *
   * @var AcquiaPurgeService
   */
  protected $service;

  /**
   * Construct a new AcquiaPurgeExecutorBase instance.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service object.
   */
  public function __construct(AcquiaPurgeService $service) {
    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return get_class($this);
  }

}
