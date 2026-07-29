<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>

<aside class="sidebar">
    <!-- 个人信息 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">关于博主</h3>
        <div class="widget-content">
            <div class="author-info">
                <?php if ($this->options->avatar): ?>
                    <img src="<?php $this->options->avatar() ?>" alt="<?php $this->author->screenName(); ?>" class="author-avatar">
                <?php endif; ?>
                <h4><?php $this->author->screenName(); ?></h4>
                <p><?php $this->author->description(); ?></p>
                <div class="author-social">
                    <?php if ($this->options->github): ?>
                        <a href="<?php $this->options->github() ?>" target="_blank" title="GitHub">
                            <i class="icon-github">GitHub</i>
                        </a>
                    <?php endif; ?>
                    <?php if ($this->options->weibo): ?>
                        <a href="<?php $this->options->weibo() ?>" target="_blank" title="微博">
                            <i class="icon-weibo">微博</i>
                        </a>
                    <?php endif; ?>
                    <?php if ($this->options->twitter): ?>
                        <a href="<?php $this->options->twitter() ?>" target="_blank" title="Twitter">
                            <i class="icon-twitter">Twitter</i>
                        </a>
                    <?php endif; ?>
                    <a href="<?php $this->author->permalink(); ?>" title="个人主页">
                        <i class="icon-home">主页</i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 搜索框 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">搜索文章</h3>
        <div class="widget-content">
            <?php echo getSearchForm(); ?>
        </div>
    </div>

    <!-- 分类目录 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">分类目录</h3>
        <div class="widget-content">
            <ul>
                <?php $this->widget('Widget_Metas_Category_List')->to($categories); ?>
                <?php while($categories->next()): ?>
                    <li>
                        <a href="<?php $categories->permalink(); ?>">
                            <?php $categories->name(); ?>
                            <span class="category-count">(<?php $categories->count(); ?>)</span>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <!-- 标签云 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">标签云</h3>
        <div class="widget-content">
            <div class="tag-cloud">
                <?php $this->widget('Widget_Metas_Tag_Cloud', array('sort' => 'count', 'ignoreZeroCount' => true, 'desc' => true, 'limit' => 20))->to($tags); ?>
                <?php while($tags->next()): ?>
                    <a href="<?php $tags->permalink(); ?>" style="font-size: <?php echo log($tags->count + 1) * 10 + 10; ?>px;">
                        <?php $tags->name(); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- 最新文章 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">最新文章</h3>
        <div class="widget-content">
            <ul>
                <?php $this->widget('Widget_Contents_Post_Recent')->to($recent); ?>
                <?php while($recent->next()): ?>
                    <li>
                        <a href="<?php $recent->permalink(); ?>">
                            <?php $recent->title(25); ?>
                        </a>
                        <span class="post-date"><?php echo date('m-d', $recent->created); ?></span>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <!-- 热门文章 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">热门文章</h3>
        <div class="widget-content">
            <ul>
                <?php $popularPosts = getPopularPosts(5); ?>
                <?php if (!empty($popularPosts)): ?>
                    <?php foreach ($popularPosts as $post): ?>
                        <li>
                            <a href="<?php echo $post['permalink']; ?>">
                                <?php echo $post['title']; ?>
                            </a>
                            <span class="post-views"><?php echo $post['views']; ?> 阅读</span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>暂无热门文章</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- 最新评论 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">最新评论</h3>
        <div class="widget-content">
            <ul>
                <?php $this->widget('Widget_Comments_Recent')->to($comments); ?>
                <?php while($comments->next()): ?>
                    <li>
                        <div class="recent-comment">
                            <div class="comment-author"><?php $comments->author(); ?>:</div>
                            <div class="comment-text">
                                <a href="<?php $comments->permalink(); ?>">
                                    <?php $comments->excerpt(25); ?>
                                </a>
                            </div>
                            <div class="comment-meta">
                                <span class="comment-time"><?php echo date('m-d H:i', $comments->created); ?></span>
                            </div>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <!-- 文章归档 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">文章归档</h3>
        <div class="widget-content">
            <ul>
                <?php $this->widget('Widget_Contents_Post_Date', 'type=month&format=Y年m月')->to($archives); ?>
                <?php while($archives->next()): ?>
                    <li>
                        <a href="<?php $archives->permalink(); ?>">
                            <?php $archives->date(); ?>
                            <span class="archive-count">(<?php $archives->count(); ?>)</span>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <!-- 网站统计 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">网站统计</h3>
        <div class="widget-content">
            <div class="site-stats">
                <?php $stats = getSiteStats(); ?>
                <p>文章总数: <strong><?php echo $stats['posts']; ?></strong></p>
                <p>评论总数: <strong><?php echo $stats['comments']; ?></strong></p>
                <p>分类总数: <strong><?php echo $stats['categories']; ?></strong></p>
                <p>标签总数: <strong><?php echo $this->widget('Widget_Metas_Tag_Count')->count; ?></strong></p>
                <p>最后更新: <strong><?php echo date('Y-m-d H:i', $this->widget('Widget_Contents_Post_Recent')->created); ?></strong></p>
                <p>运行天数: <strong><?php echo floor((time() - strtotime($this->widget('Widget_Contents_Post_Recent')->created)) / 86400); ?></strong> 天</p>
            </div>
        </div>
    </div>

    <!-- RSS订阅 -->
    <div class="sidebar-widget">
        <h3 class="widget-title">RSS订阅</h3>
        <div class="widget-content">
            <div class="rss-subscribe">
                <p>订阅本站文章更新</p>
                <a href="<?php $this->options->feedUrl(); ?>" class="rss-link" target="_blank">
                    <i class="icon-rss">RSS</i> 订阅
                </a>
                <a href="<?php $this->options->feedUrl('/atom'); ?>" class="atom-link" target="_blank">
                    <i class="icon-atom">Atom</i> 订阅
                </a>
            </div>
        </div>
    </div>

    <!-- 自定义HTML -->
    <?php if ($this->options->customHtml): ?>
    <div class="sidebar-widget">
        <h3 class="widget-title">自定义内容</h3>
        <div class="widget-content">
            <?php $this->options->customHtml(); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 二维码 -->
    <?php if ($this->options->qrcode): ?>
    <div class="sidebar-widget">
        <h3 class="widget-title">扫码关注</h3>
        <div class="widget-content">
            <div class="qrcode">
                <img src="<?php $this->options->qrcode(); ?>" alt="二维码">
                <p>扫码关注博主</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 公告 -->
    <?php if ($this->options->announcement): ?>
    <div class="sidebar-widget">
        <h3 class="widget-title">网站公告</h3>
        <div class="widget-content">
            <div class="announcement">
                <?php $this->options->announcement(); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</aside>

