<?php

namespace Dlf;

use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\Request;

class SpotifyWrapper extends SpotifyWebAPI
{
	public $session;

	function getSession() {
    	if (!$this->session) {
	        $this->session = new Session(
	            CLIENT_ID,
	            CLIENT_SECRET,
	            REDIRECT_URI_INTERACTIVE
	        );
    	}	

    	return $this->session;	
	}

    function getBasicApiToken() {
        $payload = base64_encode(CLIENT_ID . ':' . CLIENT_SECRET);
        return $payload;
    }

	function getPlaylistByName($name) {
		// playlists
		$playlists = $this->getMyPlaylists();

		// create dlf playlist
		foreach ($playlists->items as $playlist) {
			if ($playlist->name == $name) {
				return $playlist;
			}
		};

		return false;
	}

	function playlistContains($playlist, $itemId) {
		foreach ($playlist->tracks->items as $item) {
			if ($item->track->id == $itemId) {
				return true;
			}
		}

		return false;
	}

	function getUserPlaylist($userId, $playlistId, $options = []) {
		$res = parent::getUserPlaylist($userId, $playlistId, $options);

		$segment = $res->tracks;

        while ($segment->next) {
			$uri = substr($segment->next, strlen(Request::API_URL));

	        $headers = $this->authHeaders();
	        $this->lastResponse = $this->request->api('GET', $uri, [], $headers);
	        $segment = $this->lastResponse['body'];

            // $segment = parent::getUserPlaylist($userId, $playlistId, [
            //     'offset' => (int)$segment->tracks->offset + (int)$segment->tracks->limit,
            //     'limit' => (int)$segment->tracks->limit
            // ]);
            $res->tracks->items = array_merge($res->tracks->items, $segment->items);
        }

        return $res;
    }
}
