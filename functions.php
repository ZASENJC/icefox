<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox主题全新3.0版本
 *
 * @package icefox
 * @author 小胖脸
 * @version 3.0.3
 * @link https://xiaopanglian.com
 */

include_once 'comment_function.php';
include_once 'core/core.php';
include_once __TYPECHO_ROOT_DIR__ . '/var/Utils/Markdown.php';

/**
 * 主题初始化
 */
function themeInit($archive)
{
    // 设置默认模板
    $archive->template = 'index';

    // 设置每页显示文章数量
    $options = Helper::options();
    if ($options->pageSize) {
        $archive->parameter->pageSize = $options->pageSize;
    }
}

/**
 * 主题配置
 */
function themeConfig($form)
{
    // 站点顶部背景视频
    $topVideo = new Typecho_Widget_Helper_Form_Element_Text('topVideo', NULL, NULL, _t('站点顶部背景视频'), _t('在这里填入一个视频URL地址,优先级高于背景图片'));
    $form->addInput($topVideo);

    // 站点顶部背景图片
    $topImage = new Typecho_Widget_Helper_Form_Element_Text('topImage', NULL, NULL, _t('站点顶部背景图片'), _t('在这里填入一个图片URL地址,当没有设置背景视频时显示'));
    $form->addInput($topImage);

    // 站点 Logo 地址
    $logoUrl = new Typecho_Widget_Helper_Form_Element_Text('logoUrl', NULL, NULL, _t('站点 Logo 地址'), _t('在这里填入站点 Logo 图片的 URL 地址，显示在头部用户信息区域'));
    $form->addInput($logoUrl);

    // 头像点击跳转链接
    $avatarLink = new Typecho_Widget_Helper_Form_Element_Text('avatarLink', NULL, NULL, _t('头像点击跳转链接'), _t('在这里填入头像点击后的跳转地址，留空则不跳转。例如: /archives 或完整URL'));
    $form->addInput($avatarLink);

    // 网站备案信息
    $beianInfo = new Typecho_Widget_Helper_Form_Element_Text('beianInfo', NULL, NULL, _t('网站备案信息'), _t('在这里填入网站备案号，例如：蜀ICP备2000101010号。将显示在设置弹窗的版权信息处，点击自动跳转至工信部备案网站'));
    $form->addInput($beianInfo);

    // Gravatar 头像加速地址
    $gravatarUrl = new Typecho_Widget_Helper_Form_Element_Text('gravatarUrl', NULL, 'https://www.weavatar.com', _t('Gravatar 头像加速地址'), _t('用于替换 Gravatar 官方地址（http://www.gravatar.com），提升国内访问速度。默认使用 WeAvatar 加速服务（https://www.weavatar.com）。其他可选服务：<br>- https://gravatar.loli.net (Loli.net)<br>- https://cravatar.cn (Cravatar)<br>- https://dn-qiniu-avatar.qbox.me (七牛)<br>留空则使用默认值'));
    $form->addInput($gravatarUrl);

    // 自定义CSS
    $customCss = new Typecho_Widget_Helper_Form_Element_Textarea('customCss', NULL, NULL, _t('自定义CSS'), _t('在这里填入CSS代码'));
    $form->addInput($customCss);

    // 自定义JavaScript
    $customJs = new Typecho_Widget_Helper_Form_Element_Textarea('customJs', NULL, NULL, _t('自定义JavaScript'), _t('在这里填入JavaScript代码'));
    $form->addInput($customJs);

    // 统计代码
    $analytics = new Typecho_Widget_Helper_Form_Element_Textarea('analytics', NULL, NULL, _t('统计代码'), _t('在这里填入网站统计代码'));
    $form->addInput($analytics);

    // 文章发布页地址
    $editPageUrl = new Typecho_Widget_Helper_Form_Element_Text('editPageUrl', NULL, '/edit.html', _t('文章发布页地址'), _t('设置文章发布页面的访问地址，默认为 /edit.html'));
    $form->addInput($editPageUrl);

    // 是否自动收起内容
    $autoCollapse = new Typecho_Widget_Helper_Form_Element_Radio(
        'autoCollapse',
        array(
            '1' => _t('是（默认显示摘要，点击展开全文）'),
            '0' => _t('否（直接显示完整内容）')
        ),
        '1',
        _t('自动收起文章内容'),
        _t('设置列表页是否自动截取内容为摘要。选择"是"则显示摘要和展开按钮；选择"否"则直接显示完整内容。')
    );
    $form->addInput($autoCollapse);
}

function themeFields($layout)
{
    ?>
    <style>
        textarea {
            width: 100%;
            height: 8rem;
        }

        input[type=text] {
            width: 100%;
        }
    </style>
    <?php
    $position = new Typecho_Widget_Helper_Form_Element_Text(
        'position',
        null,
        null,
        _t('发布定位'),
        _t('<span>在这里填定位名称（例：成都市·天府广场）</span>')
    );
    $position->input->setAttribute('class', 't-default-find');
    $layout->addItem($position);

    $positionUrl = new Typecho_Widget_Helper_Form_Element_Text(
        'positionUrl',
        null,
        null,
        _t('定位跳转地址')
    );
    $positionUrl->input->setAttribute('class', 't-default-find');
    $layout->addItem($positionUrl);
}

