<?php

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;
use Concat\Http\Handler\CacheHandler;
use Doctrine\Common\Cache\FilesystemCache;

require_once('./vendor/autoload.php');
require_once('credentials.php');

define('CACHE_DURATION', 3600 * 24 * 365);
define('CATALOG_FILE', 'catalog.json');

function getSpotifyApiToken() {
    global $client;

    $payload = base64_encode(CLIENTID .':'. CLIENTSECRET);
    $response = $client->request('POST', 'https://accounts.spotify.com/api/token', [
        'headers' => [
            'Authorization' => 'Basic ' . $payload
        ],
        'form_params' => [
            'grant_type' => 'client_credentials'
        ]
    ]);

    if ($response->getStatusCode() != 200) {
        echo (string) $response->getBody();
        die;
    }

    $token = json_decode((string) $response->getBody())->access_token;
    return $token;
}

function parseItem($domElement) {
    $html = $domElement->ownerDocument->saveHTML($domElement);
    // echo $html;

    $crawler = new Crawler($html);
    $item = [];

    $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$item, $html) {
        $td = $node->filter('td');
        if ($td->count() !== 2) {
            printf("> invalid entry: \n%s\n", $node->text());
            // echo $html;die;
            return;
        }

        $tag = utf8_decode(html_entity_decode(trim($td->eq(0)->text())));
        $value = utf8_decode(html_entity_decode(trim($td->eq(1)->text())));
        $item[$tag] = $value;
    });

    if (isset($item['Album'])) {
        if ($item['Album'] == 'Klassik - Pop - et - cetera')
            return;

        if (strpos($item['Album'], 'aus: ') === 0)
            $item['Album'] = substr($item['Album'], strlen('aus: '));

        if (preg_match('/(^.*?)\s*[,-]\s*CD\s+\d+(\/\d+)?/', $item['Album'], $m)) {
            // echo $item['Album'];
            $item['Album'] = $m[1];
        }

        if (preg_match('/^CD\s+"(.*?)"/', $item['Album'], $m)) {
            $item['Album'] = $m[1];
        }
    }

    if (isset($item['Titel'])) {
        if (strpos($item['Titel'], 'Am Mikrofon: ') !== FALSE)
            return;

        if (strpos($item['Titel'], 'aus: ') === 0)
            $item['Titel'] = substr($item['Titel'], strlen('aus: '));
    }

    foreach ($item as $tag => $value) {
        printf("%s: %s\n", $tag, $value);
    }
    echo("\n");

    return $item;
}

function getSpotifyVariantUri($item, $variant, &$search) {
    $uri = 'https://api.spotify.com/v1/search?type=track&';

    if (in_array('track', $variant) && isset($item['Titel'])) {
        $search .= $item['Titel'];
        $uri .= sprintf("q=%s", urlencode($item['Titel']));
    }

    if (in_array('album', $variant) && isset($item['Album'])) {
        $search .= sprintf(" (%s)", $item['Album']);
        $uri .= sprintf("+album:%s", urlencode($item['Album']));
    }

    if (in_array('interpret', $variant) && isset($item['Interpret'])) {
        $search .= sprintf(" by %s", $item['Interpret']);
        $uri .= sprintf("+artist:%s", urlencode($item['Interpret']));
    }

    return $uri;
}

function searchSpotify($item, &$res) {
    global $client;

    // if ($item['Titel'] !== 'Beautiful day')
    //     return;

    // artist
    // [Interpret] => Jane Jane Monheit
    // [Dirigent] => Edward Shearmur
    // [Solist] => Jane Monheit (voc)
    // [Komponist] => Harold Arlen (1905-1986)
    // [Textdichter] => Edgar "Yip" Harburg (1898-1981)
    // [Ensemble] => Them ;  Morrison, Van (voc,g) ;  Armstrong, Jim (g) ;  Elliott, Ray (org) ;  Henderson, Alan (bg) ;  Wilson, John (dr)

    $variants = [
        ['track', 'album', 'interpret'],
        ['track', 'interpret'],
        ['track', 'album'],
        ['track']
    ];

    foreach ($variants as $variant) {
        $search = '';
        $uri = getSpotifyVariantUri($item, $variant, $search);
        printf("Search: %s\n", $search);

        // printf("%s", $uri);
        $response = $client->get($uri);

        if ($response->getStatusCode() !== 200) {
            printf("\nsearch failed: %d\n%s\n", $response->getStatusCode(), $response->getBody());
            die;
        }

        $json = json_decode($response->getBody());
        // printf(" -> %d\n", $json->tracks->total);

        if ($json->tracks->total) {
            // print_r($json);
            // echo $response->getBody();
            printf("Found: %s (%s) by %s\n",
                $json->tracks->items[0]->name,
                $json->tracks->items[0]->album->name,
                $json->tracks->items[0]->artists[0]->name
            );
            foreach ($json->tracks->items as &$item) {
                unset($item->available_markets);
                unset($item->album->available_markets);
                unset($item->album->images);
                // print_r($item);
            }
            $res = $json;
            break;
        }
    }

    if (!$json->tracks->total)
        printf("Not found\n");

    echo("\n");
    return $json->tracks->total > 0;
}

// Basic directory cache example
$cacheProvider = new FilesystemCache(__DIR__ . '/cache');
// Guzzle will determine an appropriate default handler if `null` is given.
$defaultHandler = null;
// Create a cache handler with a given cache provider and default handler.
$handler = new CacheHandler($cacheProvider, $defaultHandler, [
    'methods' => ['GET'],
    'expire' => CACHE_DURATION,
]);

$client = new Client([
    'handler' => $handler,
    'timeout'  => 10,
]);


$date = new \DateTime("2013-11-16");
$now = (new \DateTime(/*now*/))->getTimestamp();
$items = [];

$op = "search";
// $op = "update";

if ($op == "search") {
    $items = json_decode(file_get_contents(CATALOG_FILE), true);
    $hits = 0;
    $json = null;

    foreach ($items as $item) {
        $res = searchSpotify($item, $json);
        if ($res) {
            $hits++;
            // print_r($json);
        }
    }

    printf("Found %d of %d titles\n", $hits, count($items));
    exit;
}

while ($date->getTimestamp() - $now < 0) {
    $uri = sprintf('http://www.deutschlandfunk.de/playlist-klassik-pop-et-cetera.828.de.html?drpl:date=%s', $date->format("Y-m-d"));
    printf("> fetching %s from %s\n", $date->format("Y-m-d"), $uri);

    $response = $client->get($uri);
    $body = (string) $response->getBody();
    // echo $body;

    $crawler = new Crawler($body);

    foreach ($crawler->filter('ul.playlist li table') as $el) {
        // var_dump($el->nodeName);
        $item = parseItem($el);
        if ($item)
            $items[] = $item;
    }

    $date->add(new DateInterval('P7D'));
}

$uri = 'https://api.spotify.com/v1/search';

file_put_contents(CATALOG_FILE, json_encode($items, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));