<?php

/**
 * @var array|null $movie      scoped movie, or null for overall
 * @var array      $allMovies  every movie, newest first (for the scope dropdown)
 * @var array      $toots      page of toots (with ['parsed'] decoded data + optional movie_slug/movie_title)
 * @var string     $sort       favs | boosts | replies | score
 * @var string     $media      all | image | video | audio | any
 * @var string|null $from      YYYY-MM-DD or null
 * @var string|null $to        YYYY-MM-DD or null
 * @var int        $page
 * @var int        $totalPages
 * @var int        $totalCount
 */

$this->include("parts/header-best-of", $header);

$qs = function(array $overrides = []) use ($sort, $media, $from, $to) {
    $params = array_filter([
        'sort'  => $sort  !== 'favs' ? $sort  : null,
        'media' => $media !== 'all'  ? $media : null,
        'from'  => $from,
        'to'    => $to,
    ], fn($v) => $v !== null && $v !== '');
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
        else $params[$k] = $v;
    }
    return $params ? '?' . http_build_query($params) : '';
};

$basePath = $movie ? '/best-of/' . h($movie['slug']) : '/best-of';

// Build a URL for a given scope (null = all movies, or a movie slug), keeping sort/media intact.
// Drops date range when switching to a specific movie (not applicable there).
$scopeUrl = function($targetSlug) use ($sort, $media, $from, $to) {
    $params = array_filter([
        'sort'  => $sort  !== 'favs' ? $sort  : null,
        'media' => $media !== 'all'  ? $media : null,
        'from'  => $targetSlug === null ? $from : null,
        'to'    => $targetSlug === null ? $to   : null,
    ], fn($v) => $v !== null && $v !== '');
    $base = $targetSlug === null ? '/best-of' : '/best-of/' . $targetSlug;
    return $params ? $base . '?' . http_build_query($params) : $base;
};

$pageUrl = function($p) use ($basePath, $qs) {
    $extra = $qs(['page' => $p > 1 ? $p : null]);
    return $basePath . $extra;
};

?>