/**
 * 获取 Gravatar 头像 URL（支持加速地址）
 */
function getGravatarUrl($email, $size = 80, $default = 'identicon', $rating = 'g')
{
    $options = \Widget\Options::alloc();

    // 获取配置的 Gravatar 加速地址，如果未配置则使用默认值
    $gravatarBase = $options->gravatarUrl;
    if (empty($gravatarBase)) {
        $gravatarBase = 'https://www.weavatar.com';
    }

    // 移除末尾的斜杠
    $gravatarBase = rtrim($gravatarBase, '/');

    // 生成头像 URL
    $hash = md5(strtolower(trim($email)));
    $url = $gravatarBase . '/avatar/' . $hash . '?s=' . $size . '&d=' . $default . '&r=' . $rating;

    return $url;
}

/**
 * 获取文章摘要
 */
function getExcerpt($content, $length = 200)
{
    // 定义允许的HTML标签
    $allowed_tags = '<p><a><br><strong><em><ul><ol><li><blockquote><code><pre><h1><h2><h3><h4><h5><h6>';

    // 保留允许的HTML标签，去除其他标签
    $content = strip_tags($content, $allowed_tags);

    // 处理不同格式的换行标签
    $content = str_replace('<br/>', '<br>', $content);
    $content = str_replace('<br />', '<br>', $content);

    $content = mb_substr($content, 0, $length, 'utf-8');
    return $content . '...';
}

/**
 * 获取文章浏览量
 */
function getPostViews($archive)
{
    $views = $archive->views;
    if (empty($views)) {
        $views = 0;
    }
    return $views;
}

/**
 * 时间显示
 */
function showTime($time)
{
    if (date('Y-m-d') == date('Y-m-d', $time)) {
        return '今天 ' . date('H:i', $time);
    } elseif (date('Y-m-d', strtotime('-1 day')) == date('Y-m-d', $time)) {
        return '昨天 ' . date('H:i', $time);
    } else {
        return date('Y-m-d H:i', $time);
    }
}

/**
 * 获取文章分类
 */
function getCategories($archive)
{
    $categories = $archive->categories;
    if (empty($categories)) {
        return '';
    }

    $result = '';
    foreach ($categories as $category) {
        $result .= '<a href="' . $category['permalink'] . '" class="category">' . $category['name'] . '</a>';
    }

    return $result;
}

/**
 * 获取文章标签
 */
function getTags($archive)
{
    $tags = $archive->tags;
    if (empty($tags)) {
        return '';
    }

    $result = '';
    foreach ($tags as $tag) {
        $result .= '<a href="' . $tag['permalink'] . '" class="tag">' . $tag['name'] . '</a>';
    }

    return $result;
}

/**
 * 获取文章缩略图
 */
function getThumbnail($archive)
{
    // 检查文章是否有自定义字段thumbnail
    $thumbnail = $archive->fields->thumbnail;
    if ($thumbnail) {
        return $thumbnail;
    }

    // 如果没有，尝试从文章内容中提取第一张图片
    $content = $archive->content;
    preg_match('/<img.*?src="(.*?)".*?>/i', $content, $matches);
    if (isset($matches[1])) {
        return $matches[1];
    }

    // 如果都没有，返回默认图片
    return '';
}

/**
 * 获取评论数
 */
function getCommentCount($archive)
{
    return $archive->commentsNum;
}

/**
 * 获取作者信息
 */
function getAuthor($archive)
{
    return $archive->author;
}

/**
 * 获取上一篇文章
 */
function getPrevPost($archive)
{
    return $archive->widget('Widget_Contents_Post_Previous');
}

/**
 * 获取下一篇文章
 */
function getNextPost($archive)
{
    return $archive->widget('Widget_Contents_Post_Next');
}

/**
 * 获取相关文章
 */
function getRelatedPosts($archive, $limit = 5)
{
    $db = Typecho_Db::get();
    $post = $archive->cid;

    // 获取当前文章的分类
    $categories = $archive->categories;
    if (empty($categories)) {
        return array();
    }

    $categoryIds = array();
    foreach ($categories as $category) {
        $categoryIds[] = $category['mid'];
    }

    // 查询相关文章
    $relatedPosts = $db->fetchAll($db->select()
        ->from('table.contents')
        ->where('table.contents.cid <> ?', $post)
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', 'post')
        ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
        ->where('table.relationships.mid IN ?', $categoryIds)
        ->order('table.contents.created', Typecho_Db::SORT_DESC)
        ->limit($limit));

    return $relatedPosts;
}

/**
 * 获取热门文章
 */
function getPopularPosts($limit = 5)
{
    $db = Typecho_Db::get();

    $posts = $db->fetchAll($db->select()
        ->from('table.contents')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', 'post')
        ->order('table.contents.views', Typecho_Db::SORT_DESC)
        ->limit($limit));

    return $posts;
}

