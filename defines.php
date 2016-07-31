<?php

// files

define('TOKEN_FILE', 'bearertoken.txt');

define('CACHE_FILE', 'cache.db3');

// Spotify API usage

define('SCOPES', array(
    'scope' => array(
        'user-read-email',
        'user-library-modify',
        'playlist-modify',
        'playlist-modify-private'
    ),
));

define('ADD_CHUNK_SIZE', 20);

// Spotify API options

define('SEARCH_OPTIONS', array(
	'market' => 'DE'
));

define('GET_PLAYLIST_TRACKS_OPTIONS', array(
	'fields' => 'items.track(!album,artists,available_markets,preview_url,external_urls,external_ids,href,uri),next,limit,offset'
));
