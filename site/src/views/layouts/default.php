<?php $this->include("parts/header", [
    'title' => isset($title) ? $title : '',
    'bodyClass' => isset($bodyClass) ? $bodyClass : '',
    'backLink' => isset($backLink) ? $backLink : '',
    'backgroundImage' => isset($backgroundImage) ? $backgroundImage : ''
]); ?>
<?php  echo $_content; ?>
<?php $this->include("parts/footer"); ?>