/**
 * 获取最新评论
 */
function getRecentComments($limit = 5)
{
    $db = Typecho_Db::get();

    $comments = $db->fetchAll($db->select()
        ->from('table.comments')
        ->where('table.comments.status = ?', 'approved')
        ->where('table.comments.type = ?', 'comment')
        ->order('table.comments.created', Typecho_Db::SORT_DESC)
        ->limit($limit));

    return $comments;
}

/**
 * 获取分类列表
 */
function getCategoryList()
{
    $db = Typecho_Db::get();

    $categories = $db->fetchAll($db->select()
        ->from('table.metas')
        ->where('table.metas.type = ?', 'category')
        ->order('table.metas.order', Typecho_Db::SORT_ASC));

    return $categories;
}

/**
 * 获取标签云
 */
function getTagCloud($limit = 20)
{
    $db = Typecho_Db::get();

    $tags = $db->fetchAll($db->select()
        ->from('table.metas')
        ->where('table.metas.type = ?', 'tag')
        ->order('table.metas.count', Typecho_Db::SORT_DESC)
        ->limit($limit));

    return $tags;
}

/**
 * 获取归档列表
 */
function getArchiveList()
{
    $db = Typecho_Db::get();

    $archives = $db->fetchAll($db->select()
        ->from('table.contents')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', 'post')
        ->order('table.contents.created', Typecho_Db::SORT_DESC));

    return $archives;
}

/**
 * 获取搜索表单
 */
function getSearchForm()
{
    return '<form method="post" action="" class="search-form">
        <input type="text" name="s" placeholder="搜索..." required>
        <button type="submit">搜索</button>
    </form>';
}

/**
 * 获取导航菜单
 */
function getNavigationMenu()
{
    $db = Typecho_Db::get();

    $pages = $db->fetchAll($db->select()
        ->from('table.contents')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', 'page')
        ->order('table.contents.order', Typecho_Db::SORT_ASC));

    return $pages;
}

/**
 * 输出分页导航
 */
function pageNav($archive)
{
    if ($archive->getTotal() > $archive->parameter->pageSize) {
        echo '<div class="page-nav">';
        echo $archive->pageNav('&laquo;', '&raquo;', 1, '...', array('wrapTag' => 'span', 'wrapClass' => 'page-links', 'itemTag' => '', 'currentClass' => 'current'));
        echo '</div>';
    }
}

/**
 * 输出评论列表
 */
function comments($comments, $options)
{
    $commentLevelClass = 'comment-child';
    if ($comments->levels > 0) {
        echo '<div class="comment-' . $comments->levels . '">';
    }

    $comments->listComments();

    if ($comments->children) {
        echo '<div class="comment-children">';
        foreach ($comments->children as $child) {
            comments($child, $options);
        }
        echo '</div>';
    }

    if ($comments->levels > 0) {
        echo '</div>';
    }
}

/**
 * 自定义评论样式
 */
function singleComment($comment, $article)
{
    $commentClass = '';
    if ($comment->authorId) {
        if ($comment->authorId == $article->author->uid) {
            $commentClass = 'comment-by-author';
        }
    }

    echo '<li id="comment-' . $comment->coid . '" class="comment ' . $commentClass . '">';
    echo '<div class="comment-avatar">';
    echo '<img src="' . $comment->gravatar . '" alt="' . $comment->author . '" width="32" height="32">';
    echo '</div>';
    echo '<div class="comment-content">';
    echo '<div class="comment-meta">';
    echo '<span class="comment-author">' . $comment->author . '</span>';
    echo '<span class="comment-time">' . date('Y-m-d H:i', $comment->created) . '</span>';
    echo '</div>';
    echo '<div class="comment-text">';
    echo $comment->content;
    echo '</div>';
    echo '<div class="comment-reply">';
    echo '<a href="#comment-form" onclick="return TypechoComment.reply(\'' . $comment->coid . '\', \'' . $comment->coid . '\');">回复</a>';
    echo '</div>';
    echo '</div>';
    echo '</li>';
}

/**
 * 输出评论表单
 */
