<?php $this->include("parts/header", ["title" => isset($title) ? $title : '', 'bodyClass' => isset($bodyClass) ? $bodyClass : '']); ?>
<?php  echo $_content; ?>
<?php $this->include("parts/footer"); ?>