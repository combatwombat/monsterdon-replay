<?php $this->include("parts/header", $header); ?>

<h1>Backstage &gt; Movies</h1>

<div class="movies-grid">
    <!-- Add new movie card -->
    <div class="movie-card new">
        <div class="movie-card-header">
            <h3>Add New Movie</h3>
        </div>
        <div class="movie-card-content">
            <div class="field-group">
                <label>Start Datetime *</label>
                <input type="datetime-local" name="start_datetime" value="<?= h(post('start_datetime'));?>" required>
            </div>
            <div class="field-group">
                <label>IMDb ID *</label>
                <input type="text" name="imdb_id" value="<?= h(post('imdb_id'));?>" required pattern="tt[a-z0-9]+" placeholder="tt1234567">
            </div>
            <div class="field-group">
                <label>OG Image Cover Offset</label>
                <input type="number" name="og_image_cover_offset" value="<?= h(isset($_POST['og_image_cover_offset']) ? $_POST['og_image_cover_offset'] : 50);?>" placeholder="50" min="0" max="100">
            </div>
            <!-- Hidden optional fields -->
            <input style="display: none" type="text" name="title" value="<?= h(post('title'));?>" placeholder="optional">
            <input style="display: none" type="text" name="slug" value="<?= h(post('slug'));?>" pattern="[a-z0-9\-]+" placeholder="optional">
            <input style="display: none" type="date" name="release_date" value="<?= h(post('release_date'));?>" placeholder="optional">
            <input style="display: none" type="number" name="duration" value="<?= h(post('duration'));?>" placeholder="0">
            <input style="display: none" type="number" name="tmdb_id" value="<?= h(post('tmdb_id'));?>" placeholder="optional">
        </div>
        <div class="movie-card-actions">
            <button type="submit" class="add">Add Movie</button>
        </div>
    </div>

    <!-- Existing movies -->
    <?php foreach ($movies as $movie) { ?>
        <div class="movie-card edit" data-id="<?= $movie['id'];?>">
            <div class="movie-card-header">
                <h3><?= h($movie['title']); ?></h3>
                <div class="movie-actions">
                    <button type="submit" style="display: none;">edit</button>
                    <div class="button delete">delete</div>
                </div>
            </div>
            <div class="movie-card-content">
                <div class="field-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= h($movie['title']);?>" required>
                </div>
                <div class="field-group">
                    <label>Slug</label>
                    <input type="text" name="slug" value="<?= h($movie['slug']);?>" required pattern="[a-z0-9\-]+">
                </div>
                <div class="field-group">
                    <label>Release Date</label>
                    <input type="date" name="release_date" value="<?= h($movie['release_date']);?>" required>
                </div>
                <div class="field-group">
                    <label>Start Datetime</label>
                    <input type="datetime-local" name="start_datetime" value="<?= h($movie['start_datetime']);?>" required>
                </div>
                <div class="field-group">
                    <label>Duration (seconds)</label>
                    <input type="number" name="duration" value="<?= h($movie['duration']);?>" required>
                </div>
                <div class="field-group">
                    <label>IMDb ID</label>
                    <input type="text" name="imdb_id" value="<?= h($movie['imdb_id']);?>" required pattern="tt[a-z0-9]+">
                </div>
                <div class="field-group">
                    <label>TMDB ID</label>
                    <input type="number" name="tmdb_id" value="<?= h($movie['tmdb_id']);?>" required>
                </div>
                <div class="field-group og-image-cover-offset">
                    <label>OG Image Cover Offset</label>
                    <div class="input-with-link">
                        <input type="number" name="og_image_cover_offset" value="<?= h($movie['og_image_cover_offset']);?>" min="0" max="100" required>
                        <a href="/media/covers/<?= $movie['imdb_id'];?>_ogimage.png" class="og-image-link" target="_blank">
                            <?= icon("image-line");?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<script>
    ready(() => {

        // add
        find('.movie-card.new button[type="submit"]').on("click", async () => {
            const card = find('.movie-card.new');
            const result = await request("/backstage/movies", "POST", {
                title: card.find('[name=title]').value,
                slug: card.find('[name=slug]').value,
                release_date: card.find('[name=release_date]').value,
                start_datetime: card.find('[name=start_datetime]').value,
                duration: card.find('[name=duration]').value,
                imdb_id: card.find('[name=imdb_id]').value,
                tmdb_id: card.find('[name=tmdb_id]').value,
                og_image_cover_offset: card.find('[name=og_image_cover_offset]').value
            }, "json");

            if (result.status === 'error') {
                let errorString = '';
                for (const key in result.errors) {
                    errorString += key + ': ' + result.errors[key].join(', ') + '\n';
                }
                alert(errorString);
            } else {
                location.reload();
            }
        });

        // delete
        findAll('.movie-card.edit .delete').forEach(el => {
            el.on('click', async () => {
                if (confirm('Really?')) {
                    await request("/backstage/movies/" + el.closest(".movie-card").dataset.id, "DELETE");
                    location.reload();
                }
            });
        });

        // edit
        findAll('.movie-card.edit input').forEach(el => {
            el.on('change', async () => {
                const card = el.closest('.movie-card');
                const result = await request("/backstage/movies/" + card.dataset.id, "POST", {
                    title: card.find('[name=title]').value,
                    slug: card.find('[name=slug]').value,
                    release_date: card.find('[name=release_date]').value,
                    start_datetime: card.find('[name=start_datetime]').value,
                    duration: card.find('[name=duration]').value,
                    imdb_id: card.find('[name=imdb_id]').value,
                    tmdb_id: card.find('[name=tmdb_id]').value,
                    og_image_cover_offset: card.find('[name=og_image_cover_offset]').value
                }, "json");

                // example: {"status":"error","errors":{"foo":["bar"],"bar":["baakjhsjh"]}}

                if (result.status === 'error') {
                    let errorString = '';
                    for (const key in result.errors) {
                        errorString += key + ': ' + result.errors[key].join(', ') + '\n';
                    }
                    alert(errorString);
                }
            })
        })
    });
</script>

<?php $this->include("parts/footer"); ?>