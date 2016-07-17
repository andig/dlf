<?php

namespace Dlf;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;

define('CACHE_DURATION', 3600 * 24 * 365);

function retryDecider() {
	return function (
	  $retries,
	  Request $request,
	  Response $response = null,
	  RequestException $exception = null
	) {
		// Limit the number of retries to 5
		if ($retries >= 5) {
			return false;
		}

		// Retry connection exceptions
		if ($exception instanceof ConnectException) {
			return true;
		}

		if ($response) {
			// Retry on server errors
			if ($response->getStatusCode() >= 500) {
				return true;
			}

			// Retry on rate limits
			if ($response->getStatusCode() == 429) {
				$retryDelay = $response->getHeaderLine('Retry-After');
				var_dump($retryDelay);

				if (strlen($retryDelay)) {
					sleep((int)$retryDelay);
					return true;
				}
			}
		}

		return false;
	};
}

function retryDelay() {
    return function($numberOfRetries) {
        return 1000 * $numberOfRetries;
    };
}

class ClientFactory
{
	public static function getClient($clientOptions = []) {
		// Basic directory cache example
		$cacheProvider = new FilesystemCache(__DIR__ . '/cache');
		// Guzzle will determine an appropriate default handler if `null` is given.
		$defaultHandler = null;
		// Create a cache handler with a given cache provider and default handler.
		$cacheHandler = new CacheHandler($cacheProvider, $defaultHandler, [
			'methods' => ['GET'],
			'expire' => CACHE_DURATION,
		]);

		$handlerStack = HandlerStack::create($cacheHandler);
		$handlerStack->push(Middleware::retry(retryDecider(), retryDelay()));

		$options = array_merge([
			'handler' => $handlerStack,
			'timeout'  => 10,
			// 'allow_redirects' => false,
		], $clientOptions);

		$client = new Client($options);

		return $client;
	}
}