<style>
/* 侧边栏样式增强 */
.author-info {
    text-align: center;
}

.author-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 10px;
}

.author-info h4 {
    margin: 10px 0 5px;
    color: #333;
}

.author-info p {
    font-size: 14px;
    color: #666;
    margin-bottom: 15px;
}

.author-social {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.author-social a {
    display: inline-block;
    padding: 5px 10px;
    background-color: #f8f9fa;
    border-radius: 3px;
    color: #666;
    text-decoration: none;
    transition: all 0.3s ease;
}

.author-social a:hover {
    background-color: #007bff;
    color: white;
}

.recent-comment {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.recent-comment:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.comment-author {
    font-weight: bold;
    color: #333;
    margin-bottom: 3px;
}

.comment-text {
    font-size: 14px;
    color: #666;
    margin-bottom: 3px;
}

.comment-meta {
    font-size: 12px;
    color: #999;
}

.category-count,
.archive-count {
    font-size: 12px;
    color: #999;
}

.post-date,
.post-views {
    font-size: 12px;
    color: #999;
}

.site-stats p {
    margin-bottom: 8px;
    font-size: 14px;
}

.site-stats strong {
    color: #007bff;
}

.rss-subscribe {
    text-align: center;
}

.rss-link,
.atom-link {
    display: inline-block;
    padding: 8px 16px;
    margin: 5px;
    background-color: #ff8c00;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.rss-link:hover,
.atom-link:hover {
    background-color: #ff6600;
}

.qrcode {
    text-align: center;
}

.qrcode img {
    max-width: 150px;
    height: auto;
    margin-bottom: 10px;
}

.qrcode p {
    font-size: 12px;
    color: #666;
}

.announcement {
    font-size: 14px;
    line-height: 1.6;
    color: #666;
}

.tag-cloud a {
    display: inline-block;
    padding: 2px 8px;
    margin: 2px;
    background-color: #f8f9fa;
    border-radius: 3px;
    color: #666;
    text-decoration: none;
    transition: all 0.3s ease;
}

.tag-cloud a:hover {
    background-color: #007bff;
    color: white;
}

/* 深色模式支持 */
@media (prefers-color-scheme: dark) {
    .author-info h4,
    .site-stats p {
        color: #ffffff;
    }
    
    .author-info p,
    .comment-text,
    .comment-meta,
    .category-count,
    .archive-count,
    .post-date,
    .post-views {
        color: #b0b0b0;
    }
    
    .author-social a {
        background-color: #3d3d3d;
        color: #b0b0b0;
    }
    
    .author-social a:hover {
        background-color: #4a9eff;
        color: white;
    }
    
    .recent-comment {
        border-bottom-color: #555;
    }
    
    .tag-cloud a {
        background-color: #3d3d3d;
        color: #b0b0b0;
    }
    
    .tag-cloud a:hover {
        background-color: #4a9eff;
        color: white;
    }
    
    .rss-link,
    .atom-link {
        background-color: #ff6600;
    }
    
    .rss-link:hover,
    .atom-link:hover {
        background-color: #ff8c00;
    }
}
</style>
