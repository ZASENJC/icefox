<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>

<!-- 回到顶部按钮 -->
<div class="back-to-top" id="backToTop">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</div>

<script src="<?php $this->options->themeUrl('/assets/js/jquery.min.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('/assets/js/alpinejs.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('/assets/js/fancybox.umd.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('/assets/js/scrollload.min.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('/assets/js/music-player.js'); ?>"></script>
<script src="<?php $this->options->themeUrl('/assets/js/icefox.js'); ?>"></script>

<?php if ($this->options->customJs): ?>
<script>
    <?php $this->options->customJs(); ?>
</script>
<?php endif; ?>
