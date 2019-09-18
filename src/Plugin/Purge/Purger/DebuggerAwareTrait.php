<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

/**
 * Provides a Acquia purger which is debugging aware.
 */
trait DebuggerAwareTrait {

  /**
   * The debugger instance.
   *
   * @var \Drupal\acquia_purge\Plugin\Purge\Purger\DebuggerInterface
   */
  private $debuggerInstance;

  /**
   * {@inheritdoc}
   */
  public function debugger() {
    if (is_null($this->debuggerInstance)) {
      $this->debuggerInstance = new Debugger($this->logger());
    }
    return $this->debuggerInstance;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugger(DebuggerInterface $debugger, $throw = TRUE) {
    if ($throw && (!is_null($this->debuggerInstance))) {
      throw new \RuntimeException("Debugger already instantiated!");
    }
    $this->debuggerInstance = $debugger;
  }

}
