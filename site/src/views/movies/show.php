<?php
use App\Helpers\ViewHelper;
?>

<div class="movie">

    <div class="info">
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
                <div class="meta">
                    <a href="https://letterboxd.com/imdb/<?= $movie['imdb_id'];?>" target="_blank">Letterboxd</a> &middot;
                    <a href="https://www.imdb.com/title/<?= $movie['imdb_id'];?>" target="_blank">IMDb</a><!--&middot;
                    <a href="#" target="_blank">Watch on archive.org</a>-->
                </div>
                <a href="#" class="close">
                    <?= icon('close-line');?>
                </a>
            </div>
            <div class="bottom">
                <div class="start_datetime">
                    Watched on <?= formatDateTime($movie['start_datetime'], "d. MMMM YYYY"); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="toots-loading">
        <?= icon('loader-2-line');?>
        <p>Loading toots...</p>
    </div>

    <div class="toots"></div>

</div>
<script>
    TootPlayer('<?= $movie['slug'];?>');
</script>





