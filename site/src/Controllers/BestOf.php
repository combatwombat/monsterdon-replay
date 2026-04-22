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

        // Total (pre-filter). TootFilter may drop a few, so count is approximate for a single-movie view.
        $totalRow   = $this->db->fetch("SELECT COUNT(*) AS c FROM toots t WHERE $whereSQL", $params);
        $totalCount = (int)($totalRow['c'] ?? 0);
        $totalPages = max(1, (int)ceil($totalCount / $pageSize));

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

        // All movies: used both for the dropdown selector (newest first) and, on the
        // overall view, to tag each toot with its owning movie.
        $allMovies = $this->db->fetchAll(
            "SELECT slug, title, start_datetime, duration, secondary_feature FROM movies ORDER BY start_datetime DESC"
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

        $data = [
            'header' => [
                'bodyClass' => 'page-best-of',
                'title'     => $movie ? 'Best of ' . $movie['title'] : 'Best of #monsterdon',
                'backLink'  => $movie ? '/' . $movie['slug'] : '/',
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

    private function parseDate($s) {
        if (!$s) return null;
        $s = trim($s);
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        return ($d && $d->format('Y-m-d') === $s) ? $s : null;
    }
}
