<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SearchCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('search')
            ->setDescription('Search DLF playlist entries at spotify')
        ;
    }

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


    function getSpotifyVariantUri($item, $variant, &$search) {
        $uri = 'https://api.spotify.com/v1/search?type=track&';

        if (in_array('track', $variant) && isset($item['Titel'])) {
            $title = preg_replace('/[,.:;]/', '', $item['Titel']);
            $search .= $title;
            $uri .= sprintf("q=%s", urlencode($title));
        }

        if (in_array('album', $variant)) {
            if (!isset($item['Album']))
                return FALSE;

            $search .= sprintf(" (%s)", $item['Album']);
            $uri .= sprintf("+album:%s", urlencode($item['Album']));
        }

        if (in_array('interpret', $variant)) {
            if (!isset($item['Interpret']))
                return FALSE;

            $search .= sprintf(" by %s", $item['Interpret']);
            $uri .= sprintf("+artist:%s", urlencode($item['Interpret']));
        }

        if (in_array('interpret2', $variant)) {
            if (!isset($item['Interpret']))
                return FALSE;

            if (!preg_match('/^(\w+\s+)\1(\w+)$/', $item['Interpret'], $m))
                return FALSE;

            $interpret = sprintf("%s%s", $m[1], $m[2]);
            $search .= sprintf(" by %s", $interpret);
            $uri .= sprintf("+artist:%s", urlencode($interpret));
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
            ['track', 'album', 'interpret2'],
            ['track', 'interpret'],
            ['track', 'interpret2'],
            ['track', 'album'],
            ['track']
        ];

        $buf = "";

        foreach ($variants as $variant) {
            $search = '';
            $uri = $this->getSpotifyVariantUri($item, $variant, $search);
            // early exit if not substituted
            if ($uri === FALSE)
                continue;

            $buf .= sprintf("Search: %s\n", $search);
            $buf .= sprintf("%s\n", $uri);

            $response = $this->client->get($uri);

            if ($response->getStatusCode() !== 200) {
                printf("\nsearch failed: %d\n%s\n", $response->getStatusCode(), $response->getBody());
                die;
            }

            $json = json_decode($response->getBody());
            // printf(" -> %d\n", $json->tracks->total);

            if ($json->tracks->total) {
                // print_r($json);
                // echo $response->getBody();
                $buf .= sprintf("Found: %s (%s) by %s\n",
                    $json->tracks->items[0]->name,
                    $json->tracks->items[0]->album->name,
                    $json->tracks->items[0]->artists[0]->name
                );
                // print_r($item);
                foreach ($json->tracks->items as &$item) {
                    unset($item->available_markets);
                    unset($item->album->available_markets);
                    unset($item->album->images);
                }
                $res = $json;
                break;
            }
        }

        // echo $buf;
        if (!$json->tracks->total) {
            echo $buf;
            printf("Not found\n");
        }

        return $json->tracks->total > 0;
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client = ClientFactory::getClient();
        
        $items = json_decode(file_get_contents(CATALOG_FILE), true);
        $hits = 0;

        foreach ($items as $item) {
            $json = null;
            $res = $this->searchSpotify($item, $json);
            if ($res) {
                $hits++;
                // print_r($json);
            }
        }

        printf("Found %d of %d titles\n", $hits, count($items));
    }
}
