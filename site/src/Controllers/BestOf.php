<?php

namespace App\Controllers;

use App\Helpers\TootFilter;
use RTF\Controller;

class BestOf extends Controller {

    const PAGE_SIZE = 30;

    const SORTS = [
        'favs'    => 'favourites_count',
        'boosts'  => 'reblogs_count',
        'replies' => 'replies_count',
        'score'   => '(favourites_count + 2*reblogs_count + replies_count)',
    ];

    const MEDIA = ['all', 'image', 'video', 'audio', 'any'];

    public function list($slug = null) {

        $sort  = $_GET['sort']  ?? 'favs';
        if (!isset(self::SORTS[$sort])) $sort = 'favs';
        $orderBy = self::SORTS[$sort];

        $media = $_GET['media'] ?? 'all';
        if (!in_array($media, self::MEDIA, true)) $media = 'all';

        $from  = $this->parseDate($_GET['from'] ?? null);
        $to    = $this->parseDate($_GET['to']   ?? null);

        $page     = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = self::PAGE_SIZE;
        $offset   = ($page - 1) * $pageSize;

        $movie  = null;
        $filter = null;

        $where  = ["t.visible = 1"];
        $params = [];

        if ($slug !== null) {
            $movie = $this->db->getBySlug("movies", $slug);
            if (!$movie) {
                $this->error(404, ['header' => ['bodyClass' => 'error-404', 'title' => 'Movie not found']]);
                return;
            }

            $start = new \DateTime($movie['start_datetime']);
            $end   = clone $start;
            $end->add(new \DateInterval('PT' . ($movie['duration'] + (int)$this->config("aftershowDuration")) . 'S'));

            $where[] = "t.created_at >= :start AND t.created_at <= :end";
            $params['start'] = $start->format("Y-m-d H:i:s");
            $params['end']   = $end->format("Y-m-d H:i:s");

            $filter = TootFilter::forMovie($this->db, $movie);

        } else {
            if ($from) {
                $where[] = "t.created_at >= :from";
                $params['from'] = $from . " 00:00:00";
            }
            if ($to) {
                $where[] = "t.created_at <= :to";
                $params['to'] = $to . " 23:59:59";
            }
        }

        switch ($media) {
            case 'image': $where[] = "t.has_image = 1"; break;
            case 'video': $where[] = "t.has_video = 1"; break;
            case 'audio': $where[] = "t.has_audio = 1"; break;
            case 'any':   $where[] = "(t.has_image = 1 OR t.has_video = 1 OR t.has_audio = 1)"; break;
        }

        $whereSQL = implode(" AND ", $where);

        // All movies: used both for the dropdown selector (newest first) and, on the
        // overall view, to tag each toot with its owning movie.
        $allMovies = $this->db->fetchAll(
            "SELECT slug, title, start_datetime, duration, secondary_feature FROM movies
             WHERE start_datetime <= NOW() ORDER BY start_datetime DESC"
        );

        // Cache: key by slug + normalized query string. Value holds the heavy stuff
        // (filtered toots + total count); light data (movie, allMovies, pagination)
        // is recomputed every request.
        $cacheKey = $this->cacheKey($slug, $sort, $media, $from, $to, $page);
        $ttl      = $this->cacheTTL($slug, $movie, $allMovies, $from, $to);

        $toots      = null;
        $totalCount = null;

        $cached = $this->db->getByName("cache", $cacheKey);
        if ($cached && (time() - strtotime($cached['created_at'])) < $ttl) {
            $payload = json_decode($cached['value'], true);
            if (is_array($payload) && isset($payload['toots'], $payload['totalCount'])) {
                $toots      = $payload['toots'];
                $totalCount = (int)$payload['totalCount'];
            }
        }

        if ($toots === null) {
            // Total (pre-filter). TootFilter may drop a few, so count is approximate for a single-movie view.
            $totalRow   = $this->db->fetch("SELECT COUNT(*) AS c FROM toots t WHERE $whereSQL", $params);
            $totalCount = (int)($totalRow['c'] ?? 0);

            // Over-fetch a bit when TootFilter applies so filtered-out toots don't thin the page.
            $fetchLimit = $filter && $filter['mode'] !== 'none' ? $pageSize * 2 : $pageSize;

            $rows = $this->db->fetchAll(
                "SELECT t.id, t.data, t.created_at, t.favourites_count, t.reblogs_count, t.replies_count,
                        t.has_image, t.has_video, t.has_audio
                 FROM toots t
                 WHERE $whereSQL
                 ORDER BY $orderBy DESC, t.created_at DESC
                 LIMIT $fetchLimit OFFSET $offset",
                $params
            );

            // For toot → movie assignment, walk oldest-first so the first window match wins.
            $moviesAsc = $slug === null ? array_reverse($allMovies) : null;

            $toots = [];
            $kept  = 0;
            foreach ($rows as $row) {
                $data = json_decode($row['data'], true);

                if ($filter && !TootFilter::matches($data, $filter)) continue;

                $row['parsed']      = $data;
                $row['movie_slug']  = null;
                $row['movie_title'] = null;

                if ($moviesAsc) {
                    $tootTs = strtotime($row['created_at']);
                    foreach ($moviesAsc as $m) {
                        $mStart = strtotime($m['start_datetime']);
                        $mEnd   = $mStart + (int)$m['duration'] + (int)$this->config("aftershowDuration");
                        if ($tootTs >= $mStart && $tootTs <= $mEnd) {
                            $row['movie_slug']  = $m['slug'];
                            $row['movie_title'] = $m['title'];
                            break;
                        }
                    }
                }

                $toots[] = $row;
                $kept++;
                if ($kept >= $pageSize) break;
            }

            $this->db->delete("cache", ["name" => $cacheKey]);
            $this->db->insert("cache", [
                'name'  => $cacheKey,
                'value' => json_encode(['toots' => $toots, 'totalCount' => $totalCount]),
            ]);
        }

        $totalPages = max(1, (int)ceil($totalCount / $pageSize));

        $data = [
            'header' => [
                'bodyClass' => 'page-best-of',
                'title'     => $movie ? 'Best of ' . $movie['title'] : 'Best of #monsterdon',
                'logoSrc'   => '/img/logo-best-of.svg',
                'scopeTitle' => $movie ? $movie['title'] : 'All movies',
                'backgroundImage' => $movie ? 'url(/media/covers/' . $movie['imdb_id'] . '.jpg)' : null,
                'ogImage'   => $movie ? "https://" . $this->config("domain") . '/media/covers/' . $movie['imdb_id'] . '_ogimage.png' : null,
            ],
            'movie'       => $movie,
            'allMovies'   => $allMovies,
            'toots'       => $toots,
            'sort'        => $sort,
            'media'       => $media,
            'from'        => $from,
            'to'          => $to,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'totalCount'  => $totalCount,
        ];

        header("Cache-Control: max-age=3600, public");

        $this->view("best-of/list", $data);
    }

