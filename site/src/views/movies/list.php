<?php $this->include("parts/header", $header); ?>

<div class="content">
    <div class="info">
        <a href="https://wiki.neuromatch.social/Monsterdon" target="_blank">#monsterdon</a> is a weekly monster movie watch party on <a href="https://joinmastodon.org/" target=_"blank">Mastodon</a>.<br>
        It's <a href="https://timeloop.cafe/@Taweret" target="_blank">organized by Taweret</a>, with <a href="https://monsterdonbingo.neocities.org/" target="_blank">Bingo Cards by Cheri</a>, <a href="https://www.threadless.com/shop/@thediremushrump/design/monsterdon-doodles-color/">T-Shirts by Louisa</a>, made awesome by every contributor and usually held at 6 PM Pacific on Sundays (1 AM UTC Mondays).<br>
        If you missed it, replay the toots here and watch along.
    </div>

    <ul class="movies">
        <?php foreach ($movies as $movie) {

            // $startDatetime is UTC. convert it to pacific time
            $startDatetime = new DateTime($movie['start_datetime']);
            $startDatetime->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $movie['start_datetime'] = $startDatetime->format('Y-m-d H:i:s');

            ?>
            <li class="movie <?= $movie['is_in_future'] ? ' is-in-future' : '';?> <?= $movie['is_running'] ? ' is-running' : '';?>">
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
</div>

<?php $this->include("parts/footer"); ?>