function commentForm($comments, $options)
{
    echo '<div id="comment-form" class="comment-form">';
    echo '<form method="post" action="' . $options->index . '/action/comments-post">';
    echo '<div class="form-group">';
    echo '<label for="author">昵称</label>';
    echo '<input type="text" name="author" id="author" value="' . $comments->remember('author') . '" required>';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<label for="mail">邮箱</label>';
    echo '<input type="email" name="mail" id="mail" value="' . $comments->remember('mail') . '" required>';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<label for="url">网站</label>';
    echo '<input type="url" name="url" id="url" value="' . $comments->remember('url') . '">';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<label for="textarea">评论内容</label>';
    echo '<textarea name="text" id="textarea" rows="5" required></textarea>';
    echo '</div>';
    echo '<div class="form-group">';
    echo '<button type="submit" class="submit-btn">发表评论</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

/**
 * 获取网站统计信息
 */
function getSiteStats()
{
    $db = Typecho_Db::get();

    // 获取文章总数
    $postCount = $db->fetchObject($db->select(array('COUNT(cid)' => 'total'))
        ->from('table.contents')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.type = ?', 'post'))->total;

    // 获取评论总数
    $commentCount = $db->fetchObject($db->select(array('COUNT(coid)' => 'total'))
        ->from('table.comments')
        ->where('table.comments.status = ?', 'approved'))->total;

    // 获取分类总数
    $categoryCount = $db->fetchObject($db->select(array('COUNT(mid)' => 'total'))
        ->from('table.metas')
        ->where('table.metas.type = ?', 'category'))->total;

    return array(
        'posts' => $postCount,
        'comments' => $commentCount,
        'categories' => $categoryCount
    );
}

/**
 * 输出面包屑导航
 */
function breadcrumbs($archive)
{
    echo '<div class="breadcrumbs">';
    echo '<a href="' . $archive->options->siteUrl . '">首页</a>';

    if ($archive->is('index')) {
        // 首页不需要面包屑
    } elseif ($archive->is('category')) {
        echo ' &raquo; <a href="' . $archive->category['permalink'] . '">' . $archive->category['name'] . '</a>';
    } elseif ($archive->is('tag')) {
        echo ' &raquo; <a href="' . $archive->tag['permalink'] . '">' . $archive->tag['name'] . '</a>';
    } elseif ($archive->is('search')) {
        echo ' &raquo; 搜索: ' . htmlspecialchars($archive->keywords);
    } elseif ($archive->is('post') || $archive->is('page')) {
        if ($archive->categories) {
            foreach ($archive->categories as $category) {
                echo ' &raquo; <a href="' . $category['permalink'] . '">' . $category['name'] . '</a>';
            }
        }
        echo ' &raquo; ' . $archive->title;
    } elseif ($archive->is('archive')) {
        echo ' &raquo; 归档: ' . $archive->date('Y年m月');
    }

    echo '</div>';
}

/**
 * 输出SEO信息
 */
function seoInfo($archive)
{
    $options = Helper::options();

    if ($archive->is('index')) {
        $title = $options->title;
        $description = $options->description;
        $keywords = $options->keywords;
    } elseif ($archive->is('post') || $archive->is('page')) {
        $title = $archive->title . ' - ' . $options->title;
        // 清理文章摘要：移除HTML标签、换行符，并截取前150个字符
        $description = strip_tags($archive->excerpt);
        $description = preg_replace('/\s+/', ' ', $description); // 将多个空白字符替换为单个空格
        $description = trim($description);
        $description = mb_substr($description, 0, 150, 'UTF-8');
        $keywords = '';
        if ($archive->tags) {
            foreach ($archive->tags as $tag) {
                $keywords .= $tag['name'] . ',';
            }
        }
        $keywords = rtrim($keywords, ',');
    } else {
        // 归档页面：根据不同类型生成标题
        $archiveTitle = '';
        if ($archive->is('category')) {
            $archiveTitle = '分类 ' . $archive->getDescription() . ' 下的文章';
        } elseif ($archive->is('tag')) {
            $archiveTitle = '标签 ' . $archive->getDescription() . ' 下的文章';
        } elseif ($archive->is('author')) {
            $archiveTitle = $archive->getDescription() . ' 发布的文章';
        } elseif ($archive->is('search')) {
            // 尝试多种方式获取搜索关键词
            $searchKeywords = '';
            
            // 调试：检查所有可能的搜索参数来源
            $request = $archive->request;
            
            if ($request) {
                $searchKeywords = $request->get('keywords', '');
                if (empty($searchKeywords)) {
                    $searchKeywords = $request->get('s', '');
                }
            }
            
            // 如果还是空，尝试从$_GET获取
            if (empty($searchKeywords) && isset($_GET['keywords'])) {
                $searchKeywords = $_GET['keywords'];
            }
            if (empty($searchKeywords) && isset($_GET['s'])) {
                $searchKeywords = $_GET['s'];
            }
            
            $archiveTitle = '包含关键字 ' . $searchKeywords . ' 的文章';
        } else {
            // 使用默认归档标题
            $archiveTitle = '归档';
        }
        
        $title = $archiveTitle . ' - ' . $options->title;
        $description = $options->description;
        $keywords = $options->keywords;
    }

    // 转义所有输出内容，防止HTML注入
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    $keywords = htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8');

    echo '<title>' . $title . '</title>' . "\n";
    echo '    <meta name="description" content="' . $description . '">' . "\n";
    echo '    <meta name="keywords" content="' . $keywords . '">' . "\n";

    // Open Graph
    echo '    <meta property="og:title" content="' . $title . '">' . "\n";
    echo '    <meta property="og:description" content="' . $description . '">' . "\n";
    echo '    <meta property="og:type" content="website">' . "\n";
    echo '    <meta property="og:url" content="' . htmlspecialchars($archive->permalink, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <meta property="og:site_name" content="' . htmlspecialchars($options->title, ENT_QUOTES, 'UTF-8') . '">' . "\n";

    // Twitter Card
    echo '    <meta name="twitter:card" content="summary">' . "\n";
    echo '    <meta name="twitter:title" content="' . $title . '">' . "\n";
    echo '    <meta name="twitter:description" content="' . $description . '">';
}

/**
 * 主题激活时执行
 */
function themeActivate()
{
    // 创建自定义字段
    $db = Typecho_Db::get();

    // 检查是否已存在thumbnail字段
    $exists = $db->fetchRow($db->select()->from('table.fields')->where('name = ?', 'thumbnail'));
    if (!$exists) {
        $db->query($db->insert('table.fields')->rows(array(
            'name' => 'thumbnail',
            'type' => 'str',
            'title' => '缩略图',
            'description' => '文章缩略图URL'
        )));
    }
//
//    // 添加主题设置主菜单
//    $menuIndex = Helper::addMenu('icefox-theme');
//
//    // 使用返回的索引添加子面板
//    Helper::addPanel($menuIndex, 'admin/theme.php', '主题设置', '配置主题', 'administrator');
}

/**
 * 主题停用时执行
 */
function themeDeactivate()
{
//    // 清理自定义数据
//    $menuIndex = Helper::removeMenu('icefox-theme');
//
//    // 使用返回的索引删除子面板
//    Helper::removePanel($menuIndex, 'admin/theme.php');
}

/**
 * 主题升级时执行
 */
function themeUpgrade()
{
    // 执行升级操作
}

// 注册主题激活/停用钩子
//Helper::addPanel(1, 'IceFox/panel.php', 'IceFox主题设置', 'IceFox主题设置', 'administrator');
//Helper::addRoute('icefox_api', '/icefox/api', 'IceFox_Action');
//Helper::removePanel(1,'IceFox/panel.php');

/**
 * 归档页面辅助函数
 */

/**
 * 获取全站文章总数
 */
function getTotalPostCount()
{
    $db = Typecho_Db::get();
    $count = $db->fetchObject($db->select(array('COUNT(cid)' => 'count'))->from('table.contents')->where('status = ? AND type = ?', 'publish', 'post'));
    return $count->count;
}

/**
 * 获取全站分类总数
 */
function getTotalCategoryCount()
{
    $db = Typecho_Db::get();
    $count = $db->fetchObject($db->select(array('COUNT(mid)' => 'count'))->from('table.metas')->where('type = ?', 'category'));
    return $count->count;
}

/**
 * 获取全站标签总数
 */
function getTotalTagCount()
{
    $db = Typecho_Db::get();
    $count = $db->fetchObject($db->select(array('COUNT(mid)' => 'count'))->from('table.metas')->where('type = ?', 'tag'));
    return $count->count;
}

/**
 * 获取全站评论总数
 */
function getTotalCommentCount()
{
    $db = Typecho_Db::get();
    $count = $db->fetchObject($db->select(array('COUNT(coid)' => 'count'))->from('table.comments')->where('status = ?', 'approved'));
    return $count->count;
}

/**
 * 获取微信朋友圈风格的时间轴归档
 */
function getArchiveTimelineMoments()
{
    $db = Typecho_Db::get();
    $posts = $db->fetchAll($db->select('cid', 'title', 'created', 'slug', 'text')->from('table.contents')
        ->where('status = ? AND type = ?', 'publish', 'post')
        ->order('created', Typecho_Db::SORT_DESC));

    if (empty($posts)) {
        return '<div class="moments-empty">
            <div class="empty-icon">📝</div>
            <p>还没有发布文章</p>
        </div>';
    }

    $moments = '<div class="moments-container">';
    $currentDate = '';

    foreach ($posts as $post) {
        $year = date('Y', $post['created']);

        // 年份标题（仅在年份变化时显示）
        if ($currentDate !== $year) {
            $currentDate = $year;
            $moments .= '<div style="color:#333;"><span style="font-weight:bold;font-size:2rem;">' . $currentDate . '</span>年</div>';
        }
        $dateDay = '<span class="mg-day">' . date('d', $post['created']) . '</span>';
        $dateLabel = '<date>' . date('m月', $post['created']) . '</date>';

        // 获取文章内容预览，支持音乐卡片
        $filtered = filterContent($post['text']);
        $cws = generateContentWithSummaryAndMusic($filtered, 100);
        $musicHtml = !empty($cws['music_shortcodes']) ? parseMusicShortcode($cws['music_shortcodes']) : '';
        $preview = $cws['summary'];

        $postUrl = Typecho_Router::url('post', $post);
        $time = date('H:i', $post['created']);

        // 图片
        $images = extractImageSrcs($post['text']);

        $moments .= '<div class="moments-group">';

        // 有图片时：图片在最左边
        if (count($images) > 0) {
            $moments .= '<div class="moment-avatar">';
            $moments .= '<a href="' . $postUrl . '"><img src="'.$images[0].'" alt="封面"></a>';
            $moments .= '</div>';
        }

        // 日期
        $moments .= '<div class="moments-date">' . $dateDay . $dateLabel . '</div>';

        // 内容区
        $moments .= '<div class="moment-content">';
        $hasImage = count($images) > 0;
        $moments .= '<div class="moment-body' . ($hasImage ? '' : ' moment-body-text') . '">';
        if ($musicHtml) {
            $moments .= $musicHtml;
        } elseif ($preview) {
            $moments .= '<a href="' . $postUrl . '"><p class="moment-preview">' . filterContent($preview) . '</p></a>';
        }
        $moments .= '</div>';
        $moments .= '</div>';
        $moments .= '</div>'; // 关闭 moments-group

    }

    $moments .= '</div>'; // 关闭 moments-container
    return $moments;
}

/**
 * 获取分类网格视图
 */
function getArchiveCategoriesGrid()
{
    $db = Typecho_Db::get();
    $categories = $db->fetchAll($db->select('mid', 'name', 'slug', 'description')
        ->from('table.metas')
        ->where('type = ?', 'category')
        ->order('name', Typecho_Db::SORT_ASC));

    if (empty($categories)) {
        return '<div class="moments-empty">
            <div class="empty-icon">📁</div>
            <p>还没有分类</p>
        </div>';
    }

    $html = '<div class="categories-grid">';

    foreach ($categories as $category) {
        // 获取分类文章数量
        $count = $db->fetchObject($db->select(array('COUNT(cid)' => 'count'))
            ->from('table.relationships')
            ->where('mid = ?', $category['mid']))->count;

        $categoryUrl = Typecho_Router::url('category', $category);

        $html .= '<div class="category-card">';
        $html .= '<div class="category-icon">📁</div>';
        $html .= '<div class="category-info">';
        $html .= '<h3 class="category-name">' . htmlspecialchars($category['name']) . '</h3>';
        $html .= '<div class="category-count">' . $count . ' 篇文章</div>';
        if ($category['description']) {
            $html .= '<p class="category-description">' . htmlspecialchars($category['description']) . '</p>';
        }
        $html .= '</div>';
        $html .= '<div class="category-action">';
        $html .= '<a href="' . $categoryUrl . '" class="category-link">查看文章</a>';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * 获取标签云视图
 */
function getArchiveTagsCloud()
{
    $db = Typecho_Db::get();
    $tags = $db->fetchAll($db->select('mid', 'name', 'slug')
        ->from('table.metas')
        ->where('type = ?', 'tag')
        ->order('name', Typecho_Db::SORT_ASC));

    if (empty($tags)) {
        return '<div class="moments-empty">
            <div class="empty-icon">🏷️</div>
            <p>还没有标签</p>
        </div>';
    }

    // 获取每个标签的文章数量
    foreach ($tags as &$tag) {
        $count = $db->fetchObject($db->select(array('COUNT(cid)' => 'count'))
            ->from('table.relationships')
            ->where('mid = ?', $tag['mid']))->count;
        $tag['count'] = $count;
    }

    // 按文章数量排序
    usort($tags, function ($a, $b) {
        return $b['count'] - $a['count'];
    });

    $html = '<div class="tags-cloud">';

    $maxCount = !empty($tags) ? max(array_column($tags, 'count')) : 1;

    foreach ($tags as $tag) {
        $tagUrl = Typecho_Router::url('tag', $tag);

        // 根据文章数量确定标签大小
        $sizeRatio = $tag['count'] / $maxCount;
        $sizeClass = 'tag-size-small';
        if ($sizeRatio > 0.6) {
            $sizeClass = 'tag-size-large';
        } elseif ($sizeRatio > 0.3) {
            $sizeClass = 'tag-size-medium';
        }

        $html .= '<a href="' . $tagUrl . '" class="tag-chip ' . $sizeClass . '">';
        $html .= '<span class="tag-emoji">🏷️</span>';
        $html .= '<span class="tag-name">' . htmlspecialchars($tag['name']) . '</span>';
        $html .= '<span class="tag-count">' . $tag['count'] . '</span>';
        $html .= '</a>';
    }

    $html .= '</div>';
    return $html;
}

/**
 * 获取文章位置信息
 * @param object|int $archive 文章对象或cid
 * @return string 位置信息，如果没有则返回空字符串
 */
function getPostLocation($archive)
{
    $cid = is_object($archive) ? $archive->cid : intval($archive);
    if (empty($cid)) {
        return '';
    }

    $db = Typecho_Db::get();
    $field = $db->fetchRow($db->select('str_value')
        ->from('table.fields')
        ->where('cid = ?', $cid)
        ->where('name = ?', 'location'));

    return $field ? $field['str_value'] : '';
}

/**
 * 获取文章的自定义字段
 * @param object|int $archive 文章对象或cid
 * @param string $fieldName 字段名
 * @param string $type 字段类型 (str, int, float)
 * @return mixed 字段值
 */
function getPostField($archive, $fieldName, $type = 'str')
{
    $cid = is_object($archive) ? $archive->cid : intval($archive);
    if (empty($cid)) {
        return null;
    }

    $db = Typecho_Db::get();
    $valueColumn = $type . '_value';
    $field = $db->fetchRow($db->select($valueColumn)
        ->from('table.fields')
        ->where('cid = ?', $cid)
        ->where('name = ?', $fieldName));

    return $field ? $field[$valueColumn] : null;
}

/**
 * 获取文章的附件列表
 * @param object|int $archive 文章对象或cid
 * @return array 附件列表
 */
function getPostAttachments($archive)
{
    $cid = is_object($archive) ? $archive->cid : intval($archive);
    if (empty($cid)) {
        return [];
    }

    $db = Typecho_Db::get();
    $attachments = $db->fetchAll($db->select('cid', 'title', 'text')
        ->from('table.contents')
        ->where('type = ?', 'attachment')
        ->where('parent = ?', $cid)
        ->order('order', Typecho_Db::SORT_ASC));

    $result = [];
    foreach ($attachments as $attachment) {
        $data = @unserialize($attachment['text']);
        if ($data) {
            $result[] = [
                'cid' => $attachment['cid'],
                'name' => $data['name'] ?? $attachment['title'],
                'path' => $data['path'] ?? '',
                'type' => $data['type'] ?? 'image',
                'mime' => $data['mime'] ?? '',
                'size' => $data['size'] ?? 0
            ];
        }
    }

    return $result;
}

/**
 * 获取文章置顶状态
 * @param int $cid 文章ID
 * @return bool 是否置顶
 */
function getPostIsTop($cid)
{
    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();

    try {
        $result = $db->fetchRow(
            $db->select('is_top')
                ->from($prefix . 'icefox_archive')
                ->where('cid = ?', $cid)
        );

        return !empty($result) && $result['is_top'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 解析音乐短代码并渲染为 HTML
 *
 * @param string $content 文章内容
 * @return string 解析后的内容
 */
function parseMusicShortcode($content)
{
    // 正则匹配短代码
    $pattern = '/\[music\s+url=["\']([^"\']+)["\']\s+title=["\']([^"\']+)["\'](?:\s+artist=["\']([^"\']*)["\'])?(?:\s+cover=["\']([^"\']*)["\'])?\]/';

    $content = preg_replace_callback($pattern, function($matches) {
        $url = $matches[1];
        $title = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
        $artist = isset($matches[3]) && !empty($matches[3]) ? htmlspecialchars($matches[3], ENT_QUOTES, 'UTF-8') : '未知艺术家';
        $cover = isset($matches[4]) && !empty($matches[4])
            ? $matches[4]
            : Helper::options()->themeUrl . '/assets/images/default-music-cover.svg';

        // 生成音乐卡片 HTML
        ob_start();
        ?>
        <div class="music-card" data-music-player>
            <div class="media">
                <div class="media-left">
                    <figure class="image is-64x64">
                        <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $title; ?>">
                    </figure>
                </div>
                <div class="media-content">
                    <p class="title is-6"><?php echo $title; ?></p>
                    <p class="subtitle is-7"><?php echo $artist; ?></p>
                    <div class="music-controls">
                        <button class="button is-small play-btn" aria-label="播放">
                            <span class="icon play-icon">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M3 2l10 6-10 6z"/>
                                </svg>
                            </span>
                            <span class="icon pause-icon is-hidden">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M5 3h2v10H5zM9 3h2v10H9z"/>
                                </svg>
                            </span>
                        </button>
                        <div class="progress-wrapper">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                        </div>
                        <span class="time is-size-7">00:00 / 00:00</span>
                    </div>
                </div>
            </div>
            <audio preload="metadata">
                <source src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" type="audio/mpeg">
                您的浏览器不支持音频播放。
            </audio>
        </div>
        <?php
        return ob_get_clean();
    }, $content);

    return $content;
}

/**
 * 处理文章内容输出
 *
 * @param object $widget Widget 对象
 * @return string 处理后的内容
 */
function themeContent($widget)
{
    $content = $widget->content;

    // 解析音乐短代码
    $content = parseMusicShortcode($content);

    return $content;
}

/**
 * 提取内容中的所有音乐短代码
 *
 * @param string $content 原始内容
 * @return array ['shortcodes' => 短代码数组, 'content' => 移除短代码后的内容]
 */
function extractMusicShortcodes($content)
{
    $shortcodes = [];
    $pattern = '/\[music\s+url=["\']([^"\']+)["\']\s+title=["\']([^"\']+)["\'](?:\s+artist=["\']([^"\']*)["\'])?(?:\s+cover=["\']([^"\']*)["\'])?\]/';

    // 提取所有短代码
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $shortcodes[] = $match[0]; // 保存完整的短代码
    }

    // 从内容中移除所有音乐短代码
    $contentWithoutMusic = preg_replace($pattern, '', $content);

    return [
        'shortcodes' => $shortcodes,
        'content' => $contentWithoutMusic
    ];
}

/**
 * 生成带音乐短代码的摘要（音乐短代码不计入截断长度）
 *
 * @param string $full_content 完整内容
 * @param int $summary_length 摘要长度
 * @return array
 */
function generateContentWithSummaryAndMusic($full_content, $summary_length = 100)
{
    // 提取音乐短代码
    $extracted = extractMusicShortcodes($full_content);
    $musicShortcodes = $extracted['shortcodes'];
    $contentWithoutMusic = $extracted['content'];

    // 对不含音乐短代码的内容生成摘要
    $result = generateContentWithSummary($contentWithoutMusic, $summary_length);

    // 将音乐短代码作为单独字段返回
    if (!empty($musicShortcodes)) {
        $result['music_shortcodes'] = implode("\n\n", $musicShortcodes);
    } else {
        $result['music_shortcodes'] = '';
    }

    return $result;
}

/**
 * 添加音乐卡片编辑器按钮
 */
Typecho_Plugin::factory('admin/write-post.php')->bottom = function () {
    ?>
    <style>
    /* 音乐卡片弹框样式 */
    #music-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
    }
    #music-modal-content {
        background: white;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    #music-modal h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #333;
    }
    #music-modal input {
        width: 100%;
        margin-bottom: 15px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
    }
    #music-modal input:focus {
        outline: none;
        border-color: #467b96;
    }
    #music-modal .buttons {
        text-align: right;
        margin-top: 20px;
    }
    #music-modal button {
        padding: 10px 20px;
        margin-left: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    #music-modal .btn-primary {
        background: #467b96;
        color: white;
    }
    #music-modal .btn-primary:hover {
        background: #3a6579;
    }
    #music-modal .btn-cancel {
        background: #ddd;
        color: #333;
    }
    #music-modal .btn-cancel:hover {
        background: #ccc;
    }
    .wmd-music-button {
        background: none !important;
        width: auto !important;
        padding: 0 10px !important;
        line-height: 20px !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 5px !important;
    }
    .wmd-music-button:hover {
        background: rgba(0,0,0,0.05) !important;
    }
    </style>

    <!-- 音乐卡片弹框 -->
    <div id="music-modal">
        <div id="music-modal-content">
            <h3>🎵 插入音乐卡片</h3>
            <input type="text" id="music-url" placeholder="音乐地址 (必填) - 例如: https://example.com/music.mp3" />
            <input type="text" id="music-title" placeholder="歌曲名称 (必填) - 例如: 晴天" />
            <input type="text" id="music-artist" placeholder="演唱者 (可选) - 例如: 周杰伦" />
            <input type="text" id="music-cover" placeholder="封面图片地址 (可选) - 留空使用默认封面" />
            <div class="buttons">
                <button class="btn-cancel" onclick="closeMusicModal()">取消</button>
                <button class="btn-primary" onclick="insertMusicShortcode()">插入</button>
            </div>
        </div>
    </div>

    <script>
    // 添加编辑器按钮
    $(document).ready(function() {
        // 在 Typecho 编辑器工具栏添加按钮
        var toolbar = $('.wmd-button-row');
        if (toolbar.length > 0) {
            var musicBtn = $('<li class="wmd-button wmd-music-button" title="插入音乐卡片"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg></li>');
            musicBtn.on('click', openMusicModal);
            toolbar.append(musicBtn);
        }
    });

    function openMusicModal() {
        $('#music-modal').fadeIn(200);
        $('#music-url').focus();
    }

    function closeMusicModal() {
        $('#music-modal').fadeOut(200);
        // 清空输入框
        $('#music-url, #music-title, #music-artist, #music-cover').val('');
    }

    function insertMusicShortcode() {
        var url = $('#music-url').val().trim();
        var title = $('#music-title').val().trim();
        var artist = $('#music-artist').val().trim();
        var cover = $('#music-cover').val().trim();

        if (!url || !title) {
            alert('❌ 音乐地址和歌曲名称为必填项！');
            return;
        }

        // 简单验证URL格式
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            alert('❌ 音乐地址必须是完整的URL，以 http:// 或 https:// 开头');
            return;
        }

        // 构建短代码
        var shortcode = '[music url="' + url + '" title="' + title + '"';
        if (artist) shortcode += ' artist="' + artist + '"';
        if (cover) shortcode += ' cover="' + cover + '"';
        shortcode += ']';

        // 插入到编辑器
        var textarea = $('#text');
        if (textarea.length > 0) {
            var cursorPos = textarea[0].selectionStart;
            var content = textarea.val();
            var newContent = content.substring(0, cursorPos) + '\n\n' + shortcode + '\n\n' + content.substring(cursorPos);
            textarea.val(newContent);

            // 设置光标位置到短代码后面
            var newPos = cursorPos + shortcode.length + 4;
            textarea[0].setSelectionRange(newPos, newPos);
            textarea.focus();
        }

        // 关闭弹框
        closeMusicModal();
    }

    // 点击弹框外部关闭
    $(document).on('click', '#music-modal', function(e) {
        if (e.target.id === 'music-modal') {
            closeMusicModal();
        }
    });

    // ESC键关闭弹框
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#music-modal').is(':visible')) {
            closeMusicModal();
        }
    });
    </script>
    <?php
};

?>
