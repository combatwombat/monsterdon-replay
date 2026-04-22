<?php

namespace App\Helpers;

/**
 * Filters toots for a movie based on the #monsterdon double-feature rules:
 *
 * - If the movie is a secondary_feature: include only toots whose Mastodon
 *   tags contain at least one of the movie's own filter_tags.
 * - If the movie is a main feature: find the closest next movie by
 *   start_datetime. If that next movie is a secondary_feature, exclude toots
 *   that carry any of its filter_tags (they belong to the double feature).
 * - Otherwise: no filtering.
 *
 * filter_tags is a comma-separated string; matching is case-insensitive against
 * Mastodon's toot.tags[].name (which Mastodon stores lowercased anyway).
 */
class TootFilter {

    /**
     * Parse a comma-separated filter_tags string into a lowercased, trimmed list.
     */
    public static function parseTags($tagsString) {
        if (empty($tagsString)) return [];
        $tags = array_map('trim', explode(',', strtolower($tagsString)));
        return array_values(array_filter($tags, fn($t) => $t !== ''));
    }

    /**
     * Decide the filter mode for a given movie.
     * @param $db RTF\DB
     * @param $movie array movie row
     * @return array ['mode' => 'include'|'exclude'|'none', 'tags' => string[]]
     */
    public static function forMovie($db, $movie) {

        if (!empty($movie['secondary_feature'])) {
            return [
                'mode' => 'include',
                'tags' => self::parseTags($movie['filter_tags'] ?? '')
            ];
        }

        $next = $db->fetch(
            "SELECT secondary_feature, filter_tags FROM movies WHERE start_datetime > :start ORDER BY start_datetime ASC LIMIT 1",
            ['start' => $movie['start_datetime']]
        );

        if ($next && !empty($next['secondary_feature'])) {
            return [
                'mode' => 'exclude',
                'tags' => self::parseTags($next['filter_tags'] ?? '')
            ];
        }

        return ['mode' => 'none', 'tags' => []];
    }

    /**
     * Test a decoded toot data array against a filter.
     * Returns true if the toot should be kept.
     */
    public static function matches($tootData, $filter) {
        if ($filter['mode'] === 'none' || empty($filter['tags'])) {
            return true;
        }

        $tootTags = [];
        if (!empty($tootData['tags'])) {
            foreach ($tootData['tags'] as $t) {
                if (isset($t['name'])) {
                    $tootTags[] = strtolower($t['name']);
                }
            }
        }

        $hasAny = !empty(array_intersect($tootTags, $filter['tags']));

        return $filter['mode'] === 'include' ? $hasAny : !$hasAny;
    }
}
