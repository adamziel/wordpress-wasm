<?php

/**
 * This transport delegates PHP HTTP requests to JavaScript synchronous XHR.
 *
 * This file isn't actually used. It's just here for reference and development. The actual
 * PHP code used in WordPress is hardcoded copy residing in wordpress.mjs in the _patchWordPressCode
 * function.
 *
 * The reason for calling it Wp_Http_Fetch and not something more natural like
 * Requests_Transport_Fetch is the _get_first_available_transport(). It checks for
 * a class named "Wp_Http_" . $transport_name – which means we must adhere to this
 * hardcoded pattern.
 */
class Wp_Http_Fetch_Base
{
	public $headers = '';

	public function __construct()
	{
	}

	public function __destruct()
	{
	}

	/**
	 * Delegates PHP HTTP requests to JavaScript synchronous XHR.
	 *
	 * @TODO Implement handling for more $options such as cookies, filename, auth, etc.
	 *
	 * @param $url
	 * @param $headers
	 * @param $data
	 * @param $options
	 *
	 * @return false|string
	 */
	public function request($url, $headers = array(), $data = array(), $options = array())
	{
		if (!empty($data)) {
			$data_format = $options['data_format'];
			if ($data_format === 'query') {
				$url = self::format_get($url, $data);
				$data = '';
			} elseif (!is_string($data)) {
				$data = http_build_query($data, '', '&');
			}
		}

		$request = json_encode(
			array(
				'type' => 'request',
				'data' => [
					'headers' => $headers,
					'data' => $data,
					'url' => $url,
					'method' => $options['type'],
				]
			)
		);

		$this->headers = post_message_to_js($request);

		// Store a file if the request specifies it.
		// Are we sure that `$this->headers` includes the body of the response?
		$before_response_body = strpos($this->headers, "\r\n\r\n");
		if (isset($options['filename']) && $options['filename'] && false !== $before_response_body) {
			$response_body = substr($this->headers, $before_response_body + 4);
			$this->headers = substr($this->headers, 0, $before_response_body);
			file_put_contents($options['filename'], $response_body);
		}

		return $this->headers;
	}

	public function request_multiple($requests, $options)
	{
		$responses = array();
		$class = get_class($this);
		foreach ($requests as $id => $request) {
			try {
				$handler = new $class();
				$responses[$id] = $handler->request($request['url'], $request['headers'], $request['data'], $request['options']);
				$request['options']['hooks']->dispatch('transport.internal.parse_response', array(&$responses[$id], $request));
			} catch (Requests_Exception $e) {
				$responses[$id] = $e;
			}
			if (!is_string($responses[$id])) {
				$request['options']['hooks']->dispatch('multiple.request.complete', array(&$responses[$id], $id));
			}
		}

		return $responses;
	}

	protected static function format_get($url, $data)
	{
		if (!empty($data)) {
			$query = '';
			$url_parts = parse_url($url);
			if (empty($url_parts['query'])) {
				$url_parts['query'] = '';
			} else {
				$query = $url_parts['query'];
			}
			$query .= '&' . http_build_query($data, '', '&');
			$query = trim($query, '&');
			if (empty($url_parts['query'])) {
				$url .= '?' . $query;
			} else {
				$url = str_replace($url_parts['query'], $query, $url);
			}
		}

		return $url;
	}

	public static function test($capabilities = array())
	{
		if (!function_exists('post_message_to_js')) {
			return false;
		}

		return true;
	}
}

if (class_exists('\WpOrg\Requests\Requests')) {
	class Wp_Http_Fetch extends Wp_Http_Fetch_Base implements \WpOrg\Requests\Transport
	{

	}
} else {
	class Wp_Http_Fetch extends Wp_Http_Fetch_Base implements Requests_Transport
	{

	}
}
