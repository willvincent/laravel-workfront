<?php namespace willvincent\Workfront;

use willvincent\Workfront\WorkfrontException;

class WorkfrontClient {

  // Supported request methods
  const
    METH_DELETE = 'DELETE',
    METH_GET    = 'GET',
    METH_POST   = 'POST',
    METH_PUT    = 'PUT';

  // Well known paths
  const
    PATH_CONTEXT = '/attask/api',
    PATH_LOGIN  = '/login',
    PATH_LOGOUT = '/logout',
    PATH_SEARCH = '/search',
    PATH_COUNT = '/count',
    PATH_BATCH = '/batch',
    PATH_REPORT = '/report',
    PATH_METADATA = '/metadata';

  private
    $queue = NULL,
    $batch = FALSE,
    $atomic = FALSE,
    $handle = NULL,
    $hostname = NULL,
    $sessionID = NULL,
    $config = [];

  /**
   * @param  $config
   */
  public function __construct($config) {
    $this->config = $config;

    $hostname = $config['host.url'];
    $version = $config['api.version'];
    // Remove trailing slash from hostname if present
    if (substr($hostname, strlen($hostname) - 1) == '/') {
      $hostname = substr($hostname, 0, strlen($hostname) - 1);
    }

    // Append URI context to hostname
    $hostname .= self::PATH_CONTEXT;

    // Append version to hostname if provided
    if (!is_null($version)) {
      $hostname .= '/v' . $version;
    }

    // Assign hostname
    $this->hostname = $hostname;

    // Make sure that cURL is loaded
    if (!extension_loaded('curl')) {
      throw new WorkfrontException('Missing required PHP extension cURL');
    }

    // Initialize cURL
    if (is_null($this->handle)) {
      $this->handle = curl_init();
      curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, FALSE);
      curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, TRUE);
    }
  }

  /**
   * Destroys an instance of the client.
   *
   * @return void
   */
  public function __destruct () {
    // Close cURL
    if (!is_null($this->handle)) {
      curl_close($this->handle);
      $this->handle = NULL;
    }
  }

  /**
   * Logged in status
   *
   * @return  boolean
   */
  public function status () {
    if (is_null($this->sessionID)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Login to Workfront
   *
   * @throws WorkfrontException
   * @param  string $username
   * @param  string $password
   * @return object
   */
  public function login ($username = '', $password = '') {
    if (empty($username)) {
      $username = $this->config['account.login'];
    }
    if (empty($password)) {
      $password = $this->config['account.password'];
    }

    // Validate request before unnecessarily taxing the server
    if (empty($username) || empty($password)) {
      throw new WorkfrontException('Please provide both a username and password');
    }

    $result = $this->request(
      self::PATH_LOGIN,
      array(
        'username' => $username,
        'password' => $password
      ),
      NULL,
      self::METH_POST
    );

    // Store session ID
    $this->sessionID = !is_null($result) ? $result->sessionID : NULL;

    return $result;
  }

  /**
   * Logout from Workfront
   *
   * @throws WorkfrontException
   * @return bool
   */
  public function logout () {
    $result = $this->request(
      self::PATH_LOGOUT,
      array(
        'sessionID' => $this->sessionID
      ),
      NULL,
      self::METH_GET
    );

    // Discard session ID
    $this->sessionID = NULL;

    return !is_null($result) ? $result->success : FALSE;
  }

  /**
   * Searches for all objects that match a given query.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  array $query
   * @param  {array|NULL} $fields [optional]
   * @return array
   */
  public function search ($objCode, $query, $fields = NULL) {
    return $this->request(
      $objCode . self::PATH_SEARCH,
      $query,
      $fields,
      self::METH_GET
    );
  }

  /**
   * Retrieves an object by ID.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  string $objID
   * @param  {array|NULL} $fields [optional]
   * @return object
   */
  public function get ($objCode, $objID, $fields = NULL) {
    return $this->request(
      $objCode . '/' . $objID,
      NULL,
      $fields,
      self::METH_GET
    );
  }

  /**
   * Inserts a new object.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  array $message
   * @param  {array|NULL} $fields [optional]
   * @return object
   */
  public function post ($objCode, $message, $fields = NULL) {
    return $this->request(
      $objCode,
      $message,
      $fields,
      self::METH_POST
    );
  }

  /**
   * Edits an existing object.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  string $objID
   * @param  array $message
   * @param  {array|NULL} $fields [optional]
   * @return object
   */
  public function put ($objCode, $objID, $message, $fields = NULL) {
    return $this->request(
      $objCode . '/' . $objID,
      array(
        'updates' => json_encode($message)
      ),
      $fields,
      self::METH_PUT
    );
  }

  /**
   * Deletes an object.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  string $objID
   * @param  bool $force [optional]
   * @return bool
   */
  public function delete ($objCode, $objID, $force = FALSE) {
    // Build request parameters
    $params = NULL;
    if ($force) {
      $params = array('force' => 'TRUE');
    }

    $result = $this->request(
      $objCode . '/' . $objID,
      $params,
      NULL,
      self::METH_DELETE
    );

    return !is_null($result) ? $result->success : FALSE;
  }

  /**
   * Reports all objects that match a given query.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  array $query
   * @return object
   */
  public function report ($objCode, $query) {
    return $this->request(
      $objCode . self::PATH_REPORT,
      $query,
      NULL,
      self::METH_GET
    );
  }

  /**
   * Provides the total number of results that match a given query.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  array $query
   * @param  {array|NULL} $fields [optional]
   * @return int
   */
  public function count ($objCode, $query, $fields = NULL) {
    $result = $this->request(
      $objCode . self::PATH_COUNT,
      $query,
      $fields,
      self::METH_GET
    );

    return !is_null($result) ? $result->count : 0;
  }

  /**
   * Copies an existing object.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  string $objID
   * @param  {array|NULL} $message [optional]
   * @param  {array|NULL} $fields [optional]
   * @return object
   */
  public function copy ($objCode, $objID, $message = NULL, $fields = NULL) {
    // Build request parameters
    $params = array('copySourceID' => $objID);
    if (!is_null($message)) {
      $params['updates'] = json_encode($message);
    }

    return $this->request(
      $objCode,
      $params,
      $fields,
      self::METH_POST
    );
  }

  /**
   * Executes a named action on the server.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  string $objID
   * @param  string $action
   * @param  {array|NULL} $params [optional]
   * @return bool
   */
  public function execute ($objCode, $objID, $action, $params = NULL) {
    $result = $this->request(
      $objCode . '/' . $objID . '/' . $action,
      $params,
      NULL,
      self::METH_PUT
    );

    return !is_null($result) ? $result->success : FALSE;
  }

  /**
   * Executes a named query on the server.
   *
   * @throws WorkfrontException
   * @param  string $objCode
   * @param  array $queryName
   * @param  {array|NULL} $query [optional]
   * @param  {array|NULL} $fields [optional]
   * @return array
   */
  public function namedquery ($objCode, $queryName, $query = NULL, $fields = NULL) {
    return $this->request(
      $objCode . '/' . $queryName,
      $query,
      $fields,
      self::METH_GET
    );
  }

  /**
   * Retrieves API metadata for an object.
   *
   * @throws WorkfrontException
   * @param  {string|NULL} $objCode [optional]
   * @return object
   */
  public function metadata ($objCode = NULL) {
    // Build request path
    $path = '';
    if (!empty($objCode)) {
      $path .= $objCode . '/';
    }
    $path .= self::PATH_METADATA;

    return $this->request(
      $path,
      NULL,
      NULL,
      self::METH_GET
    );
  }

  /**
   * Sets a flag indicating that all subsequent calls to the API should be queued for a single request.
   *
   * @param  bool $atomic
   * @return void
   */
  public function batchStart ($atomic = FALSE) {
    $this->queue = array();
    $this->batch = TRUE;
    $this->atomic = $atomic;
  }

  /**
   * Executes the queued batch of requests.
   *
   * @throws WorkfrontException
   * @return object
   */
  public function batchEnd () {
    // Validate request before unnecessarily taxing the server
    if (sizeof($this->queue) == 0) {
      throw new WorkfrontException('Batch operations must specify at least one \'uri\' parameter');
    }

    // Build request parameters
    $params = array();
    if ($this->atomic) {
      $params['atomic'] = 'TRUE';
    }
    $params['uri'] = $this->queue;

    // Reset batch properties
    $this->queue = NULL;
    $this->batch = FALSE;
    $this->atomic = FALSE;

    return $this->request(
      self::PATH_BATCH,
      $params,
      NULL,
      self::METH_GET
    );
  }

  /**
   * Prepares request data into a URI format to be sent to the server.
   *
   * @param  string $path
   * @param  {array|NULL} $params
   * @param  {array|NULL} $fields
   * @param  string $method
   * @return string
   */
  private function prepare ($path, $params, $fields, $method) {
    // Ensure the path begins with a slash
    if ($path[0] != '/') {
      $path = '/' . $path;
    }

    // Create the query string for the requeset
    $query = array();
    if (!is_null($this->sessionID) && !$this->batch) {
      $query['sessionID'] = $this->sessionID;
    }
    $query['method'] = $method;
    $query = http_build_query($query);

    // Add provided parameters to the query string
    if ($this->iterable($params)) {
      foreach ((array) $params as $name => $value) {
        // Allow values of query parameters to be an array
        $values = $this->iterable($value) ? $value : array($value);
        foreach ((array) $values as $val) {
          $query .= '&' . urlencode($name) . '=' . urlencode($val);
        }
      }
    }

    // Add provided fields to the query string
    if ($this->iterable($fields)) {
      $query .= '&fields=' . urlencode(implode(',', (array) $fields));
    }

    return $path . '?' . $query;
  }

  /**
   * Determine if a variable is an iterable value
   *
   * @param  mixed $var
   * @return bool
   */
  private function iterable ($var) {
    return !is_null($var) && is_array($var) || is_object($var);
  }

  /**
   * Performs the request to the server.
   *
   * @throws WorkfrontException
   * @param  string $path
   * @param  {array|NULL} $params
   * @param  {array|NULL} $fields
   * @param  string $method
   * @return mixed
   */
  private function request ($path, $params, $fields, $method) {
    // Prepare the request URI
    $uri = $this->prepare($path, $params, $fields, $method);

    // Add request to the queue if running in batch mode
    if ($this->batch) {
      $this->queue[] = $uri;
      return NULL;
    }

    // Set dynamic cURL options
    curl_setopt($this->handle, CURLOPT_URL, $this->hostname . $uri);
    //echo $this->hostname . $uri;

    // Execute request
    if (!($response = curl_exec($this->handle))) {
      throw new WorkfrontException(curl_error($this->handle));
    }

    $result = json_decode($response);

    // Verify result
    if (isset($result->error)) {
      throw new WorkfrontException($result->error->message);
    }
    else if (!isset($result->data)) {
      throw new WorkfrontException('Invalid response from server');
    }

    return $result->data;
  }

}
