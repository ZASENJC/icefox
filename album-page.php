<?php
/**
 * 相册独立页面模板
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 让共享头部使用相册专属顶部图，并把登录后的编辑按钮替换为新建相册。
$GLOBALS['ICEFOX_ALBUM_PAGE'] = true;
$this->need('header.php');
?>

<main class="album-page">
    <?php $this->need('components/head.php'); ?>

    <section class="content-container">
        <?php $this->need('components/album-gallery.php'); ?>
    </section>

    <?php $this->need('components/modals/setting.php'); ?>
    <?php $this->need('components/modals/album-editor.php'); ?>
</main>

<?php $this->need('footer.php'); ?>
