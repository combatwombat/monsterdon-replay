# monsterdon-replay

Web app that records Mastodon toots for the `#monsterdon` hashtag (a weekly
monster-movie watch party) and lets visitors replay a movie's toot timeline in
sync with the movie — so you can relive the party if you missed it.

- Local: https://monsterdon-replay.loc
- Prod:  https://monsterdon-replay.gerlach.dev
- Backstage (movie admin): `/backstage/movies`

Hobby project. Small, fast, fun. No runtime dependencies beyond the bundled
framework. Vanilla JS, SCSS compiled to CSS, full server-rendered pages.

## Stack

- **PHP 8+** on top of **RTF** (Rob's Tiny Framework), a small MVC-ish framework
  vendored at `site/rtf/`. Router + controllers + views + DB layer + a few
  helpers (`Auth`, `Image`, `Localization`, `View`, `Container`, …). No models.
- **MySQL 8+** (schema in `config/schema.sql` on the deployed server's
  `shared/`; see README).
- **TMDB API** for movie metadata and cover art (key in `config.php`).
- **Mastodon** public API (no credentials needed) for fetching toots.
- **Capistrano** for deployment; **Supervisor** (or similar) keeps the worker
  alive. Shared folder holds `config/config.php`, `logs/`, and
  `public/media/{avatars,covers,originals,previews}/`.
- Frontend build: SCSS via `sass`, JS concatenated/minified via `terser`.
  See `site/src/assets/build.sh` and `watch.sh`.

## Layout

- `site/src/app/index.php` — entry point. Wires the container, defines
  **routes** and **CLI commands**.
- `site/src/Controllers/`
  - `Movies.php` — public frontend. `list` (home; paginated via `?page=N`,
    page size = `Movies::PAGE_SIZE`), `show` (single movie replay page),
    `tootsJSON` (`/api/toots/{slug}` — cached JSON of toots for replay),
    `subtitles` (`.ass` file download).
  - `BackstageMovies.php` — authenticated CRUD at `/backstage/movies`.
- `site/src/Helpers/`
  - `TMDB.php` — TMDB wrapper; also downloads/processes cover images and
    generates per-movie og:image.
  - `Subtitles.php` — builds `.ass` subtitle files so toots can be overlaid on
    the movie in VLC/IINA.
  - `ViewHelper.php`
- `site/src/Workers/TootsWorker.php` — the background worker. Saves new toots,
  periodically catches up on older ones, occasionally re-saves everything (to
  catch edits/deletions), and rebuilds the per-movie cache.
- `site/src/views/`
  - `movies/{list,show}.php` — frontend pages.
  - `backstage/movies/` — admin pages.
  - `layouts/default.php`, `parts/{header,footer,html-header}.php`,
    `about.php`, `privacy.php`, `404.php`.
- `site/src/assets/`
  - `scss/` — `main.scss` + `pages/`, `parts/`, `includes/`. Compiled to
    `site/public/css/`.
  - `js/main.js`, `js/web-components/x-timeline.js`, `js/libs/helper.js`.
    Concatenated to `site/public/js/main.js` / `main.min.js`.
- `site/rtf/src/RTF/` — the framework. Don't treat as a dependency — edit in
  place if needed.
- `site/public/` — web root. `index.php` is the front controller; `media/` is
  user-generated (avatars, covers, toot media originals + previews).
- `site/logs/default.log` — debug log (truncated to 100k lines). On deployed
  boxes this lives under `site/shared/logs/`.
- `config/deploy.rb` — Capistrano config. `config/deploy/` has stages.

## Data model (key idea)

Toots are **not** foreign-keyed to movies. A movie has `start_datetime` and
`end_datetime` (= `start_datetime + duration + config.aftershowDuration`); toots
whose `created_at` falls in that window belong to that movie. The per-movie
JSON payload and `toot_count` are precomputed into a `cache` table for speed.
Editing a movie, or the worker saving/updating a toot in its window, invalidates
the cache.

## Double features

Each `#monsterdon` week usually has a main movie plus an unofficial "encore"
that starts right after. Encore toots still carry `#monsterdon` but typically
add a secondary tag like `#wrongfrogs`, `#monsterdondoublefeature`, or
`#monstermiru`. The `movies` table has two columns to separate them:

- `secondary_feature TINYINT(1)` — flag marking encores.
- `filter_tags VARCHAR(500)` — comma-separated hashtag names (no `#`, lowercase).

Filter logic lives in `site/src/Helpers/TootFilter.php` and is applied in
`Movies::tootsJSON` and `Movies::subtitles`:

- Secondary feature → include only toots whose Mastodon `tags[].name` matches
  at least one of its own `filter_tags`.
- Main feature → if the *closest next movie by start_datetime* is a secondary
  feature, **exclude** toots carrying any of that next movie's `filter_tags`
  (they belong to the encore). Otherwise no filtering.
- Empty `filter_tags` → no filter applied (degenerate case, movie shows all
  toots in its window).

Cache invalidation: editing a movie clears its own toot-cache **and** the
preceding movie's cache (because the preceding main's exclusion filter depends
on this movie's flag and tags). Backstage also recomputes `toot_count` through
the same filter.

On the home list, secondary features are **hidden by default**. A styled
switch ("Include Double Features") above the list toggles them in; the
preference is stored in the `include_secondary_features` cookie. The total
movie count (respecting the filter) is shown on the right side of that row.
Secondary-feature entries also get an `is-secondary-feature` class on both
the list `<li>` and the show-page `<div class="movie">`.

## CLI

Run from the repo root (or with the given paths):

- `php site/public/index.php save_toots` — main worker loop (newest first,
  periodic catchup and resave).
- `php site/public/index.php save_toots -first catchup` — catch up on older
  toots first.
- `php site/public/index.php save_toots -first resave` — resave everything
  first.
- `php site/public/index.php save_toot_media` — re-pull media for existing
  toots.
- `php site/public/index.php rebuild_movie_cache` — rebuild all movie caches
  and `movies.toot_count`.

Stop the Supervisor worker before running these manually in prod.

Extra routes worth knowing: `/stats/authors` (auth'd), `/stats/movies`,
`/export-movies/{csv|json}`, `/{slug}/subtitles`.

## Frontend build

```
cd site/src/assets
./build.sh          # sass + js
./build.sh sass     # sass only
./build.sh js       # js only
./watch.sh          # rebuild on change (needs fswatch)
```

Requires `sass`, `terser`, `fswatch` on PATH.

## Conventions / gotchas

- Slugs `about` and `privacy` are reserved (static pages share the `/{slug}`
  route space).
- Times in the DB are UTC; `config.timezone` controls display.
- Backstage is protected by HTTP basic auth via `RTF\Auth` (credentials in
  `config.php`).
- No framework/runtime deps beyond SASS + terser for the build. Keep it that
  way unless there's a good reason.

## TODO (next session)

_(nothing queued)_
