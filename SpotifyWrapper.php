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
		$root = (isset($playlist->tracks)) ? $playlist->tracks : $playlist;
		
		foreach ($root->items as $item) {
			if ($item->track->id == $itemId) {
				return true;
			}
		}

		return false;
	}

	function getUserPlaylistAllTracks($userId, $playlistId, $options = []) {
		$playlist = $segment = $this->getUserPlaylistTracks($userId, $playlistId, $options);

        while ($segment->next) {
            $segment = $this->getUserPlaylistTracks($userId, $playlistId, array_merge($options, [
                'offset' => (int)$segment->offset + (int)$segment->limit,
                'limit' => (int)$segment->limit
            ]));
            $playlist->items = array_merge($playlist->items, $segment->items);
        }

        return $playlist;
    }
}
