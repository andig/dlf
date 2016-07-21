<?php

define('TOKEN_FILE', 'bearertoken.txt');

define('CACHE_FILE', 'cache.db3');

define('SCOPES', array(
    'scope' => array(
        'user-read-email',
        'user-library-modify',
        'playlist-modify',
        'playlist-modify-private'
    ),
));

define('GET_PLAYLIST_TRACKS_OPTIONS', array(
	'fields' => 'items.track(!album,artists,available_markets,preview_url,external_urls,external_ids,href,uri),next,limit,offset'
));

define('ADD_CHUNK_SIZE', 20);
