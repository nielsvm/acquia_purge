<?php

/**
 * Provides methods capable of letting multiple actors set a SUCCEEDED or
 * FAILED status, which can then be evaluated to a single overall outcome.
 */
Trait AcquiaPurgeQueueStatusTrait {

  /**
   * The instance ID of the executor that is about to process this object, or
   * NULL when no longer any executors are processing it. NULL is the default.
   *
   * @var string|null
   */
  protected $context = NULL;

  /**
   * Associative list in which each key is the context id and the value can be:
   *   - AcquiaPurgeQueueStatusInterface::FAILED
   *   - AcquiaPurgeQueueStatusInterface::SUCCEEDED
   *
   * @var int[]
   */
  protected $statuses = array();

  /**
   * Statuses in which queue objects can leave processing. Notice that
   * AcquiaPurgeQueueStatusInterface::FRESH is missing, this protects us against
   * badly written executors.
   *
   * @var int[]
   */
  protected $statuses_after_processing = array(
    SELF::SUCCEEDED,
    SELF::FAILED,
  );

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    if (empty($this->statuses)) {
      return SELF::FRESH;
    }
    if ($this->context === NULL) {
      if (in_array(SELF::FAILED, $this->statuses)) {
        return SELF::FAILED;
      }
      return SELF::SUCCEEDED;
    }
    else {
      if (isset($this->statuses[$this->context])) {
        return $this->statuses[$this->context];
      }
      return SELF::FRESH;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusBoolean() {
    $mapping = array(
      SELF::FRESH         => NULL,
      SELF::SUCCEEDED     => TRUE,
      SELF::FAILED        => FALSE,
    );
    return $mapping[$this->getStatus()];
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusString() {
    $mapping = array(
      SELF::FRESH         => 'FRESH',
      SELF::SUCCEEDED     => 'SUCCEEDED',
      SELF::FAILED        => 'FAILED',
    );
    return $mapping[$this->getStatus()];
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    if (is_null($this->context)) {
      throw new \LogicException('Status cannot be set in NULL context!');
    }
    if (!is_int($status)) {
      throw new RuntimeException('Status $status not an integer!');
    }
    if (!in_array($status, $this->statuses_after_processing)) {
      throw new RuntimeException('Status is not FAILED or SUCCEEDED!');
    }
    $this->statuses[$this->context] = $status;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusContext($id) {
    $new_is_string = is_string($id);
    $new_is_null = is_null($id);
    if ($new_is_string && (!strlen($id))) {
      throw new \LogicException('Parameter $id is empty!');
    }
    elseif ((!$new_is_string) && (!$new_is_null)) {
      throw new \LogicException('Parameter $id is not NULL or a non-empty string!');
    }
    elseif ($id === $this->context) {
      return;
    }

    // Find out if statuses returning from executors are actually valid.
    $old_is_string = is_string($this->context);
    $both_strings = $old_is_string && $new_is_string;
    $transferring = $both_strings && ($this->context != $id);
    if ($transferring || ($old_is_string && $new_is_null)) {
      if (!in_array($this->getStatus(), $this->statuses_after_processing)) {
        throw new LogicException("Executor didn't call ::setStatusFailed() or ::setStatusSucceeded()!");
      }
    }

    $this->context = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusFailed() {
    $this->setStatus(SELF::FAILED);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatusSucceeded() {
    $this->setStatus(SELF::SUCCEEDED);
  }

}
