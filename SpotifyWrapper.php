<?php

namespace Dlf;

use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;

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

    function getApiToken() {
    	$this->getSession();

        if ($this->session->requestCredentialsToken()) {
            return $this->session->getAccessToken();
        }
        
        return false;
    }

	function getPlaylistByName($name) {
		// playlists
		$playlists = $this->getMyPlaylists();

		// create dlf playlist
		$playlist = array_reduce($playlists->items, function($carry, $item) use ($name) {
			if ($item->name == $name) {
				return $item;
			}
			return $carry;
		});

		return $playlist;
	}

	function playlistContains($playlist, $itemId) {
		$items = array_reduce($playlist->tracks->items, function($carry, $item) use ($itemId) {
			if ($item->track->id == $itemId) {
				return $item;
			}
			return $carry;
		});

		return $items !== null;
	}
}
