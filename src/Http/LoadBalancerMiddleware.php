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
        if (!isset($options['acquia_purge_load_balancer_middleware'])) {
          return $handler($request, $options);
        }

        // Return a handler that throws exceptions on bad responses.
        return $handler($request, $options)->then(
          function (ResponseInterface $response) use ($request, $handler, $method) {
            $method = $request->getMethod();

            // PURGE requests should return either a 200 or a 404.
            if ($method == 'PURGE') {
              if (!in_array($response->getStatusCode(), [200, 404])) {
                throw new FailedInvalidationException(
                  sprintf(
                    "%s expected 200||404 but got %s!",
                    $request->getMethod() . ' ' . $request->getUri(),
                    $response->getStatusCode()
                  ),
                  $request,
                  $response
                );
              }
            }

            // BAN requests should always return a 200.
            elseif ($method == 'BAN') {
              if ($response->getStatusCode() !== 200) {
                throw new FailedInvalidationException(
                  sprintf(
                    "%s expected 200 but got %s!",
                    $request->getMethod() . ' ' . $request->getUri(),
                    $response->getStatusCode()
                  ),
                  $request,
                  $response
                );
              }
            }
            return $response;
          }
        );
      };
    };
  }

}
