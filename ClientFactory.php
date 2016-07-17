<?php

namespace Dlf;

use GuzzleHttp\Client;
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;

define('CACHE_DURATION', 3600 * 24 * 365);

class ClientFactory
{
	public static function getClient($clientOptions = []) {
		// Basic directory cache example
		$cacheProvider = new FilesystemCache(__DIR__ . '/cache');
		// Guzzle will determine an appropriate default handler if `null` is given.
		$defaultHandler = null;
		// Create a cache handler with a given cache provider and default handler.
		$handler = new CacheHandler($cacheProvider, $defaultHandler, [
		    'methods' => ['GET'],
		    'expire' => CACHE_DURATION,
		]);

		$options = array_merge([
		    'handler' => $handler,
		    'timeout'  => 10,
		    // 'allow_redirects' => false,
		], $clientOptions);

		$client = new Client($options);

		return $client;
	}
}