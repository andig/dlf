<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Helper\ProgressBar;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\SQLite3Cache;

class SearchCommand extends Command
{
    protected $addCache;
    protected $userId;

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
        if (in_array('track', $variant) && isset($item['Titel'])) {
            $title = preg_replace('/[,.:;]/', '', $item['Titel']);
            $search .= $title;
            $uri = $title;
        }

        if (in_array('album', $variant)) {
            if (!isset($item['Album']))
                return FALSE;

            $search .= sprintf(" (%s)", $item['Album']);
            $uri .= sprintf(" album:%s", $item['Album']);
        }

        if (in_array('interpret', $variant)) {
            if (!isset($item['Interpret']))
                return FALSE;

            $search .= sprintf(" by %s", $item['Interpret']);
            $uri .= sprintf(" artist:%s", $item['Interpret']);
        }

        if (in_array('interpret2', $variant)) {
            if (!isset($item['Interpret']))
                return FALSE;

            if (!preg_match('/^(\w+\s+)\1(\w+)$/', $item['Interpret'], $m))
                return FALSE;

            $interpret = sprintf("%s%s", $m[1], $m[2]);

            $search .= sprintf(" by %s", $interpret);
            $uri .= sprintf(" artist:%s", $interpret);
        }

        if (in_array('solist', $variant)) {
            if (!isset($item['Solist']))
                return FALSE;

            $q = $this->cleanInterpret($item['Solist']);

            $search .= sprintf(" by %s", $q);
            $uri .= sprintf(" artist:%s", $q);
        }

        if (in_array('ensemble', $variant)) {
            if (!isset($item['Ensemble']))
                return FALSE;

            $q = $this->cleanInterpret($item['Ensemble']);

            $search .= sprintf(" by %s", $q);
            $uri .= sprintf(" artist:%s", $q);
        }

        if (in_array('composer', $variant)) {
            if (!isset($item['Komponist']))
                return FALSE;

            $q = $this->cleanInterpret($item['Komponist']);

            $search .= sprintf(" by %s", $q);
            $uri .= sprintf(" artist:%s", $q);
        }

        return $uri;
    }

    function searchSpotify($item, &$res) {
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
            ['track', 'album', 'composer'],

            ['track', 'interpret'],
            ['track', 'interpret2'],
            ['track', 'solist'],
            ['track', 'ensemble'],
            ['track', 'composer'],

            ['track', 'album'],
            ['track']
        ];

        $buf = "";

        foreach ($variants as $variant) {
            $search = '';
            $q = $this->getSpotifyVariantUri($item, $variant, $search);
            // early exit if not substituted
            if ($q === FALSE)
                continue;

            $buf .= sprintf("Search: %s\n", $search);
            $buf .= sprintf("%s\n", $q);

            $json = $this->api->search($q, ['track']);

            if ($json->tracks->total) {
                // print_r($json);
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

    private function findOrCreatePlaylist($playlistName)
    {
        if (!($playlist = $this->api->getPlaylistByName($playlistName))) {
            $playlist = $this->api->createUserPlaylist($this->userId, ['name' => $playlistName]);
        }

        return $playlist->id;
    }

    private function addAndApplyBacklog($itemId = null) {
        if ($itemId)
            $this->addCache[] = $itemId;

        if (count($this->addCache) >= ADD_CHUNK_SIZE) {
            if (!$this->api->addUserPlaylistTracks($this->userId, $this->playlistId, $this->addCache)) {
                print_r($this->api->getLastResponse());
                die;
            }

            $this->addCache = [];
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $addedIdsCache = new ArrayCache();
        $spotifyIdCache = new SQLite3Cache(new \SQLite3(CACHE_FILE), 'hits');

        $this->api = new SpotifyWrapper(new SpotifyGuzzleAdapter(ClientFactory::getClient()));

        $playlistName = $input->getArgument('playlist');
        $items = json_decode(file_get_contents($playlistName . '.json'), true);

        if ($input->getOption('save') || $input->getOption('auth')) {
            $accessToken = file_get_contents(TOKEN_FILE);
            $this->api->setAccessToken($accessToken);
        }

        if ($input->getOption('save')) {
            $this->userId = $this->api->me()->id;
            $this->playlistId = $this->findOrCreatePlaylist($playlistName);

            // full playlist with tracks
            $playlist = $this->api->getUserPlaylistAllTracks($this->userId, $this->playlistId, GET_PLAYLIST_TRACKS_OPTIONS);
            printf("Playlist size: %d\n", count($playlist->items));
        }

        $searchFailures = [];
        $dupes = $hits = 0;

        $progress = null;
        if (count($items) && ($output->getVerbosity() < OutputInterface::VERBOSITY_VERY_VERBOSE)) {        
            $progress = new ProgressBar($output, count($items));
            $progress->setFormat('very_verbose');
            $progress->start();
        }

        foreach ($items as $item) {
            // already searched and found on spotify?
            $hash = serialize($item);
            $itemId = $spotifyIdCache->fetch($hash);

            // no hit - search spotify
            if (!$itemId) {
                $json = null;
                if ($this->searchSpotify($item, $json)) {
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $this->printItem($item);
                        printf(" (%d matches)\n", count($json->tracks->items));
                        $this->printMatch($json);
                    }

                    $itemId = $json->tracks->items[0]->id;
                }
            }

            // hit from cache or spotify search
            if ($itemId) {
                $hits++;

                if ($addedIdsCache->contains($itemId)) {
                    $dupes++;
                }
                elseif ($input->getOption('save')) {
                    if (!$this->api->playlistContains($playlist, $itemId)) {
                        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
                            printf("\nAdding %s\n", $itemId);

                        $this->addAndApplyBacklog($itemId);

                        // store spotify id
                        $spotifyIdCache->save($hash, $itemId);
                    }
                }

                $addedIdsCache->save($itemId, $itemId);
            }
            else {
                $searchFailures[] = $item;
            }

            if ($progress) $progress->advance();
        }

        $this->addAndApplyBacklog();

        if ($progress) $progress->finish();

        file_put_contents($playlistName . '.fail.json', json_encode($searchFailures, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        printf("\nFound %d of %d titles (%.1f%%)\n", $hits - $dupes, count($items), 100*$hits/(count($items) - $dupes));
    }
}
