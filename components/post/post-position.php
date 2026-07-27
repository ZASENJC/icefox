<?php
$position = getArticleFieldsByCid($this->cid, 'position');
$positionUrl = getArticleFieldsByCid($this->cid, 'positionUrl');

if (!empty($position)) {
    $positionText = $position[0]['str_value'];
    $hasUrl = !empty($positionUrl) && !empty($positionUrl[0]['str_value']);
    ?>
    <div class="post-position">
        <?php if ($hasUrl): ?>
            <a href="<?php echo $positionUrl[0]['str_value']; ?>" target="_blank"><?php echo $positionText; ?></a>
        <?php else: ?>
            <span><?php echo $positionText; ?></span>
        <?php endif; ?>
    </div>
<?php } ?>