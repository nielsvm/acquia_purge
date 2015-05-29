<?php

/**
 * @file
 * Contains ApCronProcessor.
 */

/**
 * Processes the queue during hook_cron().
 */
class ApCronProcessor extends ApProcessorBase implements ApProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public static function isEnabled() {

    // Don't load ApCronProcessor when ApRuntimeProcessor is enabled as well,
    // since this can lead to double processing during cron. Although running
    // ApQueueService::process twice during the same request won't harm because
    // of the built-in capacity calculation, it would mean that the second run
    // won't purge anything as the former already did #2292773.
    if (_acquia_purge_variable('acquia_purge_lateruntime')) {
      return FALSE;
    }

    return (bool) _acquia_purge_variable('acquia_purge_cron');
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribedEvents() {
    return array('onCron');
  }

  /**
   * Implements event onCron.
   *
   * @see acquia_purge_cron()
   */
  public function onCron() {
    $this->processQueueChunk();
  }

}
