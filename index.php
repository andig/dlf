<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use SpotifyWebAPI\SpotifyWebAPIException;

require_once('./vendor/autoload.php');
require_once('credentials.php');
require_once('defines.php');


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

    $expires = $api->getSession()->getTokenExpiration();

    header('Location: ?token=' . $accessToken . '&expires=' . $expires);
}
elseif (isset($_GET['token']) && ($accessToken = $_GET['token'])) {
    file_put_contents(TOKEN_FILE, $accessToken);
    $api->setAccessToken($accessToken);

    // user
    try {
        $me = $api->me();
        $userId = $me->id;

        printf("<h2>Authorized</h2><p>Token: %s</p>", $accessToken);
        // print_r($api->me());

        if (isset($_GET['expires'])) {
            $tokenExpiry = \DateTime::createFromFormat('U', (int)$_GET['expires']);
            $tokenExpiry->setTimezone(new \DateTimeZone('Europe/Berlin'));
            printf('<p>Expires: %s</p>', $tokenExpiry->format('H:i:s d.m.Y'));
        }

        printf('<p>User id: %s</p><p><a href="%s">User profile</a></p>', $me->id, $me->external_urls->spotify);
?>
<p>
<form method="get" action="">
    <input type="submit" value="Restart" />
</form>
<?php
    }
    catch (SpotifyWebAPIException $e) {
        if (preg_match('/token expired/i', $e->getMessage())) {
            header('Location: .');
            exit;
        }
    }

    // create dlf playlist
    // print_r($api->getUserPlaylists($userId)->items);
    echo("<ul>");
    foreach ($api->getUserPlaylists($userId)->items as $playlist) {
        printf('<li><a href="%s">%s</a></li>', $playlist->external_urls->spotify, $playlist->name);
    }
    echo("</ul>");

    echo("<pre>");

    exit;
}
else {
?>

<h1>dlf</h1>
<p>playlist to spotify converter</p>
<p>Visit us at <a href="https://github.com/andig/dlf">GitHub</a>
<form method="get" action="">
    <input type="hidden" name="action" value="authorize" />
    <input type="submit" value="Authorize" />
</form>

<?php
}
