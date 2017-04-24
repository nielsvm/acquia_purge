<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorPageCache.
 */

/**
 * Executor that pre-clears URLs from Drupal's page cache.
 */
class AcquiaPurgeExecutorPageCache extends AcquiaPurgeExecutorBase implements AcquiaPurgeExecutorInterface {

  /**
   * {@inheritdoc}
   */
  public static function isEnabled(AcquiaPurgeService $service) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($invalidations) {
    foreach ($invalidations as $invalidation) {
      cache_clear_all($invalidation->getUri(), 'cache_page');
      $invalidation->setStatusSucceeded();
    }
  }

}
