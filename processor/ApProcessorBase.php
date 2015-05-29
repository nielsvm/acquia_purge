<?php

/**
 * @file
 * Contains ApProcessorBase.
 */

/**
 * Base class for processors that process items from the queue.
 */
abstract class ApProcessorBase implements ApProcessorInterface {

  /**
   * The queue service object.
   *
   * @var ApQueueService
   */
  protected $qs;

  /**
   * Construct a new ApProcessorBase instance.
   *
   * @param ApQueueService $qs
   *   The queue service object.
   */
  public function __construct($qs) {
    $this->qs = $qs;
  }

  /**
   * Attempt to process a chunk from the queue.
   *
   * @param bool $log
   *   (optional) Whether diagnostic failure should be logged or not.
   */
  protected function processQueueChunk($log = TRUE) {

    // Test if the diagnostic tests prohibit purging the queue.
    if (!_acquia_purge_are_we_allowed_to_purge()) {
      if ($log) {
        $err = _acquia_purge_get_diagnosis(ACQUIA_PURGE_SEVLEVEL_ERROR);
        _acquia_purge_get_diagnosis_logged($err);
      }
      return;
    }

    // Acquire a lock and process a chunk from the queue.
    if ($this->qs->lockAcquire()) {
      $this->qs->process();
      $this->qs->lockRelease();
    }
  }

}
