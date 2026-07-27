<?php
// 显示文章视频
$videoUrl = $this->videoUrl;
if (!empty($videoUrl)) {
    ?>
    <div class="post-video">
        <video controls controlsList="nodownload" preload="metadata">
            <source src="<?php echo $videoUrl; ?>" type="video/mp4">
            您的浏览器不支持视频播放
        </video>
    </div>
    <?php
}
?>
