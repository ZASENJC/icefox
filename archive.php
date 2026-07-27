<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 包含头部文件
$this->need('header.php');
?>

<main>
    <?php $this->need('components/head.php'); ?>

    <section class="content-container">
        <!-- 归档标题 -->
        <div class="archive-header">
            <h2 class="archive-title"><?php $this->archiveTitle([
                'category' => _t('分类 %s 下的文章'),
                'search'   => _t('包含关键字 %s 的文章'),
                'tag'      => _t('标签 %s 下的文章'),
                'author'   => _t('%s 发布的文章')
            ], '', ''); ?></h2>
        </div>

        <!-- 无限滚动容器 -->
        <div class="scrollload-container">
            <div class="post-list scrollload-content" x-data="commentReplyManager()">
                <?php if ($this->have()): ?>
                    <?php while ($this->next()): ?>
                        <article class="post-item">
                            <div class="post-item-left">
                                <a href="<?php $this->author->permalink() ?>">
                                    <img alt="<?php $this->author() ?>"
                                         src="<?php echo getGravatarUrl($this->author->mail, 64, 'identicon', 'g'); ?>">
                                </a>
                            </div>
                            <div class="post-item-right">
                                <h2 class="post-title">
                                    <a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a>
                                </h2>
                                <div class="post-content">
                                    <?php
                                    // 获取主题设置
                                    $options = \Widget\Options::alloc();
                                    $autoCollapse = $options->autoCollapse !== '0'; // 默认为 true（收起）

                                    // 先过滤内容（保留摘要用的标签）
                                    $filtered = filterContent($this->content);

                                    // 生成摘要（音乐短代码不计入截断长度，会完整保留）
                                    $cws = generateContentWithSummaryAndMusic($filtered, 100);

                                    // 解析音乐短代码
                                    $musicHtml = !empty($cws['music_shortcodes']) ? parseMusicShortcode($cws['music_shortcodes']) : '';

                                    if ($autoCollapse) {
                                        // 自动收起模式：显示摘要，点击展开全文
                                        if ($cws['is_truncated'] === true) {
                                            // 有截断：摘要 + 全文按钮 | [隐藏]完整内容 + 收起按钮 | 音乐卡片
                                            echo '<div class="summary-' . $this->cid . '">' . $cws['summary'] . '<span class="show_all_btn cursor-pointer" data-cid="' . $this->cid . '">全文</span></div>';
                                            echo '<div class="hidden full_content-' . $this->cid . '">' . $cws['full_content'] . '<div><span class="hide_all_btn cursor-pointer" data-cid="' . $this->cid . '">收起</span></div></div>';
                                        } else {
                                            // 无截断：直接显示完整内容
                                            echo '<div>' . $cws['full_content'] . '</div>';
                                        }
                                        // 音乐卡片始终在最后面
                                        echo $musicHtml;
                                    } else {
                                        // 不收起模式：直接显示完整内容 + 音乐卡片
                                        echo '<div class="full-content-display">' . $cws['full_content'] . '</div>';
                                        echo $musicHtml;
                                    }
                                    ?>
                                </div>
                                <?php
                                // 检查是否有视频
                                $videoSrc = extractVideoSrc($this->content);
                                if ($videoSrc) {
                                    // 有视频则显示视频
                                    $this->videoUrl = $videoSrc;
                                    $this->need('components/post/post-video.php');
                                } else {
                                    // 没有视频则显示图片
                                    $images = extractImageSrcs($this->content);
                                    $this->images = $images;
                                    $this->need('components/post/post-images.php');
                                }

                                $this->need('components/post/post-position.php');
                                ?>
                                <div class="post-time">
                                    <time
                                        datetime="<?php $this->date('yyyy年mm月dd日'); ?>"><?php echo formatCommentTime($this->created); ?></time>
                                    <div class="post-time-comment" x-data="{ptcmShow: false}"
                                         :id="'ptcm-' + <?php echo $this->cid; ?>">
                                        <div class="ptc-more" @click="togglePostTimeComment($event, <?php echo $this->cid; ?>)">
                                            <svg t="1709204592505" class="icon" viewBox="0 0 1024 1024" version="1.1"
                                                 xmlns="http://www.w3.org/2000/svg"
                                                 p-id="16237" width="16" height="16">
                                                <path d="M229.2 512m-140 0a140 140 0 1 0 280 0 140 140 0 1 0-280 0Z"
                                                      p-id="16238" fill="#8a8a8a"></path>
                                                <path d="M794.8 512m-140 0a140 140 0 1 0 280 0 140 140 0 1 0-280 0Z" p-id="16239"
                                                      fill="#8a8a8a"></path>
                                            </svg>
                                        </div>
                                        <div class="post-time-comment-modal" x-show="ptcmShow"
                                             x-transition.in.duration.300ms.origin.top.right>
                                            <div class="ptcm-good like-menu-btn" data-cid="<?php echo $this->cid; ?>" @click="toggleLike($event, <?php echo $this->cid; ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="16"
                                                     width="16"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6 like-menu-icon">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                                                </svg>
                                                <span class="like-menu-text">点赞</span>
                                            </div>
                                            <div class="ptcm-comment" @click="showPostReplyForm($event, <?php echo $this->cid; ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="16"
                                                     width="16"
                                                     stroke-width="1.5" stroke="currentColor" class="size-6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                                                </svg>
                                                评论
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="post-comment-container">
                                    <div class="pcc-like-list" data-cid="<?php echo $this->cid; ?>">
                                        <div class="pcc-like-summary">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="16"
                                                 width="16"
                                                 stroke-width="1.5" stroke="currentColor" class="like-icon">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                                            </svg>
                                            <span class="like-users-text"></span>
                                        </div>
                                    </div>
                                    <div class="pcc-comment-list">
                                        <?php
                                        $comments = getPostLatestCommentsWithReplies($this->cid, 5);
                                        foreach ($comments as $comment) {
                                            ?>
                                            <div class="pcc-comment-item"
                                                 data-comment-id="<?php echo $comment['coid'] ?? $comment['id'] ?? 0; ?>">
                                                <a href="<?php echo htmlspecialchars($comment['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($comment['author'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                <span>:</span>
                                                <span class="cursor-help pcc-comment-content"
                                                      @click="showReplyForm($event, '<?php echo $this->cid; ?>', '<?php echo $comment['coid'] ?? $comment['id'] ?? 0; ?>', <?php echo htmlspecialchars(json_encode($comment['author'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo htmlspecialchars($comment['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                            <?php
                                            foreach ($comment['replies'] as $reply) {
                                                ?>
                                                <div class="pcc-comment-item"
                                                     data-comment-id="<?php echo $reply['coid'] ?? $reply['id'] ?? 0; ?>">
                                                    <a href="<?php echo htmlspecialchars($reply['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reply['author'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <span>回复</span>
                                                    <a href="<?php echo htmlspecialchars($reply['parentUrl'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($reply['parentAuthor'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <span>:</span>
                                                    <span class="cursor-help pcc-comment-content"
                                                          @click="showReplyForm($event, '<?php echo $this->cid; ?>', '<?php echo $reply['coid'] ?? $reply['id'] ?? 0; ?>', <?php echo htmlspecialchars(json_encode($reply['author'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)"><?php echo htmlspecialchars($reply['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="archive-empty">
                        <div class="archive-empty-icon">🔍</div>
                        <h3><?php _e('没有找到内容'); ?></h3>
                        <p><?php _e('换个关键词试试吧'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 分页信息（隐藏） -->
        <div class="pagination" style="display: none;">
            <?php
            $currentPage = $this->_currentPage;
            $totalPages = $this->_totalPages;
            ?>
            <span class="current-page" data-page="<?php echo $currentPage; ?>"></span>
            <span class="total-pages" data-total="<?php echo $totalPages; ?>"></span>
            <?php $this->pageNav('&laquo;', '&raquo;', 1, '...', array('wrapTag' => 'div', 'wrapClass' => 'page-navigator', 'itemTag' => 'span', 'currentClass' => 'current')); ?>
        </div>
    </section>

    <?php $this->need('components/modals/setting.php'); ?>
    <?php $this->need('components/modals/login.php'); ?>
</main>

<?php $this->need('footer.php'); ?>
