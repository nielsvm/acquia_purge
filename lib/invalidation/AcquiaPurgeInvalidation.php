<?php

/**
 * Provides an invalidation object.
 *
 * Invalidations are small value objects that describe an individual path from
 * the Acquia Purge queue, to be invalidated for the given scheme and domain
 * name. Executors are responsible for calling ::setState(), so that at the end
 * of processing, ::getState() evaluates whether the invalidation succeeded
 * across all executor engines. This means that a failed CloudEdge purge, would
 * render the entire path as failed so that it goes back into the queue.
 */
class AcquiaPurgeInvalidation implements AcquiaPurgeInvalidationInterface {

  /**
   * The instance ID of the executor that is about to process this object, or
   * NULL when no longer any executors are processing it. NULL is the default.
   *
   * @var string|null
   */
  private $context = NULL;

  /**
   * The domain name to clear the path on, e.g. "foo.com" or "bar.baz".
   *
   * @var string
   */
  private $domain;

  /**
   * The path to wipe, e.g. 'user/1?destination=foo' or 'news/*'.
   *
   * @var string
   */
  private $path;

  /**
   * The requested scheme to be invalidated: 'http' or 'https'.
   *
   * @var string
   */
  private $scheme;

  /**
   * Associative list in which each key is the $executor_instance_id and the
   * value can be SELF::SUCCEEDED or SELF::FAILED.
   *
   * @var int[]
   */
  private $states = array();

  /**
   * States in which invalidation objects can leave processing. Notice that
   * SELF::FRESH is missing, this protects us against badly written executors.
   *
   * @var int[]
   */
  private $states_after_processing = array(
    SELF::SUCCEEDED,
    SELF::FAILED,
  );

  /**
   * Queue item object as defined in AcquiaPurgeQueueInterface::claimItem(),
   * with at least the following properties:
   *   - data: the same as what what passed into createItem().
   *   - item_id: the unique ID returned from createItem().
   *   - created: timestamp when the item was put into the queue.
   *
   * @var object
   */
  private $queue_item;

  /**
   * {@inheritdoc}
   */
  public function __construct($scheme, $domain, $base_path, $queue_item) {
    $this->scheme = $scheme;
    $this->domain = $domain;
    $this->path = str_replace('//', '/', $base_path . $queue_item->data[0]);
    $this->queue_item = $queue_item;
  }

  /**
   * {@inheritdoc}
   */
  public function getScheme($fqn = FALSE) {
    if ($fqn) {
      return $this->scheme . '://';
    }
    return $this->scheme;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomain() {
    return $this->domain;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueueItem() {
    return $this->queue_item;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri() {
    return $this->getScheme(TRUE) . $this->getDomain() . $this->getPath();
  }

  /**
   * {@inheritdoc}
   */
  public function getState() {
    if (empty($this->states)) {
      return SELF::FRESH;
    }

    // General context lets us evaluate the "global" invalidation state. Simply
    // put, if one executor FAILED, the entire thing did.
    if ($this->context === NULL) {
      if (in_array(SELF::FAILED, $this->states)) {
        return SELF::FAILED;
      }
      return SELF::SUCCEEDED;
    }

    // Within executor context, return the state belonging to it.
    else {
      if (isset($this->states[$this->context])) {
        return $this->states[$this->context];
      }
      return SELF::FRESH;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStateString() {
    $mapping = array(
      SELF::FRESH         => 'FRESH',
      SELF::SUCCEEDED     => 'SUCCEEDED',
      SELF::FAILED        => 'FAILED',
    );
    return $mapping[$this->getState()];
  }

  /**
   * {@inheritdoc}
   */
  public function hasWildcard() {
    return strpos($this->path, '*') !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setState($state) {
    if (is_null($this->context)) {
      throw new \LogicException('State cannot be set in NULL context!');
    }
    if (!is_int($state)) {
      throw new RuntimeException('State $state not an integer!');
    }
    if (!in_array($state, $this->states_after_processing)) {
      throw new RuntimeException('State is not FAILED or SUCCEEDED!');
    }
    $this->states[$this->context] = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function setStateContext($executor_instance_id) {
    $new_is_string = is_string($executor_instance_id);
    $new_is_null = is_null($executor_instance_id);
    if ($new_is_string && (!strlen($executor_instance_id))) {
      throw new \LogicException('Parameter $executor_instance_id is empty!');
    }
    elseif ((!$new_is_string) && (!$new_is_null)) {
      throw new \LogicException('Parameter $executor_instance_id is not NULL or a non-empty string!');
    }
    elseif ($executor_instance_id === $this->context) {
      return;
    }

    // Find out if states returning from executors are actually valid.
    $old_is_string = is_string($this->context);
    $both_strings = $old_is_string && $new_is_string;
    $transferring = $both_strings && ($this->context != $executor_instance_id);
    if ($transferring || ($old_is_string && $new_is_null)) {
      if (!in_array($this->getState(), $this->states_after_processing)) {
        throw new LogicException("Only SUCCEEDED and FAILED are valid outbound states.");
      }
    }

    $this->context = $executor_instance_id;
  }

}
