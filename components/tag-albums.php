<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$tagAlbums = isset($this->icefoxTagAlbums) && is_array($this->icefoxTagAlbums)
    ? $this->icefoxTagAlbums
    : array();
if (!$tagAlbums) return;
?>

<section class="tag-archive-albums" aria-labelledby="tag-album-results-title">
    <h3 id="tag-album-results-title" class="tag-result-section-title">相册</h3>
    <div class="album-list-grid">
        <?php foreach ($tagAlbums as $album): ?>
            <article class="album-card">
                <a class="album-card-link" href="<?php echo htmlspecialchars($album['permalink'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="album-card-cover">
                        <?php if ($album['cover'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars($album['cover'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($album['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="album-card-placeholder">相册</div>
                        <?php endif; ?>
                    </div>
                    <div class="album-card-heading">
                        <span class="album-card-name"><?php echo htmlspecialchars($album['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($album['address'] !== ''): ?>
                            <span class="album-card-address" title="<?php echo htmlspecialchars($album['address'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($album['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($album['photoCount'] > 0): ?>
                        <div class="album-card-meta"><?php echo (int) $album['photoCount']; ?> 张照片</div>
                    <?php endif; ?>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