    private function cacheKey($slug, $sort, $media, $from, $to, $page) {
        $parts = [
            'slug'  => $slug ?? 'all',
            'sort'  => $sort,
            'media' => $media,
            'from'  => $from ?? '',
            'to'    => $to   ?? '',
            'page'  => $page,
        ];
        return "best-of:" . ($slug ?? 'all') . ":" . md5(json_encode($parts));
    }

    /**
     * TTL in seconds. Per-movie: based on that movie's age since end. Overall: based on
     * the youngest movie in scope (or all movies if no date range), with a 1-day floor
     * since accuracy matters less on the overall view than keeping it fast.
     */
    private function cacheTTL($slug, $movie, $allMovies, $from, $to) {
        $aftershow = (int)$this->config("aftershowDuration");

        if ($slug !== null && $movie) {
            $age = time() - strtotime($movie['start_datetime']) - (int)$movie['duration'] - $aftershow;
            return $this->ttlForAge($age);
        }

        // Overall: youngest movie whose window overlaps the date range (or youngest overall).
        $fromTs = $from ? strtotime($from . " 00:00:00") : null;
        $toTs   = $to   ? strtotime($to   . " 23:59:59") : null;

        $youngestEnd = null;
        foreach ($allMovies as $m) {
            $mStart = strtotime($m['start_datetime']);
            $mEnd   = $mStart + (int)$m['duration'] + $aftershow;
            if ($fromTs !== null && $mEnd   < $fromTs) continue;
            if ($toTs   !== null && $mStart > $toTs)   continue;
            if ($youngestEnd === null || $mEnd > $youngestEnd) $youngestEnd = $mEnd;
        }

        if ($youngestEnd === null) return 30 * 86400;

        return max(86400, $this->ttlForAge(time() - $youngestEnd));
    }

    private function ttlForAge($ageSeconds) {
        if ($ageSeconds < 12 * 3600)  return 3600;       // < 12h:  1h
        if ($ageSeconds < 7  * 86400) return 6 * 3600;   // < 7d:   6h
        if ($ageSeconds < 30 * 86400) return 86400;      // < 30d:  1d
        return 30 * 86400;                               // >= 30d: 30d
    }

    private function parseDate($s) {
        if (!$s) return null;
        $s = trim($s);
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        return ($d && $d->format('Y-m-d') === $s) ? $s : null;
    }
}
