<?php
$isAlbumPage = !empty($GLOBALS['ICEFOX_ALBUM_PAGE']);
$isAlbumDetail = $isAlbumPage && trim((string) $this->request->get('album', '')) !== '';
$albumPageUrl = Typecho_Common::url('albums', $this->options->index);
?>
<section class="top-container" x-data>
    <div class="top-container-left">
        <a class="tc-home" data-icon="home" href="<?php $this->options->siteUrl(); ?>" aria-label="返回首页">
            <?php $this->need("components/svgs/home-outline.php"); ?>
        </a>
        <a class="tc-album" data-icon="album" href="<?php echo htmlspecialchars($albumPageUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="相册">
            <?php $this->need("components/svgs/album-outline.php"); ?>
        </a>
        <button type="button" class="tc-links" data-icon="links" aria-label="友情链接"
                @click="$nextTick(() => { window.dispatchEvent(new CustomEvent('links-modal-open')) })">
            <?php $this->need("components/svgs/links-outline.php"); ?>
        </button>
        <!--<div class="tc-music" data-icon="music">
            <?php $this->need("components/svgs/music-outline.php"); ?>
        </div>-->
    </div>
    <div class="top-container-right">
        <?php
        $user = \Widget\User::alloc();
        if ($user->hasLogin()):
        ?>
            <?php if ($isAlbumPage): ?>
                <button type="button" class="tc-edit tc-album-create" aria-label="<?php echo $isAlbumDetail ? '上传照片' : '新建相册'; ?>"
                        @click="window.dispatchEvent(new CustomEvent('album-primary-action'))">
                    <?php $this->need("components/svgs/plus-outline.php"); ?>
                </button>
            <?php else: ?>
                <button type="button" class="tc-edit" data-icon="edit" aria-label="发布内容"
                        @click="window.dispatchEvent(new CustomEvent('editor-modal-open'))">
                    <?php $this->need("components/svgs/edit-outline.php"); ?>
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <div class="tc-setting" data-icon="setting"
             @click="$nextTick(() => { document.querySelector('.setting-modal')._x_dataStack[0].settingModalShow = true })">
            <?php $this->need("components/svgs/setting-outline.php"); ?>
        </div>
    </div>
</section>

<?php $this->need('components/modals/links.php'); ?>
<?php
$albumTopImage = trim((string) $this->options->albumTopImage);
$headerImage = $isAlbumPage && $albumTopImage !== '' ? $albumTopImage : trim((string) $this->options->topImage);
?>
<section class="header-container<?php echo $isAlbumPage ? ' album-header' : ''; ?>"<?php echo $isAlbumPage ? ' data-album-header' : ''; ?> style="<?php
    // 优先级: 背景视频 > 背景图片 > 默认颜色
    if ($isAlbumPage) {
        if ($headerImage === '') {
            echo 'background-color: #f1f1f1;';
        } else {
            echo 'background-image: url(' . htmlspecialchars($headerImage, ENT_QUOTES, 'UTF-8') . ');';
        }
    } elseif (empty($this->options->topVideo) && empty($this->options->topImage)) {
        echo 'background-color: #f1f1f1;';
    } elseif (empty($this->options->topVideo) && !empty($this->options->topImage)) {
        echo 'background-image: url(' . htmlspecialchars($this->options->topImage, ENT_QUOTES, 'UTF-8') . ');';
    }
?>">
    <?php if (!$isAlbumPage && !empty($this->options->topVideo)): ?>
        <!--顶部背景视频-->
        <video src="<?php echo htmlspecialchars($this->options->topVideo, ENT_QUOTES, 'UTF-8'); ?>" autoplay muted loop playsinline></video>
    <?php endif; ?>

    <div class="header-info">
        <div class="header-user">
            <a href="<?php $this->options->siteUrl(); ?>" class="header-site-title">
                <span><?php echo $this->options->title; ?></span>
            </a>
            <?php if ($this->options->avatarLink): ?>
                <a href="<?php echo htmlspecialchars($this->options->avatarLink, ENT_QUOTES, 'UTF-8'); ?>" class="header-avatar-link">
                    <?php if (!empty($this->options->logoUrl)): ?>
                        <img src="<?php echo htmlspecialchars($this->options->logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="logo"/>
                    <?php else: ?>
                        <div class="header-logo-placeholder">Logo</div>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <div class="header-avatar-nolink">
                    <?php if (!empty($this->options->logoUrl)): ?>
                        <img src="<?php echo htmlspecialchars($this->options->logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="logo"/>
                    <?php else: ?>
                        <div class="header-logo-placeholder">Logo</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="header-description">
            <?php $this->options->description(); ?>
        </div>
    </div>

</section>
