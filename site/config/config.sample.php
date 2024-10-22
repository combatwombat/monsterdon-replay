<?php

return [
    'db' => [
        'name' => 'name',
        'user' => 'root',
        'pass' => 'root',
        'host' => 'localhost'
    ],
    'auth' => [
        'http' => [
            'users' => [
                [
                    'user' => 'franz',
                    'pass' => 'hunter3'
                ]
            ]
        ],
    ],
    'mastodon' => [
        'instance' => 'https://mastodon.social',
        'hashtag' => 'monsterdon',
        'startDateTime' => '2016-03-16 00:00:00' // Date from which onwards to save toots. Can't be earlier than 2016-03-16, the initial release of Mastodon 🤓
    ],
    'tmdb' => [
        'apiKey' => '1234' // to add movie info
    ],
    'http' => [
        'headers' => [ // extra headers for http requests
            "User-Agent: monsterdon-replay-bot"
        ]
    ],
    'contact' => [
        'email' => 'contact@example.com' # for privacy policy
    ],
    'aftershowDuration' => 60 * 60, // how long to keep playing toots after the movie has ended, in seconds
];

