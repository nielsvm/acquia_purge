<?php

namespace Drupal\acquia_purge\Plugin\Purge\Purger;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Drupal\purge\Logger\LoggerChannelPartInterface;
use Drupal\purge\Logger\PurgeLoggerAwareInterface;

/**
 * Describes a centralized debugger for Acquia purger plugins.
 */
interface DebuggerInterface extends PurgeLoggerAwareInterface {

  /**
   * Construct a debugger.
   *
   * @param \Drupal\purge\Logger\LoggerChannelPartInterface $logger,
   *   The logger passed to the Platform CDN purger.
   */
  public function __construct(LoggerChannelPartInterface $logger);

  /**
   * Register the current caller to the callgraph.
   *
   * @param string|object $caller
   *   Fully namespaced class string or instantiated object.
   */
  public function callerAdd($caller);

  /**
   * Remove the current caller from the callgraph.
   *
   * @param string|object $caller
   *   Fully namespaced class string or instantiated object.
   */
  public function callerRemove($caller);

  /**
   * Check whether the debugger is enabled or not.
   *
   * The debugger is enabled when the passed logger channel reports TRUE on
   * ::isDebuggingEnabled(), so this behavior can be entirely controlled
   * using the Drush p:debug-en and p:debug-dis commands.
   *
   * @return bool
   */
  public function enabled();

  /**
   * Generate a short and readable class name.
   *
   * @param string|object $caller
   *   Fully namespaced class string or instantiated object.
   *
   * @return string
   */
  public function extractClassName($caller);

  /**
   * Extract information from a request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request object.
   * @param bool $body_title
   *   Whether the request body should be a titled array key.
   *
   * @return string[]
   */
  public function extractRequestInfo(RequestInterface $request, $body_title = FALSE);

  /**
   * Extract information from a response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The HTTP response object.
   * @param bool $body_title
   *   Whether the respone body should be a titled array key.
   *
   * @return string[]
   */
  public function extractResponseInfo(ResponseInterface $response, $body_title = FALSE);

  /**
   * Log the given failure with as much info as possible.
   *
   * @param \Exception $exception
   *   The exception thrown in the request execution code path.
   */
  public function logFailedRequest(\Exception $exception);

  /**
   * Write to Drupal's debug output.
   *
   * @throws \LogicException
   *   Thrown when the debugger isn't enabled.
   *
   * @param string $line
   *   Arbitrary log output, without prefix.
   */
  public function write($line);

  /**
   * Write out a separator line to Drupal's debug output.
   *
   * @throws \LogicException
   *   Thrown when the debugger isn't enabled.
   *
   * @param string $separator
   *   The separation character to use.
   */
  public function writeSeparator($separator = '-');

  /**
   * Write tabular data rendered as table to Drupal's debug output.
   *
   * @throws \LogicException
   *   Thrown when the debugger isn't enabled.
   *
   * @param mixed[] $table
   *   Associative array with each key being the row title, when the array key
   *   is an integer, the row will be fully used. Non-string data will be
   *   rendered using json_encode().
   * @param string $title
   *   Optional title to render above the table content.
   */
  public function writeTable(array $table, $title = NULL);

  /**
   * Write a header title.
   *
   * @throws \LogicException
   *   Thrown when the debugger isn't enabled.
   *
   * @param string $title
   *   Title to render in the center of the string buffer.
   * @param bool $top
   *   Whether to include a top separator line.
   * @param bool $bottom
   *   Whether to include a bottom separator line.
   */
  public function writeTitle($title, $top = TRUE, $bottom = TRUE);

}
