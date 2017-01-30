<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorAh.
 */

/**
 * Executor that clears URLs across all Acquia Cloud load balancers.
 */
class AcquiaPurgeExecutorAh extends AcquiaPurgeExecutorBase implements AcquiaPurgeExecutorInterface {

  /**
   * {@inheritdoc}
   */
  public static function isEnabled() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($invalidations) {
    foreach ($invalidations as $invalidation) {
      // $invalidation->setStatusFailed();
    }
  }

}
