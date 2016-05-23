<?php

require_once dirname(__FILE__) . '/OAuth.php';


/**
 * Twitter for PHP - library for sending messages to Twitter and receiving status updates.
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2008 David Grudl
 * @license    New BSD License
 * @link       http://phpfashion.com/twitter-for-php
 * @see        https://dev.twitter.com/rest/public
 * @version    3.4
 * 
 * This library normally does not contain an updateAvatar function, I added it.
 * As such I've stripped the library down to just that function and the underlying class
 */
class Twitter
{
	const API_URL = 'https://api.twitter.com/1.1/';

	/**#@+ Timeline {@link Twitter::load()} */
	const ME = 1;
	const ME_AND_FRIENDS = 2;
	const REPLIES = 3;
	const RETWEETS = 128; // include retweets?
	/**#@-*/

	/** @var int */
	public static $cacheExpire = '30 minutes';

	/** @var string */
	public static $cacheDir;

	/** @var array */
	public $httpOptions = array(
		CURLOPT_TIMEOUT => 20,
		CURLOPT_SSL_VERIFYPEER => 0,
		CURLOPT_HTTPHEADER => array('Expect:'),
		CURLOPT_USERAGENT => 'Twitter for PHP',
	);

	/** @var Twitter_OAuthSignatureMethod */
	private $signatureMethod;

	/** @var Twitter_OAuthConsumer */
	private $consumer;

	/** @var Twitter_OAuthConsumer */
	private $token;



	/**
	 * Creates object using consumer and access keys.
	 * @param  string  consumer key
	 * @param  string  app secret
	 * @param  string  optional access token
	 * @param  string  optinal access token secret
	 * @throws TwitterException when CURL extension is not loaded
	 */
	public function __construct($consumerKey, $consumerSecret, $accessToken = NULL, $accessTokenSecret = NULL)
	{
		if (!extension_loaded('curl')) {
			throw new TwitterException('PHP extension CURL is not loaded.');
		}

		$this->signatureMethod = new Twitter_OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new Twitter_OAuthConsumer($consumerKey, $consumerSecret);
		$this->token = new Twitter_OAuthConsumer($accessToken, $accessTokenSecret);
	}


	/**
	 * Tests if user credentials are valid.
	 * @return bool
	 * @throws TwitterException
	 */
	public function authenticate()
	{
		try {
			$res = $this->request('account/verify_credentials', 'GET');
			return !empty($res->id);

		} catch (TwitterException $e) {
			if ($e->getCode() === 401) {
				return FALSE;
			}
			throw $e;
		}
	}
	
	/**
	 * Updates profile image
	 * @param  string
	 * @throws TwitterException
	        * This function is a new addition to David's library
	 */
	public function updateAvatar($image64)
	{
		return $this->request('account/update_profile_image', 'POST', array('image' => $image64));
	}


	/**
	 * Process HTTP request.
	 * @param  string  URL or twitter command
	 * @param  string  HTTP method GET or POST
	 * @param  array   data
	 * @param  array   uploaded files
	 * @return stdClass|stdClass[]
	 * @throws TwitterException
	 */
	public function request($resource, $method, array $data = NULL, array $files = NULL)
	{
		if (!strpos($resource, '://')) {
			if (!strpos($resource, '.')) {
				$resource .= '.json';
			}
			$resource = self::API_URL . $resource;
		}

		foreach (array_keys((array) $data, NULL, TRUE) as $key) {
			unset($data[$key]);
		}

		foreach ((array) $files as $key => $file) {
			if (!is_file($file)) {
				throw new TwitterException("Cannot read the file $file. Check if file exists on disk and check its permissions.");
			}
			$data[$key] = '@' . $file;
		}

		$request = Twitter_OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $resource, $files ? array() : $data);
		$request->sign_request($this->signatureMethod, $this->consumer, $this->token);

		$options = array(
			CURLOPT_HEADER => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
		) + ($method === 'POST' ? array(
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $files ? $data : $request->to_postdata(),
			CURLOPT_URL => $files ? $request->to_url() : $request->get_normalized_http_url(),
		) : array(
			CURLOPT_URL => $request->to_url(),
		)) + $this->httpOptions;

		$curl = curl_init();
		curl_setopt_array($curl, $options);
		$result = curl_exec($curl);
		if (curl_errno($curl)) {
			throw new TwitterException('Server error: ' . curl_error($curl));
		}

		$payload = defined('JSON_BIGINT_AS_STRING')
			? @json_decode($result, FALSE, 128, JSON_BIGINT_AS_STRING)
			: @json_decode($result); // intentionally @

		if ($payload === FALSE) {
			throw new TwitterException('Invalid server response');
		}

		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($code >= 400) {
			throw new TwitterException(isset($payload->errors[0]->message)
				? $payload->errors[0]->message
				: "Server error #$code",
				$code
			);
		}

		return $payload;
	}


	/**
	 * Cached HTTP request.
	 * @param  string  URL or twitter command
	 * @param  array
	 * @param  int
	 * @return stdClass|stdClass[]
	 */
	public function cachedRequest($resource, array $data = NULL, $cacheExpire = NULL)
	{
		if (!self::$cacheDir) {
			return $this->request($resource, 'GET', $data);
		}
		if ($cacheExpire === NULL) {
			$cacheExpire = self::$cacheExpire;
		}

		$cacheFile = self::$cacheDir
			. '/twitter.'
			. md5($resource . json_encode($data) . serialize(array($this->consumer, $this->token)))
			. '.json';

		$cache = @json_decode(@file_get_contents($cacheFile)); // intentionally @
		$expiration = is_string($cacheExpire) ? strtotime($cacheExpire) - time() : $cacheExpire;
		if ($cache && @filemtime($cacheFile) + $expiration > time()) { // intentionally @
			return $cache;
		}

		try {
			$payload = $this->request($resource, 'GET', $data);
			file_put_contents($cacheFile, json_encode($payload));
			return $payload;

		} catch (TwitterException $e) {
			if ($cache) {
				return $cache;
			}
			throw $e;
		}
	}

}



/**
 * An exception generated by Twitter.
 */
class TwitterException extends Exception
{
}
