<?php

return [
    'db' => [
        'name' => 'name',
        'user' => 'root',
        'pass' => 'root',
        'host' => 'localhost'
    ],
    'auth' => [
        'user' => 'bob',
        'pass' => 'hunter3'
    ],
    'mastodon' => [
        'instance' => 'https://mastodon.social',
        'hashtag' => 'monsterdon',
        'startDateTime' => '2016-03-16 00:00:00' // Date from which onwards to save toots. Can't be earlier than 2016-03-16, the initial release of Mastodon 🤓
    ],
    'tmdb' => [
        'apiKey' => 'd119b7f140d7a67721d4d2665da62f43' // to add movie info
    ],
    'http' => [
        'headers' => [ // extra headers for http requests
            "User-Agent: monsterdon-replay-bot"
        ]
    ]
];

