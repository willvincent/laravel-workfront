<?php
/*
 * Copyright (c) 2011 AtTask, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * StreamClient class.
 *
 * @throws StreamClientException
 * @package StreamClient
 */
namespace h4hardik\AtTask;

class StreamClient {

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
		$queue = null,
		$batch = false,
		$atomic = false,
		$handle = null,
		$hostname = null,
		$sessionID = null;

	/**
	 * Creates an instance of the client.
	 *
	 * @param  string $hostname
	 * @param  {string|null} $version [optional]
	 * @return void
	 */
	public function __construct ($hostname, $version = null) {
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
			throw new StreamClientException('Missing required PHP extension cURL');
		}

		// Initialize cURL
		if (is_null($this->handle)) {
			$this->handle = curl_init();
			curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
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
			$this->handle = null;
		}
	}

	/**
	 * Login to @task
	 *
	 * @throws StreamClientException
	 * @param  string $username
	 * @param  string $password
	 * @return object
	 */
	public function login ($username, $password) {
		// Validate request before unnecessarily taxing the server
		if (empty($username) || empty($password)) {
			throw new StreamClientException('Please provide both a username and password');
		}

		$result = $this->request(self::PATH_LOGIN, array('username' => $username, 'password' => $password), null, self::METH_GET);
		
		// Store session ID
		$this->sessionID = !is_null($result) ? $result->sessionID : null;
		return $result;
	}

	/**
	 * Logout from @task
	 *
	 * @throws StreamClientException
	 * @return bool
	 */
	public function logout () {
		$result = $this->request(self::PATH_LOGOUT, array('sessionID' => $this->sessionID), null, self::METH_GET);

		// Discard session ID
		$this->sessionID = null;
		return !is_null($result) ? $result->success : false;
	}

	/**
	 * Searches for all objects that match a given query.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  array $query
	 * @param  {array|null} $fields [optional]
	 * @return array
	 */
	public function search ($objCode, $query, $fields = null) {
		return $this->request($objCode.self::PATH_SEARCH, $query, $fields, self::METH_GET);
	}

	/**
	 * Retrieves an object by ID.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  string $objID
	 * @param  {array|null} $fields [optional]
	 * @return object
	 */
	public function get ($objCode, $objID, $fields = null) {
		return $this->request($objCode.'/'.$objID, null, $fields, self::METH_GET);
	}

	/**
	 * Inserts a new object.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  array $message
	 * @param  {array|null} $fields [optional]
	 * @return object
	 */
	public function post ($objCode, $message, $fields = null) {
		return $this->request($objCode, $message, $fields, self::METH_POST);
	}

	/**
	 * Edits an existing object.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  string $objID
	 * @param  array $message
	 * @param  {array|null} $fields [optional]
	 * @return object
	 */
	public function put ($objCode, $objID, $message, $fields = null) {
		return $this->request($objCode.'/'.$objID, array('updates' => json_encode($message)), $fields, self::METH_PUT);
	}

	/**
	 * Deletes an object.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  string $objID
	 * @param  bool $force [optional]
	 * @return bool
	 */
	public function delete ($objCode, $objID, $force = false) {
		// Build request parameters
		$params = null;
		if ($force) {
			$params = array('force' => 'true');
		}

		$result = $this->request($objCode.'/'.$objID, $params, null, self::METH_DELETE);
		return !is_null($result) ? $result->success : false;
	}

	/**
	 * Reports all objects that match a given query.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  array $query
	 * @return object
	 */
	public function report ($objCode, $query) {
		return $this->request($objCode.self::PATH_REPORT, $query, null, self::METH_GET);
	}

	/**
	 * Provides the total number of results that match a given query.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  array $query
	 * @param  {array|null} $fields [optional]
	 * @return int
	 */
	public function count ($objCode, $query, $fields = null) {
		$result = $this->request($objCode.self::PATH_COUNT, $query, $fields, self::METH_GET);
		return !is_null($result) ? $result->count : 0;
	}

	/**
	 * Copies an existing object.
	 * 
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  string $objID
	 * @param  {array|null} $message [optional]
	 * @param  {array|null} $fields [optional]
	 * @return object
	 */
	public function copy ($objCode, $objID, $message = null, $fields = null) {
		// Build request parameters
		$params = array('copySourceID' => $objID);
		if (!is_null($message)) {
			$params['updates'] = json_encode($message);
		}
		
		return $this->request($objCode, $params, $fields, self::METH_POST);
	}

	/**
	 * Executes a named action on the server.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  string $objID
	 * @param  string $action
	 * @param  {array|null} $params [optional]
	 * @return bool
	 */
	public function execute ($objCode, $objID, $action, $params = null) {
		$result = $this->request($objCode.'/'.$objID.'/'.$action, $params, null, self::METH_PUT);
		return !is_null($result) ? $result->success : false;
	}

	/**
	 * Executes a named query on the server.
	 *
	 * @throws StreamClientException
	 * @param  string $objCode
	 * @param  array $queryName
	 * @param  {array|null} $query [optional]
	 * @param  {array|null} $fields [optional]
	 * @return array
	 */
	public function namedquery ($objCode, $queryName, $query = null, $fields = null) {
		return $this->request($objCode.'/'.$queryName, $query, $fields, self::METH_GET);
	}

	/**
	 * Retrieves API metadata for an object.
	 *
	 * @throws StreamClientException
	 * @param  {string|null} $objCode [optional]
	 * @return object
	 */
	public function metadata ($objCode = null) {
		// Build request path
		$path = '';
		if (!empty($objCode)) {
			$path .= $objCode . '/';
		}
		$path .= self::PATH_METADATA;

		return $this->request($path, null, null, self::METH_GET);
	}

	/**
	 * Sets a flag indicating that all subsequent calls to the API should be queued for a single request.
	 *
	 * @param  bool $atomic
	 * @return void
	 */
	public function batchStart ($atomic = false) {
		$this->queue = array();
		$this->batch = true;
		$this->atomic = $atomic;
	}

	/**
	 * Executes the queued batch of requests.
	 *
	 * @throws StreamClientException
	 * @return object
	 */
	public function batchEnd () {
		// Validate request before unnecessarily taxing the server
		if (sizeof($this->queue) == 0) {
			throw new StreamClientException('Batch operations must specify at least one \'uri\' parameter');
		}

		// Build request parameters
		$params = array();
		if ($this->atomic) {
			$params['atomic'] = 'true';
		}
		$params['uri'] = $this->queue;

		// Reset batch properties
		$this->queue = null;
		$this->batch = false;
		$this->atomic = false;

		return $this->request(self::PATH_BATCH, $params, null, self::METH_GET);
	}

	/**
	 * Prepares request data into a URI format to be sent to the server.
	 *
	 * @param  string $path
	 * @param  {array|null} $params
	 * @param  {array|null} $fields
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
	 * @throws StreamClientException
	 * @param  string $path
	 * @param  {array|null} $params
	 * @param  {array|null} $fields
	 * @param  string $method
	 * @return mixed
	 */
	private function request ($path, $params, $fields, $method) {
		// Prepare the request URI
		$uri = $this->prepare($path, $params, $fields, $method);

		// Add request to the queue if running in batch mode
		if ($this->batch) {
			$this->queue[] = $uri;
			return null;
		}

		// Set dynamic cURL options
		curl_setopt($this->handle, CURLOPT_URL, $this->hostname . $uri);

		//echo $this->hostname . $uri;
		
		// Execute request
		if (!($response = curl_exec($this->handle))) {
			throw new StreamClientException(curl_error($this->handle));
		}
		$result = json_decode($response);

		// Verify result
		if (isset($result->error)) {
			throw new StreamClientException($result->error->message);
		}
		else if (!isset($result->data)) {
			throw new StreamClientException('Invalid response from server');
		}

		return $result->data;
	}

}
/**
 * StreamClientException class.
 * 
 * @package StreamClient
 */
class StreamClientException extends \Exception {}


?>
