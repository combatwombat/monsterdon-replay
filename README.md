<p align="center">
  <img style="display: inline-block; margin: 100px 0 -50px 0;" src="https://monsterdon-replay.gerlach.dev/img/logo.svg" width="300"/>
</p>

Records Mastodon toots for a specific hashtag, to replay them for movie watch parties.

## Hosting

This is work-in-progress and some manual intervention might be required. But, if you want to host it on your server, for #monsterdon or other Mastodon hashtags:

### Requirements

- PHP 8+ with gd
- MySQL 8+
- A way to keep a worker script running. I use Supervisor.
- A way to deploy it to your server. I use Capistrano.
- An API key from [tmdb.org](https://tmdb.org)
- A Mastodon instance whose API you can use without credentials

### Setup
- Set up your server, so that I can be deployed to with Capistrano and can keep a worker script alive, with Supervisor, systemd for example. Alternatively deploy in another way. 
- Have some space on your server. The media files for ~110.000 toots take about 6 GB.
- If deploying with Capistrano, create shared/ folder with
  - config/config.php 
  - logs/
  - public/media/
    - avatars/
    - covers/
    - originals/
    - previews/
- Fill config/config.php with your details, including domain name, hashtag, backstage credentials and tmdb.org api key
- Create database, fill with config/schema.sql
- Test worker: `php site/public/index.php save_toot`
    - This should save all toots and their media for the given hashtag, down to the beginning of Mastodon (or config.mastodon.oldestTootDateTime). Once that is done, it periodically checks for new toots.

## Usage

### Backstage

example.com/backstage/movies contains the backend to add and edit movies. To add, enter the Start Datetime (UTC time for when watching/tooting for the movie started) and the IMDb id. The name, runtime etc. gets pulled from the TMDB API via the IMDb id. 

<img src="https://monsterdon-replay.gerlach.dev/img/screenshot-backstage.png" width="100%"/>

Change the og:image cover offset to nudge the cover image up (higher value) or down (lower value) for the og:image used for social sharing. Example:

<img src="https://monsterdon-replay.gerlach.dev/img/screenshot-ogimage.png" width="600"/>



### CLI

There are some CLI commands. Stop your worker beforehand:



- `php site/public/index.php save_toots` - Save newest toots and their media until it reaches an existing one. Good to initially fill the toots db, then check periodically for new ones. Occasionally catches up on older toots. Seldom re-fetches all toots, possibly deleting ones that have not been found on Mastodon for a while.
- `php site/public/index.php save_toots -first catchup` - Starts with catching up on older toots, then goes on as usual.
- `php site/public/index.php save_toot_media -first resave` - Starts with re-saving all toots, then goes on as usual.
- `php site/public/index.php rebuild_movie_cache` - Delete and rebuild cache for all movies. Also updates movies.toot_count. This is not often needed, since the cache of a movie also gets deleted if it's edited in the backend, or if the save_toots worker adds a toot for that movie.
- `php site/public/index.php save_toot_media` - Goes through all toots and saves their media

Check `site/logs/default.log` (or `site/shared/logs/default.log` if deployed with Capistrano) for various debug output. The file is truncated to 100k lines.


## Development

This is a hobby project, with a focus on small, fast and fun code. No dependencies, other than SASS and my minimal framework. 

RTF (Rob's Tiny Framework) is a small [M]VC framework with a router, controllers, views and some convenience methods. No models for now, only a DB abstraction layer. If the app grows, we could use alpine.js and the component capabilities of RTF with HTMX (think poor mans Livewire). But for now, it's full pages and Vanilla JS. Also, don't use this framework for your own stuff, it's not really supported or full-featured. Use Laravel or Symfony instead.

Files:

- `site/src/app/index.php` - The starting point. Contains the routes and CLI scripts.
- `site/src/Controllers/*` - Backend and frontend controllers to show and edit movies and return toots for a movie.
- `site/src/views/*` - Various views/templates for the controller actions. Contains about and privacy page.
- `site/src/Helpers/TMDB.php` - TMDB API wrapper. Also saves images and generates og:image for each cover.
- `site/src/Workers/TootsWorker.php` - background worker that saves toots and media
- `site/src/assets/*` - Contains JS and SCSS files, to build JS and CSS from.

### Building the frontend

`build.sh` builds CSS from SCSS and concatenates and minifies the JS. 

`watch.sh` watches for file changes and calls `build.sh`. 

They need `terser`, `fswatch` and `sass` installed. Thus, this needs some Unix-like environment to work.

Edit `build.sh` and restart the watcher if you want to include different JS files.

### Toots and movies

Toots are not directly linked to movies in the DB. Instead, a movie has a start_datetime and an end_datetime (calculated from start_datetime + duration + config.aftershowDuration). All toots with created_at in between those times belong to the movie.

### Caching

To keep things snappy, the JSON list of toots for a movie (via /api/toots/{slug}) is saved in the cache table. The toot_count of a movie is also pre-calculated. Editing a movie refreshes the cache.

## Licensing

This project is primarily licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

However, the [Remix Icons](https://remixicon.com/) in `site/public/img/icons` are licensed under the Apache License, Version 2.0.
The full text of this license can be found in `site/public/img/icons/LICENSE`.
