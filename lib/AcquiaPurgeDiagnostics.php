<?php

/**
 * @file
 * Contains AcquiaPurgeExecutorsService.
 */

/**
 * Provides access to the diagnostic tests.
 */
class AcquiaPurgeDiagnostics {

  /**
   * The diagnostic test functions.
   *
   * @var string[]
   */
  protected $functions = array();

  /**
   * Includes to load before executing the test functions.
   *
   * @var array[]
   */
  protected $includes = array();

  /**
   * Contains the error result when the system is blocked, FALSE otherwise.
   *
   * @var null|bool|array
   */
  protected $isSystemBlocked = NULL;

  /**
   * The state item containing hashes of previously logged error messages.
   *
   * @var null|AcquiaPurgeStateItemInterface
   */
  protected $loggedErrors = NULL;

  /**
   * The gathered results as associative array with arrays in them.
   *
   * @var null|array[]
   */
  protected $results = NULL;

  /**
   * The Acquia Purge service object.
   *
   * @var AcquiaPurgeService
   */
  protected $service;

  /**
   * Construct a new AcquiaPurgeDiagnostics instance.
   *
   * @param AcquiaPurgeService $service
   *   The Acquia Purge service object.
   */
  public function __construct(AcquiaPurgeService $service) {
    $this->loggedErrors = $service->state()->get('logged_errors', array());
    $this->service = $service;
  }

  /**
   * Get the test results.
   *
   * @param int $verbosity
   *   (optional) Return test results matching the given level or higher.
   *
   * @return array[]
   *   Array that complies to the format as seen in hook_requirements().
   */
  public function get($verbosity = ACQUIA_PURGE_SEVLEVEL_INFO) {
    $this->initializeTestResults();
    $results = array();
    foreach ($this->results as $id => $result) {
      if ($result['severity'] >= $verbosity) {
        $results[$id] = $result;
      }
    }
    return $results;
  }

  /**
   * Gather test function names from hook_acquia_purge_diagnostics().
   */
  protected function initializeTestFunctionsList() {
    if (empty($this->functions)) {
      $cid = 'acquia_purge_diagnostics_v' . ACQUIA_PURGE_VERSION;
      if ($cache = cache_get($cid)) {
        $this->functions = $cache->data['f'];
        $this->includes = $cache->data['i'];
      }
      else {
        foreach (module_implements('acquia_purge_diagnostics') as $module) {
          $hook = $module . '_acquia_purge_diagnostics';
          foreach ($hook() as $i => $value) {
            if ($i === 'module_load_include') {
              $this->includes[] = $value;
            }
            else {
              $this->functions[] = $value;
            }
          }
        }
        cache_set($cid, array('i' => $this->includes, 'f' => $this->functions));
      }
    }
  }

  /**
   * Execute the test functions and gather the results.
   */
  protected function initializeTestResults() {
    if (is_null($this->results)) {
      $this->initializeTestFunctionsList();
      $this->results = array();

      // Firstly, load all the includes that have been collected earlier.
      foreach ($this->includes as $include) {
        call_user_func_array('module_load_include', $include);
      }

      // Iterate each test function and normalize and enrich the returning data.
      $t = get_t();
      $r = array();
      foreach ($this->functions as $f) {
        $r[$f] = $f($t, $this->service);
        $r[$f]['name'] = isset($r[$f]['title']) ? $r[$f]['title'] : $f;
        $r[$f]['description'] = isset($r[$f]['description']) ? $r[$f]['description'] : NULL;
        $r[$f]['description_plain'] = strip_tags($r[$f]['description']);
        $r[$f]['severity'] = isset($r[$f]['severity']) ? $r[$f]['severity'] : ACQUIA_PURGE_SEVLEVEL_INFO;
        $r[$f]['value_plain'] = isset($r[$f]['value_plain']) ? $r[$f]['value_plain'] : $r[$f]['value'];
        $r[$f]['value'] = $t('<b>@title</b><br />@value', array(
          '@title' => $r[$f]['title'],
          '@value' => $r[$f]['value'],
          )
        );
        $r[$f]['title'] = $t('Acquia Purge');
      }
      $this->results = $r;
    }
  }

  /**
   * Is the system blocked by an error? If so, retrieve the error.
   *
   * @return bool|array
   *   FALSE|Array that complies to the format as seen in hook_requirements().
   */
  public function isSystemBlocked() {
    if (is_null($this->isSystemBlocked)) {
      $errors = $this->get(ACQUIA_PURGE_SEVLEVEL_ERROR);
      $this->isSystemBlocked = empty($errors) ? FALSE : current($errors);
    }
    return $this->isSystemBlocked;
  }

  /**
   * Log diagnostic test results to watchdog.
   *
   * @param array $results
   *   Associative array with test results or an individual test result array.
   * @param bool $deduplicate
   *   Prevent diagnostic messages from ending up in the logs multiple times
   *   when they already have been logged since the last queue wipe.
   */
  public function log(array $results, $deduplicate = TRUE) {
    $map = array(
      ACQUIA_PURGE_SEVLEVEL_INFO    => WATCHDOG_INFO,
      ACQUIA_PURGE_SEVLEVEL_OK      => WATCHDOG_INFO,
      ACQUIA_PURGE_SEVLEVEL_WARNING => WATCHDOG_ERROR,
      ACQUIA_PURGE_SEVLEVEL_ERROR   => WATCHDOG_CRITICAL,
    );

    // Wrap single a single test result into a workable array.
    if (isset($results['severity'])) {
      $results = array($results);
    }

    // Iterate the items and report them to the watchdog log.
    foreach ($results as $result) {
      $descr = $result['description_plain'];
      if (empty($description)) {
        $descr = $result['value_plain'];
      }

      // If we aren't asked to deduplicate messages, log it straight away.
      if (!$deduplicate) {
        watchdog('acquia_purge', $descr, array(), $map[$result['severity']]);
      }

      // Hash logged error messages to prevent them from being logged again. The
      // hashes are in a state item, so they're gone after the queue cleared.
      else {
        $logged_errors = $this->loggedErrors->get();
        $hash = sha1($descr);
        if (in_array($hash, $logged_errors)) {
          return;
        }

        // Log the message and add the hash to the deduplication list.
        watchdog('acquia_purge', $descr, array(), $map[$result['severity']]);
        $logged_errors[] = $hash;
        $this->loggedErrors->set($logged_errors);
      }
    }
  }

}
