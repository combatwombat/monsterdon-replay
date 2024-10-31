<?php

return [
    "domain" => "monsterdon-replay.loc",
    "defaultLocale" => "en_US",
    "timezone" => "UTC",
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

        // Date from which onwards to save toots. Can't be earlier than 2016-03-16, the initial release of Mastodon 🤓
        'oldestTootDateTime' => '2016-03-16 00:00:00',

        // how long to keep invisible toots in the database, in seconds (to delete them after they have been deleted from mastodon and repeatedly not found there)
        'keepInvisibleTootsForSeconds' => 60 * 60 * 24 * 7,
    ],
    'apiKeys' => [
        'tmdb' => '1234',
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

