<?php $this->include("parts/header", $header); ?>

<h1>Backstage &gt; Movies</h1>

<table class="movies">
    <tr>
        <th>Title</th>
        <th>Slug</th>
        <th>Release Date</th>
        <th>Start Datetime</th>
        <th>Duration in s</th>
        <th>IMDb id</th>
        <th></th>
    </tr>
    <tr class="new">
        <td><input type="text" name="title" value="<?= h(post('title'));?>" placeholder="optional"></td>
        <td><input type="text" name="slug" value="<?= h(post('slug'));?>" pattern="[a-z0-9\-]+" placeholder="optional"></td>
        <td><input type="date" name="release_date" value="<?= h(post('release_date'));?>" placeholder="optional"></td>
        <td><input type="datetime-local" name="start_datetime" value="<?= h(post('start_datetime'));?>" required></td>
        <td><input type="number" name="duration" value="<?= h(post('duration'));?>" placeholder="0"></td>
        <td><input type="text" name="imdb_id" value="<?= h(post('imdb_id'));?>" required pattern="tt[a-z0-9]+"></td>
        <td><button type="submit" class="add">add</button></td>
    </tr>

    <tr>
        <td colspan="7"><hr></td>
    </tr>

    <?php foreach ($movies as $movie) { ?>
        <tr class="edit" data-id="<?= $movie['id'];?>">
            <td><input type="text" name="title" value="<?= h($movie['title']);?>" required></td>
            <td><input type="text" name="slug" value="<?= h($movie['slug']);?>" required pattern="[a-z0-9\-]+"></td>
            <td><input type="date" name="release_date" value="<?= h($movie['release_date']);?>" required></td>
            <td><input type="datetime-local" name="start_datetime" value="<?= h($movie['start_datetime']);?>" required></td>
            <td><input type="number" name="duration" value="<?= h($movie['duration']);?>" required></td>
            <td><input type="text" name="imdb_id" value="<?= h($movie['imdb_id']);?>" required pattern="tt[a-z0-9]+"></td>
            <td>
                <button type="submit" style="display: none;">edit</button>
                <div class="button delete">delete</div>
            </td>
        </tr>
    <?php } ?>


</table>

<script>
    ready(() => {

        // add
        find('.movies .new button[type="submit"]').on("click", async () => {
            const tr = find('.movies .new');
            const result = await request("/backstage/movies", "POST", {
                title: tr.find('[name=title]').value,
                slug: tr.find('[name=slug]').value,
                release_date: tr.find('[name=release_date]').value,
                start_datetime: tr.find('[name=start_datetime]').value,
                duration: tr.find('[name=duration]').value,
                imdb_id: tr.find('[name=imdb_id]').value
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
        findAll('.edit .delete').forEach(el => {
            el.on('click', async () => {
                if (confirm('Really?')) {
                    await request("/backstage/movies/" + el.closest("tr").dataset.id, "DELETE");
                    location.reload();
                }
            });
        });

        // edit
        findAll('.edit input').forEach(el => {
            el.on('change', async () => {
                console.log("chaaange");
                const tr = el.closest('tr');
                const result = await request("/backstage/movies/" + tr.dataset.id, "POST", {
                    title: tr.find('[name=title]').value,
                    slug: tr.find('[name=slug]').value,
                    release_date: tr.find('[name=release_date]').value,
                    start_datetime: tr.find('[name=start_datetime]').value,
                    duration: tr.find('[name=duration]').value,
                    imdb_id: tr.find('[name=imdb_id]').value
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