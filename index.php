<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

use GuzzleHttp\Cookie\FileCookieJar;

require_once('./vendor/autoload.php');
require_once('credentials.php');
require_once('defines.php');
require_once('spotifyfunctions.php');


$api = new SpotifyWrapper();

if (isset($_GET['action']) && ($action = $_GET['action'])) {
    if ($action == 'start') {
        echo $api->getSession()->getAuthorizeUrl(SCOPES);
    }
    elseif ($action == 'authorize') {
        header('Location: ' . $api->getSession()->getAuthorizeUrl(SCOPES));
    }
}
elseif (isset($_GET['code']) && ($code = $_GET['code'])) {
    $api->getSession()->requestAccessToken($code);
    $accessToken = $api->getSession()->getAccessToken();

    header('Location: ?token=' . $accessToken);
}
elseif (isset($_GET['token']) && ($accessToken = $_GET['token'])) {
    file_put_contents(TOKEN_FILE, $accessToken);
    $api->setAccessToken($accessToken);

    printf("<h2>Authorized</h2><p>Token: %s</p>", $accessToken);

    // user
    $userId = $api->me()->id;
    // print_r($api->me());

    // playlists
    // $playlists = $api->getMyPlaylists();
    // array_walk($playlists->items, function($pl) {
    //     printf("%s\n", $pl->name);
    // });

    echo("<pre>");

    // create dlf playlist
    $dlfPlaylist = $api->getPlaylistByName('klassik-pop-et-cetera');
    print_r($dlfPlaylist);

    exit;
}
else {
?>

<p>
<form method="get" action="">
    <input type="hidden" name="action" value="authorize" />
    <input type="submit" value="Authorize" />
</form>

<?php
}
