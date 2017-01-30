<?php

/**
 * Describes a single HTTP request.
 */
interface AcquiaPurgeExecutorRequestInterface {

  /**
   * Constructs a HTTP request object.
   *
   * @param string $uri
   *   The URL to connect to, e.g.: http://domain.com/path/a/b/c
   */
  public function __construct($uri);

}
