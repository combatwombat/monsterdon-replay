<?php $this->include("parts/html-header", ["title" => isset($title) ? $title : '']); ?>

<body
    <?= !empty($bodyClass) ? ' class="'.$bodyClass.'"' : '';?>
    <?= !empty($backgroundImage) ? 'style="--background-image: ' . $backgroundImage . '"' : '';?>
>
<div class="site">
    <header <?= !empty($headerClass) ? ' class="'.$headerClass.'"' : '';?>>
        <div class="col col-left">
            <?php if (!empty($backLink)) { ?>
                <a href="<?php echo $backLink;?>" class="back">
                    <?= icon('arrow-left-s-line');?>
                </a>
            <?php } ?>
        </div>
        <div class="col col-middle">
            <h1 class="logo">
                <a href="/">
                    <img src="/img/logo.svg" alt="#monsterdon replay">
                </a>
            </h1>
        </div>
        <div class="col col-right">
            <?php if (!empty($backLink)) { ?>
                <a href="#" class="open-movie-info">
                    <?= icon('information-2-line');?>
                </a>
            <?php } ?>
        </div>

    </header>
