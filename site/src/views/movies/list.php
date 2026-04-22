<?php $this->include("parts/header", $header); ?>

<div class="content">
    <div class="info">
        <a href="https://wiki.neuromatch.social/Monsterdon" target="_blank">#monsterdon</a> is a weekly monster movie watch party on <a href="https://joinmastodon.org/" target=_"blank">Mastodon</a>. It's <a href="https://timeloop.cafe/@Taweret" target="_blank">organized by Taweret</a>, with <a href="https://monsterdonbingo.neocities.org/" target="_blank">Bingo Cards by Cheri</a>, <a href="https://www.threadless.com/shop/@thediremushrump/design/monsterdon-doodles-color/">T-Shirts by Louisa</a>, made awesome by every contributor and held at 6 PM Pacific on Sundays (1&nbsp;AM UTC Mondays). If you missed it, replay the toots here and watch along.
    </div>

    <div class="movies-filter">
        <div class="row">
            <div class="col col-left">
                <div class="setting-checkbox">
                    <div class="col col-checkbox">
                        <input type="checkbox" class="checkbox" id="include-secondary-features" <?= $includeSecondaryFeatures ? 'checked' : '';?>>
                        <label for="include-secondary-features" class="switch"></label>
                    </div>
                    <div class="col col-label">
                        <label for="include-secondary-features">Include Double Features</label>
                    </div>
                </div>
            </div>
            <div class="col col-right">
                <?= $totalCount;?> movies
            </div>
        </div>
    </div>

    <ul class="movies">
        <?php foreach ($movies as $movie) {

            // $startDatetime is UTC. convert it to pacific time
            $startDatetime = new DateTime($movie['start_datetime']);
            $startDatetime->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $movie['start_datetime'] = $startDatetime->format('Y-m-d H:i:s');

            ?>
            <li class="movie <?= $movie['is_in_future'] ? ' is-in-future' : '';?> <?= $movie['is_running'] ? ' is-running' : '';?> <?= !empty($movie['secondary_feature']) ? ' is-secondary-feature' : '';?>" <?= !empty($movie['secondary_feature']) ? ' title="#WrongFrogs"' : '';?>>
                <a href="/<?= h($movie['slug']); ?>">
                    <div class="inner">
                        <div class="col col-cover">
                            <img src="/media/covers/<?= $movie['imdb_id'];?>_thumb.jpg" alt="Cover for <?= h($movie['title']);?>" loading="lazy" width="100" height="150">
                        </div>
                        <div class="col col-content">
                            <div class="top">
                                <h2 class="title">
                                <span class="name">
                                    <?= h($movie['title']);?>
                                </span>
                                </h2>
                                <div class="meta">
                                <span class="release-date" title="<?= h($movie['release_date']);?>">
                                <?= h(substr($movie['release_date'], 0, 4));?>
                                </span>
                                    &middot;
                                    <span><?= formatDuration($movie['duration']); ?></span> &middot;
                                    <span><?= $movie['toot_count'];?> toots</span>
                                </div>
                            </div>
                            <div class="bottom">
                                <div class="start_datetime">
                                    <?php if ($movie['is_running']) { ?>
                                        <strong>Happening now!</strong><br>
                                        Go over to Mastodon. The toots are finished recording an hour after the movie ends.
                                    <?php } else { ?>
                                        <?= $movie['is_in_future'] ? 'To be watched' : 'Watched';?> on <?= formatDateTime($movie['start_datetime'], "d MMMM yyyy"); ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </a>
            </li>

        <?php } ?>
    </ul>

    <?php if ($totalPages > 1) { ?>
        <?php
        $pageUrl = function($p) {
            return $p <= 1 ? '/' : '/?page=' . $p;
        };
        ?>
        <div class="pagination">
            <div class="col col-prev">
                <div class="link-wrap">
                    <?php if ($page > 1) { ?>
                        <a href="<?= $pageUrl($page - 1);?>" class="page-link prev">
                            <svg width="10" height="15" viewBox="0 0 10 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.5 1L2 7.5L8.5 14" stroke="currentColor" stroke-width="2"></path>
                            </svg>
                            <span>Newer</span>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <div class="col col-all">
                <select class="all-pages" onchange="window.location.href = this.value">
                    <?php for ($p = 1; $p <= $totalPages; $p++) { ?>
                        <option value="<?= $pageUrl($p);?>" <?= $p === $page ? 'selected' : '';?>><?= $p;?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col col-next">
                <div class="link-wrap">
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?= $pageUrl($page + 1);?>" class="page-link next">
                            <svg width="10" height="15" viewBox="0 0 10 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.5 1L8 7.5L1.5 14" stroke="currentColor" stroke-width="2"></path>
                            </svg>
                            <span>Older</span>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php $this->include("parts/footer"); ?>