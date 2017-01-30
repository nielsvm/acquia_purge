<?php

/**
 * Provides a single HTTP request.
 */
class AcquiaPurgeExecutorRequest implements AcquiaPurgeExecutorRequestInterface {

  /**
   * The cURL execution resource.
   *
   * @var null|resource
   */
  public $curl = NULL;

  /**
   * cURL error code.
   *
   * @var int
   */
  public $error_curl = '';

  /**
   * String useful for debugging or logging the request.
   *
   * @var string
   */
  public $error_debug = '';

  /**
   * Unassociative array with headers keys and values ("key: value").
   *
   * @var string[]
   */
  public $headers = array();

  /**
   * The HTTP request method, e.g.: 'GET', 'POST', 'PURGE' or 'BAN'.
   *
   * @var string
   */
  public $method = 'GET';

  /**
   * The HTTP response code.
   *
   * @var string
   */
  public $response_code = '';

  /**
   * The outcome of the HTTP request.
   *
   * @var bool
   */
  public $result = NULL;

  /**
   * The scheme to perform against, without '://': 'http' or 'https'.
   *
   * @var string
   */
  public $scheme = 'http';

  /**
   * The URL to connect to, e.g.: http://domain.com/path/a/b/c
   *
   * @var string
   */
  public $uri = '';

  /**
   * {@inheritdoc}
   */
  public function __construct($method = 'GET') {
    $this->method = $method;
  }

}
