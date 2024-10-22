<p align="center">
  <img src="site/public/img/logo.svg?raw=true" width="300"/>
</p>

Records and replays Mastodon toots for a specific hashtag, to replay toots for movie watch parties.

## Hosting

This is work-in-progress and some manual intervention might be required. But, if you want to host it on your server, for #monsterdon or other Mastodon hashtags:

### Requirements

- PHP 8+ with gd
- MySQL 8+
- A way to keep a worker script running. I use Supervisor.
- A way to deploy it to your server. I use Capistrano.

### Setup
- Fork the repository. It's too tied to #monsterdon and myself for now.
- Set up your server, so that I can be deployed to with Capistrano and can keep a worker script alive, with Supervisor, systemd for example.
- Have some space on your server. The media files for ~110.000 toots take about 6 GB.
- Create shared/ folder with
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

example.com/backstage/movies contains the backend to add and edit movies. To add, enter the Start Datetime (UTC time for when watching/tooting for the movie started) and the IMDb id. The name, runtime etc. gets pulled from the TMDB API via the IMDb id. Change the og:image cover offset to nudge the cover image up (higher value) or down (lower value) for the og:image used for social sharing. [Example](https://monsterdon-replay.gerlach.dev/media/covers/tt0065569_ogimage.png).

### CLI

There are some CLI commands. Stop your worker beforehand:

- `php site/public/index.php save_toots` - Save newest toots and their media until it reaches an existing one. Good to initially fill the toots db, then check periodically for new ones.
- `php site/public/index.php save_toots -catchup 100` - Save toots and their media from now till 100 days ago. Don't stop on existing toots. Good to catch some stragglers, or if you change your config.mastodon.instance to fill in some toots from anoher instance.
- `php site/public/index.php save_toot_media` - Go through all toots, save media if it doesn't exist yet. Good if the initial import missed some media files.
- `php site/public/index.php rebuild_movie_cache` - Delete and rebuild toot-cache for all movies. Also updates toot_count. The cache of a movie also gets deleted if it's edited in the backend.

Check site/logs/default.log (or site/shared/logs/default.log if deployed with Capistrano) for various debug output. The file is truncated to 100k lines.


## Development

This is a hobby project, with a focus on small, fast and fun code. So it might not be your cup of tea, nor does it use frameworks or libraries, other than my own small ones. 

Files:

- `site/src/app/index.php` - The starting point. Contains the routes and CLI scripts.
- `site/src/Controllers/*` - Backend and frontend controllers to show and edit movies and return toots for a movie.
- `site/src/views/*` - Various views/templates for the controller actions. Contains about and privacy page.
- `site/src/Helpers/TMDB.php` - TMDB API wrapper. Also saves images and generated og:image for each cover.
- `site/src/Workers/SaveToots.php` - background worker that saves toots and media
- `site/src/assets/*` - Contains JS and SCSS files, to build JS and CSS from.


### Toots and movies

Toots are not directly linked to movies in the DB. Instead, a movie has a start_datetime and a end_datetime (calculated from start_datetime + duration + config.aftershowDuration). All toots with created_at in between those times belong to the movie.

### Caching

To keep things snappy, the JSON list of toots for a movie (via /api/toots/{slug}) is saved in the cache table. The toot_count of a movie is also pre-calculated. Editing a movie refreshes the cache.

### Building the frontend

To keep things simple, there is no gulp, grunt, vite, webpack, rollup, parcel, esbuild or bun. Just two bash scripts to watch and build the JS and CSS. They need `terser`, `fswatch` and `sass` installed. Thus, this needs some Unix environment to work.

Call `watch.sh`, edit files, they get built with `build.sh`. 
Edit and restart `build.sh` if you add a JS file. But don't add a JS file, it's nice and small right now :D 

## Licensing

This project is primarily licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

However, the [Remix Icons](https://remixicon.com/) in `site/public/img/icons` are licensed under the Apache License, Version 2.0.
The full text of this license can be found in `site/public/img/icons/LICENSE`.
