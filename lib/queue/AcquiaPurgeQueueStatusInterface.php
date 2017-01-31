<?php

/**
 * Describes objects capable of letting multiple actors set a SUCCEEDED or
 * FAILED status, which can then be evaluated to a single overall outcome.
 */
interface AcquiaPurgeQueueStatusInterface {

  /**
   * Item is new and no processing has been attempted on it yet.
   *
   * @var int
   */
  const FRESH = 0;

  /**
   * The actor succeeded processing the item.
   *
   * @var int
   */
  const SUCCEEDED = 1;

  /**
   * The actor failed processing the item.
   *
   * @var int
   */
  const FAILED = 2;

  /**
   * Get the current or general status of this object.
   *
   * New, freshly claimed queue items area always in the NULL context. This
   * context is normal when the queue item doesn't yet float from
   * executor to executor, and is called the "general context". calling
   * ::getStatus() will then evaluate the "global" status for the object.
   *
   * However, the behaviors of ::getStatus() and ::setStatus() change after a
   * call to ::setStatusContext(). From this point on, both will respectively
   * retrieve and store the status *specific* to that executor context. Context
   * switching is done by AcquiaPurgeService::process() and therefore simple
   * executor implementations don't require thorough understanding of this
   * concept.
   *
   * @return int
   *   - AcquiaPurgeQueueStatusInterface::FRESH
   *   - AcquiaPurgeQueueStatusInterface::FAILED
   *   - AcquiaPurgeQueueStatusInterface::SUCCEEDED
   */
  public function getStatus();

  /**
   * Get a boolean representation of the general status of this object.
   *
   * @return bool|null
   *   - AcquiaPurgeQueueStatusInterface::FRESH -> NULL
   *   - AcquiaPurgeQueueStatusInterface::FAILED -> FALSE
   *   - AcquiaPurgeQueueStatusInterface::SUCCEEDED -> TRUE
   */
  public function getStatusBoolean();

  /**
   * Get the current status as string.
   *
   * @return string
   *   A capitalized string, either "FRESH", "FAILED" or "SUCCEEDED".
   */
  public function getStatusString();

  /**
   * Set the status of the object.
   *
   * Setting status on queue items is the responsibility of executors, as only
   * executors decide what succeeded and what failed. For this reason a call
   * to ::setStatusContext() before ::setStatus() is required and done by
   * AcquiaPurgeService::process().
   *
   * @param int $status
   *   - AcquiaPurgeQueueStatusInterface::FAILED
   *   - AcquiaPurgeQueueStatusInterface::SUCCEEDED
   *
   * @throws \RuntimeException
   *   Thrown when the $status parameter doesn't match any of the constants
   *   defined in AcquiaPurgeQueueStatusInterface.
   * @throws \LogicException
   *   Thrown when trying to set the status before ::setStatusContext() has been
   *   called to set the executor context.
   */
  public function setStatus($status);

  /**
   * Set (or reset) status context to the executor instance next in line.
   *
   * @param string|null $id
   *   Unique string based on the processing executor processing the object.
   *
   * @throws \LogicException
   *   Thrown when the given parameter is empty, not a string or NULL.
   * @throws \LogicException
   *   Thrown when the last set status was not any of:
   *   - AcquiaPurgeInvalidationInterface::SUCCEEDED
   *   - AcquiaPurgeInvalidationInterface::FAILED
   *
   * @see AcquiaPurgeQueueStatusInterface::setStatus()
   */
  public function setStatusContext($id);

  /**
   * Set the status to AcquiaPurgeQueueStatusInterface::FAILED.
   *
   * @throws \LogicException
   *   Thrown when the status is being set in general context.
   */
  public function setStatusFailed();

  /**
   * Set the status to AcquiaPurgeQueueStatusInterface::SUCCEEDED.
   *
   * @throws \LogicException
   *   Thrown when the status is being set in general context.
   */
  public function setStatusSucceeded();

}
