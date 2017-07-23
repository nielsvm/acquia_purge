<?php

namespace Drupal\acquia_purge\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Drupal\acquia_purge\Http\FailedInvalidationException;

/**
 * HTTP middleware which throws FailedInvalidationException's on BAN and PURGE
 * requests sent to Acquia Cloud load balancers.
 */
class LoadBalancerMiddleware {

  /**
   * {@inheritdoc}
   */
  public function __invoke() {
    return function (callable $handler) {
      return function ($request, array $options) use ($handler) {

        // Don't interfere on requests not going to Acquia Load balancers.
        if (!isset($options['acquia_purge_middleware'])) {
          return $handler($request, $options);
        }

        // Return a handler that throws exceptions on bad responses.
        return $handler($request, $options)->then(
          function (ResponseInterface $response) use ($request, $handler, $options) {
            $status = $response->getStatusCode();
            $method = $request->getMethod();

            // Define a tiny closure that throws exceptions for us.
            $e = function($msg) use ($request, $response, $method, $status) {
              $msg = sprintf("HTTP %d; %s %s; %s", $status, $method, $request->getUri(), $msg);
              throw new FailedInvalidationException($msg, $request, $response);
            };

            // Flag up suspicious response types.
            if ($status === 403) {
              $e('Forbidden is abnormal and suggests that your balancer runs'
              . ' on a malfunctioning custom VCL configuration!');
            }
            elseif ($status == 405) {
              $e('Not allowed; Chances are that you customized the VCL file'
              . ' running on your balancer for a customized cache invalidation'
              . ' token. Please contact Acquia Support and consider reverting'
              . ' your configuration as these setups are no longer supported by'
              . ' the acquia_purge module.');
            }

            // Test response codes per HTTP method.
            if ($method == 'PURGE') {
              if (!in_array($status, [200, 404])) {
                $e("Expected 200 or 404 instead!");
              }
            }
            elseif ($method == 'BAN') {
              if ($status !== 200) {
                $e("Expected 200 instead!");
              }
            }
            else {
              $e("Unsupported HTTP method!");
            }

            return $response;
          }
        );
      };
    };
  }

}
