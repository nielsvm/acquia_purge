<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use Drupal\acquia_purge\Plugin\Purge\Purger\DebuggerInterface;
use Drupal\acquia_purge\Plugin\Purge\Purger\Debugger;

/**
 * Provides a Acquia purger which is debugging aware.
 */
trait DebuggerAwareTrait {

  /**
   * @var \Drupal\acquia_purge\Plugin\Purge\Purger\DebuggerInterface
   */
  private $debugger_instance;

  /**
   * {@inheritdoc}
   */
  public function debugger() {
    if (is_null($this->debugger_instance)) {
      $this->debugger_instance = new Debugger($this->logger());
    }
    return $this->debugger_instance;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugger(DebuggerInterface $debugger, $throw = TRUE) {
    if ($throw && (!is_null($this->debugger_instance))) {
      throw new \RuntimeException("Debugger already instantiated!");
    }
    $this->debugger_instance = $debugger;
  }

}
