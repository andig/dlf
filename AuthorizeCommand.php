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

class AuthorizeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('auth')
            ->setDescription('Authorize dlf app at Spotify')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $session = new Session(
            CLIENT_ID,
            CLIENT_SECRET,
            REDIRECT_URI
        );
        $api = new SpotifyWebAPI();

        $scopes = array(
            'scope' => array(
                'user-read-email',
                'user-library-modify',
                'playlist-modify',
                'playlist-modify-private'
            ),
        );

        $uri = $session->getAuthorizeUrl($scopes);
        printf("%s\n", $uri);

        $client = ClientFactory::getClient();

        $cookies = new FileCookieJar(COOKIE_FILE, true);

        // spotify cookies
        $headers = [];
        if (file_exists(COOKIE_FILE)) {
            $headers['Cookie'] = file_get_contents(COOKIE_FILE);
        }
        $response = $client->get($uri, [
            'cookies' => $cookies,
        ]);
        print_r($response->getHeaders());
        print_r((string)$response->getBody());

        if ($response->hasHeader('Set-Cookie')) {
            $cookies = $response->getHeader('Set-Cookie');
            $cookie = $cookies[0];

            if (($pos = strpos($cookie, ';')) !== false) {
                $cookie = substr($cookie, 0, $pos);
                file_put_contents(COOKIE_FILE, $cookie);
            }
        }

        $loop = \React\EventLoop\Factory::create();
        $socket = new \React\Socket\Server($loop);

        $http = new \React\Http\Server($socket);
        $http->on('request', function ($request, $response) use ($session, $api) {
            $q = $request->getQuery();
            $code = $q['code'];

            $session->requestAccessToken($code);
            $accessToken = $session->getAccessToken();
            $api->setAccessToken($accessToken);

            $response->writeHead(200, array('Content-Type' => 'text/plain'));
            $response->end($accessToken);

            print_r($api->getMyPlaylists());
        });

        $socket->listen(9042);
        $loop->run();
    }
}
