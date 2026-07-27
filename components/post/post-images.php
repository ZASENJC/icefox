<?php
// $images是图片集合
$images = $this->images;
$imageCount = count($images);
// 一张图
if ($imageCount == 1) {
    ?>
    <div class="post-images">
        <div class="post-images-1 grid">
            <?php
            foreach ($images as $image):
                ?>
                <figure class="cell post-image-one"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                                                         alt="" class="preview-image"
                                                         data-fancybox="<?php echo $this->cid; ?>"
                                                         data-src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"/>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // 四张图
} else if ($imageCount == 4) {
    ?>
    <div class="post-images fixed-grid has-3-cols">
        <div class="post-images-2 grid">
            <?php
            $index = 1;
            foreach ($images as $image) {
                ?>
                <figure class="cell post-image"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="" class="preview-image"
                                                     data-fancybox="<?php echo $this->cid; ?>"
                                                     data-src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"/>
                </figure>
                <?php
                if ($index == 2) {
                    ?>
                    <figure></figure>
                    <?php
                }
                $index++;
            } ?>
        </div>
    </div>

    <?php
    // 大于9张图
} else if ($imageCount > 9) {

    ?>
    <div class="post-images fixed-grid has-3-cols">
        <div class="post-images-container grid">
            <?php
            $index = 1;
            foreach ($images as $image) {
                $hidden = '';
                if ($index > 9) {
                    $hidden = 'hidden';
                }
                if ($index == 9) {
                    $addNum = $imageCount - 9;
                    ?>
                    <figure class="cell post-image <?php echo $hidden; ?> reactive">
                        <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                             alt=""/>
                        <div class="post-image-zzc preview-image"
                             data-fancybox="<?php echo $this->cid; ?>"
                             data-src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>">+<?php echo $addNum; ?></div>
                    </figure>
                    <?php
                } else {
                    ?>
                    <figure class="cell post-image <?php echo $hidden; ?>">
                        <img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="" class="preview-image"
                             data-fancybox="<?php echo $this->cid; ?>"
                             data-src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"/>
                    </figure>
                    <?php
                }
                $index++;
            } ?>
        </div>
    </div>

    <?php
} else {
    ?>
    <div class="post-images fixed-grid has-3-cols">
        <div class="post-images-2 grid">
            <?php
            foreach ($images as $image):
                ?>
                <figure class="cell post-image"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                                                     alt="" class="preview-image"
                                                     data-fancybox="<?php echo $this->cid; ?>"
                                                     data-src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"/>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
}

?>
