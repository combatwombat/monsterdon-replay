<?php

define('BASEPATH', dirname(__DIR__));


$config = require BASEPATH . '/config/config.php';
require BASEPATH . '/classes/Base.php';
require BASEPATH . '/classes/TMDB.php';


// escape html
function h($str) {
    if (is_null($str)) {
        $str = '';
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function post($key) {
    return isset($_POST['key']) ? $_POST['key'] : null;
}


class Web extends Base {

    public $tmdb;

    public function __construct($config) {
        parent::__construct($config);
        $this->tmdb = new TMDB($config);
    }

    public function run() {

        // me: i want a router!
        // mom: we have a router at home.
        // the router at home:

        $uri = $_SERVER['REQUEST_URI'];
        $uri = explode('?', $uri)[0];
        $uri = explode('#', $uri)[0];
        $uri = rtrim($uri, '/');

        // homepage
        if ($uri == '') {
            $this->pageMovies();

        // movie page /movie/{alphanumeric-plus-dashes}
        } else if (preg_match('/^\/movie\/([a-zA-Z0-9-]+)$/', $uri, $matches)) {

            $movie = $this->getMovieBySlug($matches[1]);

            if ($movie) {
                $this->pageMovie($movie);
            } else {
                $this->page404();
            }

        } else if ($uri == "/backstage/movies") {
            $this->pageBackstageMovies();

        } else {
            $this->page404();
        }


        


    }


    //////////////////// DB ////////////////////

    public function getMovieBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM movies WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }



    //////////////////// Pages /////////////////

    public function page404() {
        header("HTTP/1.0 404 Not Found");
        $this->header('404', 'page-404');
        echo "<h1>404</h1><h2>nope</h2>";
        $this->footer();
    }

    public function pageMovie($movie) {

        $this->header($movie['title'], 'page-movie');
        echo "<h1>moopie</h1><pre>".print_r($movie, true) . print_r($_GET, true)."</pre>";
        $this->footer();

    }

    public function pageMovies() {

        $this->header('', 'page-movies');
        echo "<h1>übersicht</h1>";
        $this->footer();

    }

    public function validateMovieFields() {
        return $this->validate([
            'title' => ['data' => $_POST['title'], 'rules' => 'required'],
            'slug' => ['data' => $_POST['slug'], 'rules' => 'required|regex:[a-z0-9\-]+'],
            'release_date' => ['data' => $_POST['release_date'], 'rules' => 'required|date'],
            'start_datetime' => ['data' => $_POST['start_datetime'], 'rules' => 'required|datetime'],
            'duration' => ['data' => $_POST['duration'], 'rules' => 'required|numeric'],
            'imdb_id' => ['data' => $_POST['imdb_id'], 'rules' => 'required|regex:tt[a-z0-9]+']
        ]);
    }

    public function pageBackstageMovies() {
        $this->auth(); // members only

        if (isset($_POST['action'])) {
            if ($_POST['action'] == 'add') {

                $errors = $this->validateMovieFields();

                if (!$errors) {
                    // check if slug already exist
                    $stmt = $this->db->prepare("SELECT * FROM movies WHERE slug = ?");
                    $stmt->execute([$_POST['slug']]);
                    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($movie) {
                        $errors['slug'][] = 'slug already exists';
                    }
                }

                if (empty($errors)) {
                    $stmt = $this->db->prepare("INSERT INTO movies (title, slug, release_date, start_datetime, duration, imdb_id) VALUES (?, ?, ?, ?, ?, ?)");

                    try {
                        $stmt->execute([$_POST['title'], $_POST['slug'], $_POST['release_date'], $_POST['start_datetime'], $_POST['duration'], $_POST['imdb_id']]);
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        exit;
                    }

                    header("Location: /backstage/movies");
                    exit;
                }

            }

            if ($_POST['action'] == 'edit') {

                $errors = $this->validateMovieFields();

                if (!$errors) {
                    // check if slug already exist on another movie
                    $stmt = $this->db->prepare("SELECT * FROM movies WHERE slug = ? AND id != ?");
                    $stmt->execute([$_POST['slug'], $_POST['id']]);
                    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($movie) {
                        $errors['slug'][] = 'slug already exists';
                    }
                }

                if (empty($errors) && isset($_POST['id'])) {
                    $stmt = $this->db->prepare("UPDATE movies SET title = ?, slug = ?, release_date = ?, start_datetime = ?, duration = ?, imdb_id = ? WHERE id = ?");
                    $stmt->execute([$_POST['title'], $_POST['slug'], $_POST['release_date'], $_POST['start_datetime'], $_POST['duration'], $_POST['imdb_id'], $_POST['id']]);
                    header("Location: /backstage/movies");
                    exit;
                }

            }

            if ($_POST['action'] == 'delete') {
                $stmt = $this->db->prepare("DELETE FROM movies WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                header("Location: /backstage/movies");
                exit;
            }
        }

        $stmt = $this->db->prepare("SELECT * FROM movies ORDER BY start_datetime DESC");
        $stmt->execute();
        $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->header('Backstage', 'page-backstage');
        ?>
        <h1>Movies</h1>

        <?php if (!empty($errors)) { ?>
            <ul class="errors">
                <?php foreach ($errors as $field => $fieldErrors) { ?>
                    <li>
                    <?= implode(', ', $fieldErrors);?>
                    </li>
                <?php }?>
            </ul>
        <?php }?>

        <form action="" method="post">
            <input type="hidden" name="action" value="add">
            <table class="movie movie-add">
                <tr>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Release Date</th>
                    <th>Start Datetime</th>
                    <th>Duration in s</th>
                    <th>IMDb id</th>
                    <th></th>
                </tr>
                <tr>
                    <td><input type="text" name="title" value="<?= h(post('title'));?>" required></td>
                    <td><input type="text" name="slug" value="<?= h(post('slug'));?>" required pattern="[a-z0-9\-]+"></td>
                    <td><input type="date" name="release_date" value="<?= h(post('release_date'));?>" required></td>
                    <td><input type="datetime-local" name="start_datetime" value="<?= h(post('start_datetime'));?>" required></td>
                    <td><input type="number" name="duration" value="<?= h(post('duration'));?>" required></td>
                    <td><input type="text" name="imdb_id" value="<?= h(post('imdb_id'));?>" required pattern="tt[a-z0-9]+"></td>
                    <td><button type="submit" class="add">add</button></td>
                </tr>
            </table>
        </form>

        <?php foreach ($movies as $movie) { ?>
            <form action="" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $movie['id'];?>">
                <table class="movie movie-edit">
                    <tr>
                        <td><input type="text" name="title" value="<?= h($movie['title']);?>" required></td>
                        <td><input type="text" name="slug" value="<?= h($movie['slug']);?>" required pattern="[a-z0-9\-]+"></td>
                        <td><input type="date" name="release_date" value="<?= h($movie['release_date']);?>" required></td>
                        <td><input type="datetime-local" name="start_datetime" value="<?= h($movie['start_datetime']);?>" required></td>
                        <td><input type="number" name="duration" value="<?= h($movie['duration']);?>" required></td>
                        <td><input type="text" name="imdb_id" value="<?= h($movie['imdb_id']);?>" required pattern="tt[a-z0-9]+"></td>
                        <td>
                            <button type="submit" style="display: none;"></button>
                            <div class="button delete" data-id="<?= $movie['id'];?>">delete</div>
                        </td>
                    </tr>
                </table>
            </form>
        <?php } ?>

        <script>
            ready(() => {
                // delete ,ovie
                document.querySelectorAll('.movie-edit .delete').forEach(el => {
                    el.addEventListener('click', async () => {
                        if (confirm('Really?')) {
                            await httpRequest("/backstage/movies", "POST", {action: "delete", id: el.dataset.id });
                            location.reload();
                        }
                    });
                });
            });
        </script>


        <?php
        $this->footer();

    }


    ////////////////// Parts /////////////////////

    public function header($title = '', $bodyClasses = '') {
        $filemtimeCSS = filemtime(BASEPATH . '/public/css/main.css');
        $filemtimeJS = filemtime(BASEPATH . '/public/js/main.js');
        ?>
        <html>
        <head>
            <title>#monsterdon replay <?= $title ? '&middot; ' . h($title) : '';?></title>
            <link rel="stylesheet" type="text/css" href="/css/main.css?v=<?php echo $filemtimeCSS;?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <script src="/js/main.js?v=<?php echo $filemtimeJS;?>"></script>
            <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
            <link rel="apple-touch-icon" sizes="256x256" href="/img/icon-256.png">
        </head>
        <body<?= !empty($bodyClasses) ? ' class="'.$bodyClasses.'"' : '';?>>
        <div class="site">
        <?php
    }

    public function footer() {
        ?>
        </div>
        </body>
        </html>
        <?php
    }


}

$web = new Web($config);
$web->run();