<div class="content page-best-of-content">

    <button class="filters-toggle" type="button" aria-expanded="false">Filters</button>

    <div class="best-of-layout">

        <aside class="filters" aria-label="Filters">
            <form method="get" action="<?= $basePath ?>" class="filters-form">

                <fieldset class="filter-group">
                    <legend>Movie</legend>
                    <select class="scope-select" onchange="window.location.href = this.value">
                        <option value="<?= h($scopeUrl(null)) ?>" <?= $movie ? '' : 'selected' ?>>All movies</option>
                        <?php foreach ($allMovies as $m): ?>
                            <option value="<?= h($scopeUrl($m['slug'])) ?>" <?= ($movie && $movie['slug'] === $m['slug']) ? 'selected' : '' ?>>
                                <?= h($m['title']) ?><?= !empty($m['secondary_feature']) ? ' 🐸' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Sort by</legend>
                    <?php foreach ([
                        'favs'    => 'Favorites',
                        'boosts'  => 'Boosts',
                        'replies' => 'Replies',
                        'score'   => 'Combined score',
                    ] as $k => $label): ?>
                        <label class="radio">
                            <input type="radio" name="sort" value="<?= $k ?>" <?= $sort === $k ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset class="filter-group">
                    <legend>Media</legend>
                    <?php foreach ([
                        'all'   => 'All toots',
                        'any'   => 'Any media',
                        'image' => 'Images',
                        'video' => 'Videos',
                        'audio' => 'Audio',
                    ] as $k => $label): ?>
                        <label class="radio">
                            <input type="radio" name="media" value="<?= $k ?>" <?= $media === $k ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <?php if (!$movie): ?>
                <fieldset class="filter-group">
                    <legend>Date range</legend>
                    <label class="date">
                        <span>From</span>
                        <input type="date" name="from" value="<?= h($from ?? '') ?>">
                    </label>
                    <label class="date">
                        <span>To</span>
                        <input type="date" name="to" value="<?= h($to ?? '') ?>">
                    </label>
                </fieldset>
                <?php endif; ?>

                <div class="filter-actions">
                    <button type="submit" class="apply">Apply</button>
                    <a href="<?= $basePath ?>" class="reset">Reset</a>
                </div>
            </form>
        </aside>

        <main class="results">
            <?php /*
            <div class="results-meta">
                <?= number_format($totalCount) ?> toot<?= $totalCount === 1 ? '' : 's' ?>
            </div>
            */ ?>

            <?php if (empty($toots)): ?>
                <div class="empty">No toots match these filters.</div>
            <?php else: ?>
                <div class="toots">
                    <?php foreach ($toots as $t): $data = $t['parsed']; ?>
                        <article class="toot" data-id="<?= h($t['id']) ?>">
                            <a href="<?= h($data['url'] ?? '#') ?>" target="_blank" rel="noopener" class="toot-header">
                                <div class="col col-image">
                                    <img src="/media/avatars/<?= hash('sha256', $data['account']['uri']) ?>.jpg"
                                         alt="<?= h($data['account']['display_name']) ?>"
                                         loading="lazy" width="40" height="40">
                                </div>
                                <div class="col col-name">
                                    <div class="display-name"><?= h($data['account']['display_name']) ?></div>
                                    <div class="acct"><?= h($data['account']['acct']) ?></div>
                                </div>
                            </a>

                            <div class="toot-body">
                                <?= strip_tags($data['content'] ?? '', '<p><a><span><br>') ?>
                            </div>

                            <?php if (!empty($data['media_attachments'])): ?>
                                <div class="toot-media-attachments">
                                    <?php foreach ($data['media_attachments'] as $m):
                                        $originalURL = $m['remote_url'] ?: $m['url'];
                                        $mid = hash('sha256', $originalURL);
                                        $ext = trim(pathinfo($originalURL, PATHINFO_EXTENSION));
                                        if ($ext === '') $ext = $m['type'] === 'video' ? 'mp4' : 'jpg';
                                        $type = $m['type'] ?? '';
                                    ?>
                                        <?php if ($type === 'image'): ?>
                                            <div class="media media-image">
                                                <a href="/media/originals/<?= $mid ?>.<?= $ext ?>" target="_blank" rel="noopener">
                                                    <img src="/media/previews/<?= $mid ?>.jpg" alt="" loading="lazy">
                                                </a>
                                            </div>
                                        <?php elseif ($type === 'video'): ?>
                                            <div class="media media-video">
                                                <video controls preload="none" poster="/media/previews/<?= $mid ?>.jpg">
                                                    <source src="/media/originals/<?= $mid ?>.<?= $ext ?>" type="video/mp4">
                                                </video>
                                            </div>
                                        <?php elseif ($type === 'gifv'): ?>
                                            <div class="media media-gifv">
                                                <video autoplay loop muted playsinline>
                                                    <source src="/media/originals/<?= $mid ?>.<?= $ext ?>" type="video/mp4">
                                                </video>
                                            </div>
                                        <?php elseif ($type === 'audio'): ?>
                                            <div class="media media-audio">
                                                <audio controls preload="none">
                                                    <source src="/media/originals/<?= $mid ?>.<?= $ext ?>">
                                                </audio>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="toot-footer">
                                <div class="toot-stats">
                                    <span class="stat stat-favs" title="Favorites">★ <?= number_format((int)$t['favourites_count']) ?></span>
                                    <span class="stat stat-boosts" title="Boosts">&uarr; <?= number_format((int)$t['reblogs_count']) ?></span>
                                    <span class="stat stat-replies" title="Replies">&larr; <?= number_format((int)$t['replies_count']) ?></span>
                                    <?php if (!empty($t['movie_slug'])): ?>
                                        <a class="stat stat-movie" href="/<?= h($t['movie_slug']) ?>"><?= h($t['movie_title']) ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="toot-created_at">
                                    <?= formatDateTime($t['created_at'], "d MMM yyyy HH:mm:ss") ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <div class="col col-prev">
                            <div class="link-wrap">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $pageUrl($page - 1) ?>" class="page-link prev">
                                        <svg width="10" height="15" viewBox="0 0 10 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M8.5 1L2 7.5L8.5 14" stroke="currentColor" stroke-width="2"></path>
                                        </svg>
                                        <span>Newer</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col col-all">
                            <select class="all-pages" onchange="window.location.href = this.value">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <option value="<?= $pageUrl($p) ?>" <?= $p === $page ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col col-next">
                            <div class="link-wrap">
                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $pageUrl($page + 1) ?>" class="page-link next">
                                        <svg width="10" height="15" viewBox="0 0 10 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1.5 1L8 7.5L1.5 14" stroke="currentColor" stroke-width="2"></path>
                                        </svg>
                                        <span>Older</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('.filters-toggle');
    var root = document.querySelector('.best-of-layout');
    if (!btn || !root) return;
    btn.addEventListener('click', function () {
        var open = root.classList.toggle('filters-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});
</script>

<?php $this->include("parts/footer"); ?>
