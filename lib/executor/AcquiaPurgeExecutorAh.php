<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorAh.
 */

/**
 * Executor that clears URLs across all Acquia Cloud load balancers.
 */
class AcquiaPurgeExecutorAh extends AcquiaPurgeExecutorBase implements AcquiaPurgeExecutorInterface {

  /**
   * {@inheritdoc}
   */
  public static function isEnabled(AcquiaPurgeService $service) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($invalidations) {
    $hosting_info = $this->service->hostingInfo();
    $balancer_token = $hosting_info->getBalancerToken();
    $geoip = $this->service->oddities()->has('geoip');
    $wildcards = $this->service->oddities()->has('wildcards');

    // Create a long list of executable HTTP requests.
    $requests = array();
    foreach ($hosting_info->getBalancerAddresses() as $balancer_ip) {
      foreach ($invalidations as $invalidation) {
        $invalidation_has_wildcard = $invalidation->hasWildcard();
        $request_must_use_ban = $geoip || $invalidation_has_wildcard;

        // Fetch a new request object and propagate all data.
        $r = $this->getRequest();
        // Set properties not used by ::requestsExecute() but that we use later.
        $r->_invalidation = $invalidation;
        $r->_balancer_ip = $balancer_ip;
        $r->_host = $invalidation->getDomain();
        // Set all the properties required for the cache invalidation.
        $r->scheme = $invalidation->getScheme();
        $r->path = $invalidation->getPath();
        $r->uri = $r->scheme . '://' . $r->_balancer_ip . $r->path;
        $r->method = $request_must_use_ban ? 'BAN' : 'PURGE';
        $r->headers = array(
          'Host: ' . $r->_host,
          'Accept-Encoding: gzip',
          'X-Acquia-Purge: ' . $balancer_token,
        );
        $requests[] = $r;

        // Register the 'wildcards' oddity when this is the first encountered
        // wildcard invalidation, this way, diagnostics can act report problems.
        if ($invalidation_has_wildcard && (!$wildcards)) {
          $this->service->oddities()->add('wildcards');
        }
      }
    }

    // Execute the HTTP requests and process the results.
    $this->requestsExecute($requests, TRUE);
    foreach ($requests as $request) {

      // Regard HTTP 200 and 404 as successful cache invalidations. Anything
      // else isn't expected to come back from Acquia Cloud's load balancers and
      // is therefore registered as oddity to be able to diagnose later.
      if (in_array($request->response_code, array(404, 200))) {
        $request->result = TRUE;
      }
      else {
        $request->result = FALSE;
        if (strlen($response_code = (string) $request->response_code)) {
          $this->service->oddities()->add($response_code);
        }
      }

      // Since we make multiple HTTP requests for a single invalidation (to each
      // load balancer), we set a specific context suffix on the invalidation
      // object. Now, when we call ::setStatusSucceeded()/Failed, the suffix
      // will be used in the background to keep the statuses apart. Now when
      // AcquiaPurgeService::process() requests the parent queue item for
      // result evaluation, only success comes out of it if everything went ok.
      $request->_invalidation->setStatusContextSuffix($request->_balancer_ip);
      if ($request->result) {
        $request->_invalidation->setStatusSucceeded();
      }
      else {
        $request->_invalidation->setStatusFailed();
      }
    }

    // Log each processed request.
    $this->requestsLog($requests);
  }

}
