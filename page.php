<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 独立页面模板
 *
 * @package icefox
 * @author 小胖脸
 * @version 3.1.3
 * @link https://xiaopanglian.com
 */

// 包含头部文件
$this->need('header.php');
?>

<main>
    <?php $this->need('components/head.php'); ?>

    <section class="content-container">
        <div class="post-detail" x-data="commentReplyManager()">
            <!-- 页面主体 -->
            <article class="post-item post-detail-item">
                <div class="post-item-left">
                    <a href="<?php $this->author->permalink() ?>">
                        <img alt="<?php $this->author() ?>"
                             src="<?php echo getGravatarUrl($this->author->mail, 64, 'identicon', 'g'); ?>">
                    </a>
                </div>
                <div class="post-item-right">
                    <!-- 页面标题 -->
                    <h2 class="post-title">
                        <a href="<?php $this->author->permalink() ?>"><?php $this->author() ?></a>
                        <span class="page-badge">页面</span>
                    </h2>

                    <!-- 页面内容 -->
                    <div class="post-content">
                        <?php $this->content(); ?>
                    </div>

                    <!-- 页面自定义字段 -->
                    <?php if ($this->fields->customFields): ?>
                        <div class="page-custom-fields">
                            <?php
                            $customFields = json_decode($this->fields->customFields, true);
                            if (is_array($customFields)):
                                foreach ($customFields as $field):
                                    if (!empty($field['value'])):
                            ?>
                                        <div class="custom-field">
                                            <strong><?php echo $field['name']; ?>：</strong>
                                            <span><?php echo $field['value']; ?></span>
                                        </div>
                            <?php
                                    endif;
                                endforeach;
                            endif;
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- 页面信息 -->
                    <?php $this->need('components/post/post-position.php'); ?>
                    <div class="post-time">
                        <time
                            datetime="<?php $this->date('yyyy年mm月dd日'); ?>"><?php $this->date('Y年m月d日'); ?></time>
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
                                <div class="ptcm-good like-menu-btn" data-cid="<?php echo $this->cid; ?>"
                                     @click="toggleLike($event, <?php echo $this->cid; ?>)">
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

                    <!-- 点赞和评论区 -->
                    <div class="post-comment-container" data-cid="<?php echo $this->cid; ?>">
                        <!-- 点赞列表 -->
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

                        <!-- 评论列表 -->
                        <?php if ($this->allow('comment')): ?>
                        <div class="pcc-comment-list">
                            <?php
                            // 使用与文章详情页相同的评论显示方式
                            $comments = getPostLatestCommentsWithReplies($this->cid, 20);
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
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <!-- 子页面列表 -->
            <?php if ($this->is('page') && $this->hasChildren): ?>
            <div class="sub-pages">
                <h3>子页面</h3>
                <ul class="sub-pages-list">
                    <?php $this->widget('Widget_Contents_Page_List')->to($pages); ?>
                    <?php while($pages->next()): ?>
                        <?php if ($pages->parent == $this->cid): ?>
                            <li>
                                <a href="<?php $pages->permalink(); ?>">
                                    <?php $pages->title(); ?>
                                </a>
                                <?php if ($pages->description): ?>
                                    <span class="page-description"><?php $pages->description(); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php $this->need('components/modals/setting.php'); ?>
    <?php $this->need('components/modals/editor.php'); ?>
</main>

<?php $this->need('footer.php'); ?>
