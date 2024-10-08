<?php
use App\Helpers\ViewHelper;
?>

<div class="content">
    <div class="info">
        <a href="https://wiki.neuromatch.social/Monsterdon" target="_blank">#monsterdon</a> is a weekly monster movie watch party on <a href="https://mastodon.social/tags/monsterdon" target=_"blank">Mastodon</a>, organized by <a href="https://octodon.social/@Taweret" target="_blank">Taweret</a> and usually held at 6 PM Pacific on Sundays (1 AM UTC Mondays). If you missed it, replay the toots here and watch along.
    </div>

    <ul class="movies">
        <?php foreach ($movies as $movie) {

            // $startDatetime is UTC. convert it to pacific time
            $startDatetime = new DateTime($movie['start_datetime']);
            $startDatetime->setTimezone(new DateTimeZone('America/Los_Angeles'));
            $movie['start_datetime'] = $startDatetime->format('Y-m-d H:i:s');

            ?>
            <li class="movie">
                <a href="/<?= h($movie['slug']); ?>">
                    <div class="col col-cover">
                        <img src="/media/covers/<?= $movie['imdb_id'];?>.jpg" alt="Cover for <?= h($movie['title']);?>" loading="lazy" width="100" height="150">
                    </div>
                    <div class="col col-content">
                        <div class="top">
                            <h2 class="title">
                            <span class="name">
                                <?= h($movie['title']);?>
                            </span>
                                <span class="release-date" title="<?= h($movie['release_date']);?>">
                                <?= h(substr($movie['release_date'], 0, 4));?>
                            </span>
                            </h2>
                            <div class="duration">
                                <span><?= formatDuration($movie['duration']); ?></span>
                            </div>
                        </div>
                        <div class="bottom">
                            <div class="start_datetime">
                                Watched on <?= formatDateTime($movie['start_datetime'], "d. MMMM YYYY"); ?>
                            </div>
                        </div>
                    </div>
                </a>
            </li>

        <?php } ?>
    </ul>
</div>

