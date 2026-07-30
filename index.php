<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox主题全新3.0版本
 *
 * @package icefox
 * @author 小胖脸
 * @version 3.1.0
 * @link https://xiaopanglian.com
 */

// 包含头部文件
$this->need('header.php');
?>

    <main>
        <?php $this->need('components/head.php'); ?>

        <section class="content-container">
            <?php $this->need('components/post-list.php'); ?>
        </section>

        <?php $this->need('components/modals/setting.php'); ?>
        <?php $this->need('components/modals/editor.php'); ?>
    </main>


<?php $this->need('footer.php'); ?>
