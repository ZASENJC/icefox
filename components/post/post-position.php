<?php
$position = getArticleFieldsByCid($this->cid, 'position');
$positionUrl = getArticleFieldsByCid($this->cid, 'positionUrl');

if (!empty($position)) {
    $positionText = $position[0]['str_value'];
    $hasUrl = !empty($positionUrl) && !empty($positionUrl[0]['str_value']);
    ?>
    <div class="post-position">
        <?php if ($hasUrl): ?>
            <a href="<?php echo htmlspecialchars($positionUrl[0]['str_value'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($positionText, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php else: ?>
            <span><?php echo htmlspecialchars($positionText, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </div>
<?php } ?>
