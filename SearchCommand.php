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
            ->addArgument(
                'playlist',
                InputArgument::OPTIONAL,
                'Playlist name',
                'klassik-pop-et-cetera'
            )
            ->addOption(
               'auth',
               null,
               InputOption::VALUE_NONE,
               'Use api autorization'
            )            
            ->addOption(
               'save',
               null,
               InputOption::VALUE_NONE,
               'Save search results to playlist'
            )
        ;
    }

    // function getSpotifyApiToken() {
    //     $session = new Session(
    //         CLIENT_ID,
    //         CLIENT_SECRET,
    //         REDIRECT_URI_INTERACTIVE
    //     );

    //     if ($session->requestCredentialsToken()) {
    //         return $session->getAccessToken();
    //     }
        
    //     return false;
    // }

    function cleanInterpret($q) {
        if (($pos = strpos($q, ';')) !== false) {
            $q = substr($q, 0, $pos);
        }

        if (($pos = strpos($q, '(')) !== false) {
            $q = substr($q, 0, $pos);
        }

        return trim($q);
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

        if (in_array('solist', $variant)) {
            if (!isset($item['Solist']))
                return FALSE;

            $q = $this->cleanInterpret($item['Solist']);

            $search .= sprintf(" by %s", $q);
            $uri .= sprintf("+artist:%s", urlencode($q));
        }

        if (in_array('ensemble', $variant)) {
            if (!isset($item['Ensemble']))
                return FALSE;

            $q = $this->cleanInterpret($item['Ensemble']);

            $search .= sprintf(" by %s", $q);
            $uri .= sprintf("+artist:%s", urlencode($q));
        }

        return $uri;
    }

    function searchSpotify($item, &$res) {
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
            ['track', 'album', 'solist'],
            ['track', 'album', 'ensemble'],
            ['track', 'interpret'],
            ['track', 'interpret2'],
            ['track', 'solist'],
            ['track', 'ensemble'],
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
            // echo $buf;
            // printf("Not found\n");
        }

        return $json->tracks->total > 0;
    }

    protected function printItem($item) 
    {   
        $search = '';
        $this->getSpotifyVariantUri($item, ['track', 'album', 'interpret'], $search);

        printf("\n--> %s", $search);
    }
    
    protected function printMatch($json)
    {
        foreach ($json->tracks->items as $item) {
            printf("%s %s", $item->id, $item->name);
            if ($item->album) {
                printf(" (%s)", $item->album->name);
            }
            if (count($item->artists)) {
                printf(" by %s", $item->artists[0]->name);
            }
            printf("\n");
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $api = new SpotifyWrapper();

        $clientOptions = [];
        if ($input->getOption('auth')) {
            if ($token = $api->getApiToken()) {
                $clientOptions['headers']['Authorization'] = 'Bearer ' . $token;
            }
        }
        $this->client = ClientFactory::getClient($clientOptions);

        $playlistName = $input->getArgument('playlist');

        if ($input->getOption('save')) {
            $accessToken = file_get_contents(TOKEN_FILE);
            $api->setAccessToken($accessToken);

            $userId = $api->me()->id;

            // playlist header
            $playlist = $api->getPlaylistByName($playlistName);

            if (!$playlist) {
                $playlist = $api->createUserPlaylist($userId, ['name' => $playlistName]);
            }

            // full playlist with tracks
            $playlist = $api->getUserPlaylist($userId, $playlist->id);
        }

        $items = json_decode(file_get_contents($playlistName . '.json'), true);
        $hits = 0;

        foreach ($items as $item) {
            $json = null;
            $res = $this->searchSpotify($item, $json);
            if ($res) {
                $hits++;

                $this->printItem($item);

                printf(" (%d matches)\n", count($json->tracks->items));
                $this->printMatch($json);

                if ($input->getOption('save')) {
                    $itemId = $json->tracks->items[0]->id;

                    if (!$api->playlistContains($playlist, $itemId)) {                    
                        if (!$api->addUserPlaylistTracks($userId, $playlist->id, $itemId)) {
                            print_r($api->getLastResponse());
                            die;
                        }
                    }
                }
            }
        }

        printf("Found %d of %d titles (%.1f%%)\n", $hits, count($items), 100*$hits/count($items));
    }
}
