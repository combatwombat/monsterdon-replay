<?php $this->include("parts/html-header", ["title" => isset($title) ? $title : "", "ogImage" => isset($ogImage) ? $ogImage : ""]); ?>

<body
    <?= !empty($bodyClass) ? ' class="'.$bodyClass.'"' : '';?>
    <?= !empty($backgroundImage) ? 'style="--background-image: ' . $backgroundImage . '"' : '';?>
>
<div class="site">
    <header class="header-best-of">
        <div class="col col-logo">
            <h1 class="logo">
                <a href="/">
                    <img src="<?= !empty($logoSrc) ? $logoSrc : '/img/logo.svg' ?>" alt="#monsterdon replay">
                </a>
            </h1>
        </div>
        <div class="col col-scope">
            <?= !empty($scopeTitle) ? htmlspecialchars($scopeTitle) : 'all movies' ?>
        </div>
    </header>

