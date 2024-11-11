<?php
use App\Helpers\ViewHelper;

// $startDatetime is UTC. convert it to pacific time
$startDatetime = new DateTime($movie['start_datetime']);
$startDatetime->setTimezone(new DateTimeZone('America/Los_Angeles'));
$movie['start_datetime'] = $startDatetime->format('Y-m-d H:i:s');
?>

<?php $this->include("parts/header", $header); ?>


<div class="movie">

    <div class="movie-info">
        <div class="col col-cover">
            <img src="/media/covers/<?= $movie['imdb_id'];?>.jpg" alt="Cover for <?= h($movie['title']);?>" width="100" height="150">
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
                    </span> &middot;
                    <span><?= formatDuration($movie['duration']); ?></span> &middot;
                    <span><?= $movie['toot_count'];?> toots</span>
                </div>
                <div class="meta">
                    <a href="https://letterboxd.com/imdb/<?= $movie['imdb_id'];?>" target="_blank">Letterboxd</a> &middot;
                    <a href="https://www.imdb.com/title/<?= $movie['imdb_id'];?>" target="_blank">IMDb</a> &middot;
                    <a href="https://www.themoviedb.org/movie/<?= $movie['tmdb_id'];?>/watch" target="_blank">Where to watch</a> &middot;
                    <a href="/<?= $movie['slug'];?>/subtitles" target="_blank">Toots as Subtitles</a>
                </div>
                <a href="#" class="close">
                    <?= icon('close-line');?>
                </a>
            </div>
            <div class="bottom">
                <div class="start_datetime">
                    Watched on <?= formatDateTime($movie['start_datetime'], "d MMMM YYYY"); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="toots-loading">
        <?= icon('loader-2-line');?>
        <p>Loading toots...</p>
    </div>

    <div class="toots-start">
        <a href="#" class="toots-start-button">
            <?= icon('play-circle-fill');?>
            <span>Start</span>
        </a>
    </div>

    <div class="toots"></div>

    <div class="player">
        <div class="settings">
            <div class="row setting-checkbox">
                <div class="col col-label">
                    Compact View
                </div>
                <div class="col col-checkbox">
                    <input type="checkbox" class="checkbox" id="setting-compact">
                    <label for="setting-compact" class="switch"></label>
                </div>
            </div>
            <div class="row setting-checkbox">
                <div class="col col-label">
                    Hide Hashtags
                </div>
                <div class="col col-checkbox">
                    <input type="checkbox" class="checkbox" id="setting-hide-hashtags">
                    <label for="setting-hide-hashtags" class="switch"></label>
                </div>
            </div>
        </div>
        <div class="columns">
            <a href="#" class="col col-play-pause play-pause-button">
                <div class="icon icon-play">
                    <?= icon('play-circle-fill');?>
                </div>
                <div class="icon icon-pause">
                    <?= icon('pause-circle-fill');?>
                </div>
            </a>
            <div class="col col-timeline">
                <div class="current-time">
                    2:03:26
                </div>
                <div class="timeline-wrap">
                    <x-timeline class="input-current-time" value="0" min="0" max="<?= $overallDuration;?>" step="1"></x-timeline>
                </div>
                <div class="overall-time">
                    2:33:07
                </div>
            </div>
            <a href="#" class="col col-settings open-settings">
                <div class="icon">
                    <?= icon("equalizer-line"); ?>
                </div>
            </a>
        </div>
    </div>
</div>
<script>
    ready(() => {
        TootPlayer('<?= $movie['slug'];?>');
    });
</script>

<?php $this->include("parts/footer"); ?>



