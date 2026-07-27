$(function () {
    printCopyright();
    // 初始化Fancybox
    Fancybox.bind("[data-fancybox]", {
        Thumbs: {
            autoStart: false // 不显示底部缩略图
        },
        Toolbar: {
            display: {
                left: ["infobar"],
                middle: ["zoomIn", "zoomOut", "toggle1to1", "rotateCCW", "rotateCW", "flipX", "flipY"],
                right: ["slideshow", "thumbs", "close"],
            },
        },
        // 自定义配置
        loop: true,
        keyboard: {
            Escape: "close",
            Delete: "close",
            Backspace: "close",
            PageUp: "next",
            PageDown: "prev",
            ArrowUp: "next",
            ArrowDown: "prev",
            ArrowRight: "next",
            ArrowLeft: "prev",
        },
    });

    // 初始化点赞功能
    initLikes();

    // 初始化无限滚动功能
    initInfiniteScroll();

    // 顶部容器滚动背景色变化功能
    const $topContainer = $('.top-container');
    const scrollThreshold = 264; // 滚动阈值
    let lastScrollState = false; // 记录上一次的滚动状态

    // 切换图标函数 - 使用预加载的SVG内容
    function toggleIcons(isScrolled) {
        $('.tc-user, .tc-music, .tc-edit, .tc-setting').each(function () {
            const $iconContainer = $(this);
            const iconType = $iconContainer.data('icon');
            const newIconType = isScrolled ? iconType + '-outline' : iconType;

            // 从预加载的图标中获取内容
            const $preloadedIcon = $(`.preloaded-icons [data-icon="${newIconType}"]`);
            if ($preloadedIcon.length) {
                $iconContainer.html($preloadedIcon.html());
            }
        });
    }

    // 监听滚动事件
    $(window).scroll(function () {
        const scrollTop = $(this).scrollTop();
        const isScrolled = scrollTop > scrollThreshold;

        // 只有当滚动状态发生变化时才执行操作
        if (isScrolled !== lastScrollState) {
            if (isScrolled) {
                // 向下滚动超过阈值，添加背景色并切换图标
                $topContainer.addClass('scrolled');
                toggleIcons(true);
            } else {
                // 向上滚动小于阈值，移除背景色并恢复图标
                $topContainer.removeClass('scrolled');
                toggleIcons(false);
            }

            // 更新上一次的滚动状态
            lastScrollState = isScrolled;
        }
    });

    // 页面加载时检查一次滚动位置
    $(window).trigger('scroll');

    // 全文按钮点击事件
    $(document).on('click', '.show_all_btn', function() {
        const $btn = $(this);
        const cid = $btn.data('cid');
        const $summary = $('.summary-' + cid);
        const $fullContent = $('.full_content-' + cid);

        // 显示全文内容，隐藏摘要
        $summary.addClass('hidden');
        $fullContent.removeClass('hidden');
    });

    // 收起按钮点击事件
    $(document).on('click', '.hide_all_btn', function() {
        const $btn = $(this);
        const cid = $btn.data('cid');
        const $summary = $('.summary-' + cid);
        const $fullContent = $('.full_content-' + cid);

        // 隐藏全文内容，显示摘要
        $summary.removeClass('hidden');
        $fullContent.addClass('hidden');
    });

    // 初始化回到顶部功能
    initBackToTop();
});

// 无限滚动功能
function initInfiniteScroll() {
    // 检查是否有无限滚动容器
    if (!$('.scrollload-container').length) {
        return;
    }

    // 获取分页信息
    const $pagination = $('.pagination');
    const $currentPageEl = $('.current-page');
    const $totalPagesEl = $('.total-pages');

    if (!$pagination.length || !$currentPageEl.length || !$totalPagesEl.length) {
        return;
    }

    // 判断页面类型（首页还是归档页）
    const isArchivePage = $('.archive-header').length > 0 || window.location.pathname.includes('/search/') || window.location.pathname.includes('/category/') || window.location.pathname.includes('/tag/') || window.location.pathname.includes('/author/');

    // 判断是否为作者页面（特殊处理，格式为 /author/{uid}/{page}/）
    const isAuthorPage = window.location.pathname.includes('/author/');

    // 从URL中获取当前页码，如果没有找到则默认为1
    let currentPage = 1;
    const currentPath = window.location.pathname;

    if (isAuthorPage) {
        // 作者页面：格式为 /author/{uid}/ 或 /author/{uid}/{page}/
        const authorPageMatch = currentPath.match(/\/author\/\d+\/(\d+)\/?$/);
        if (authorPageMatch) {
            currentPage = parseInt(authorPageMatch[1]);
        }
        // 如果没有匹配到第二个数字，说明是第一页
    } else if (isArchivePage) {
        // 其他归档页面：匹配 /数字/ 格式 (如 /search/keyword/2/)
        const archivePageMatch = currentPath.match(/\/(\d+)\/?$/);
        if (archivePageMatch) {
            currentPage = parseInt(archivePageMatch[1]);
        }
    } else {
        // 首页：匹配 /page/数字/ 格式
        const homePageMatch = currentPath.match(/\/page\/(\d+)\/?$/);
        if (homePageMatch) {
            currentPage = parseInt(homePageMatch[1]);
        }
    }
    
    // 如果URL解析失败，尝试从数据属性获取
    if (currentPage === 1) {
        const dataPage = $currentPageEl.data('page');
        if (dataPage) {
            currentPage = parseInt(dataPage);
        }
    }

    const totalPages = parseInt($totalPagesEl.data('total'));

    // 如果已经在最后一页，不需要初始化无限滚动
    if (currentPage >= totalPages) {
        return;
    }

    const postSelector = '.post-item';

    // 初始化ScrollLoad
    const scrollload = new Scrollload({
        container: document.querySelector('.scrollload-container'),
        content: document.querySelector('.scrollload-content'),
        threshold: 100, // 提前100px开始加载
        loadingHtml: `
            <div class="scrollload-loading">
                <div class="loading-spinner"></div>
                <span>正在加载更多内容...</span>
            </div>
        `,
        noMoreDataHtml: `
            <div class="scrollload-nomore">
                <span>没有更多内容了</span>
            </div>
        `,
        exceptionHtml: `
            <div class="scrollload-error">
                <span>加载失败，请稍后重试</span>
                <button class="retry-btn" onclick="location.reload()">重新加载</button>
            </div>
        `,
        loadMore: function(sl) {
            currentPage++;
            // 检查是否超出总页数
            if (currentPage > totalPages) {
                sl.noMoreData();
                return;
            }
            loadNextPage(currentPage, sl, postSelector, isArchivePage, isAuthorPage, totalPages);
        }
    });
}

// 加载下一页内容
function loadNextPage(page, scrollloadInstance, postSelector, isArchivePage, isAuthorPage, totalPages) {
    // 双重检查：确保不超出总页数
    if (page > totalPages) {
        scrollloadInstance.noMoreData();
        return;
    }
    // 构建下一页URL - 区分首页和归档页的分页格式
    let nextPageUrl;
    const currentPath = window.location.pathname;
    const currentSearch = window.location.search;

    if (isAuthorPage) {
        // 作者页面：格式为 /author/{uid}/ 或 /author/{uid}/{page}/
        // 需要保留 /author/{uid}/ 部分，只移除分页数字
        let cleanPath = currentPath.replace(/\/author\/(\d+)\/\d+\/?$/, '/author/$1/');

        // 如果没有匹配到分页数字（即当前是第一页），保持原路径
        if (cleanPath === currentPath) {
            cleanPath = currentPath.replace(/\/?$/, '/');
        }

        if (page === 1) {
            nextPageUrl = cleanPath + currentSearch;
        } else {
            nextPageUrl = cleanPath + page + '/' + currentSearch;
        }
    } else if (isArchivePage) {
        // 其他归档页面：移除现有的数字分页路径 (如 /2/, /3/)
        let cleanPath = currentPath.replace(/\/\d+\/?$/, '');

        // 确保路径以 / 结尾，除非是根路径
        if (cleanPath !== '' && !cleanPath.endsWith('/')) {
            cleanPath += '/';
        }

        if (page === 1) {
            // 第一页不需要分页路径
            nextPageUrl = cleanPath + currentSearch;
        } else {
            // 归档页分页格式：直接添加数字，如 /search/keyword/2/
            nextPageUrl = cleanPath + page + '/' + currentSearch;
        }
    } else {
        // 首页：移除现有的 /page/数字 分页路径
        let cleanPath = currentPath.replace(/\/page\/\d+\/?$/, '');
        
        // 确保路径以 / 结尾，除非是根路径
        if (cleanPath !== '' && !cleanPath.endsWith('/')) {
            cleanPath += '/';
        }

        if (page === 1) {
            // 第一页不需要分页路径
            nextPageUrl = cleanPath + currentSearch;
        } else {
            // 首页分页格式：/page/2/
            nextPageUrl = cleanPath + 'page/' + page + '/' + currentSearch;
        }
    }

    $.ajax({
        url: nextPageUrl,
        type: 'GET',
        dataType: 'html',
        success: function(response) {
            try {
                // 解析返回的HTML
                const $response = $(response);
                const $newPosts = $response.find('.scrollload-content ' + postSelector);

                if ($newPosts.length === 0) {
                    // 没有更多内容
                    scrollloadInstance.noMoreData();
                    return;
                }
                
                // 将新内容添加到现有列表中
                const $content = $('.scrollload-content');
                $newPosts.each(function() {
                    // 重新绑定Alpine.js数据
                    const $newItem = $(this).appendTo($content);
                    // 触发Alpine.js初始化
                    if (window.Alpine) {
                        window.Alpine.initTree($newItem[0]);
                    }

                    // 初始化新文章的点赞数据
                    const $likeContainer = $newItem.find('.pcc-like-list');
                    if ($likeContainer.length) {
                        const cid = $likeContainer.data('cid');
                        if (cid) {
                            loadLikeData(cid, $likeContainer);
                        }
                    }

                    // 初始化新加载的音乐卡片
                    const $musicPlayers = $newItem.find('[data-music-player]');
                    if ($musicPlayers.length && window.IcefoxMusicManager) {
                        $musicPlayers.each(function() {
                            // 检查是否已经初始化过
                            if (!this.dataset.musicPlayerInitialized) {
                                const player = new MusicPlayer(this);
                                window.IcefoxMusicManager.register(player);
                            }
                        });
                    }
                });

                // 重新初始化Fancybox
                Fancybox.bind("[data-fancybox]", {
                    Thumbs: { autoStart: false },
                    Toolbar: {
                        display: {
                            left: ["infobar"],
                            middle: ["zoomIn", "zoomOut", "toggle1to1", "rotateCCW", "rotateCW", "flipX", "flipY"],
                            right: ["slideshow", "thumbs", "close"],
                        },
                    },
                    loop: true,
                    keyboard: {
                        Escape: "close", Delete: "close", Backspace: "close",
                        PageUp: "next", PageDown: "prev",
                        ArrowUp: "next", ArrowDown: "prev",
                        ArrowRight: "next", ArrowLeft: "prev",
                    },
                });

                // 检查是否还有下一页
                const $newPagination = $response.find('.total-pages');
                if ($newPagination.length) {
                    const newTotalPages = parseInt($newPagination.data('total'));
                    if (page >= newTotalPages) {
                        scrollloadInstance.noMoreData();
                        return;
                    }
                }

                // 继续允许加载
                scrollloadInstance.unLock();

            } catch (error) {
                scrollloadInstance.throwException();
            }
        },
        error: function(xhr, status, error) {
            // 如果是404错误，说明页面不存在，直接显示没有更多数据
            if (xhr.status === 404) {
                scrollloadInstance.noMoreData();
            } else {
                scrollloadInstance.throwException();
            }
        }
    });
}

// 获取或生成匿名用户ID
function getAnonymousId() {
    let anonymousId = localStorage.getItem('icefox_anonymous_id');
    if (!anonymousId) {
        // 生成唯一ID（使用时间戳 + 随机数）
        anonymousId = 'anon_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('icefox_anonymous_id', anonymousId);
    }
    return anonymousId;
}

// 初始化点赞功能
function initLikes() {
    // 确保匿名ID已生成
    getAnonymousId();

    // 初始化时先隐藏所有点赞列表,等数据加载后再决定是否显示
    $('.pcc-like-list').hide();

    // 检查并隐藏没有评论的容器
    $('.post-comment-container').each(function() {
        const $commentContainer = $(this);
        const $commentList = $commentContainer.find('.pcc-comment-list');
        const hasComments = $commentList.find('.pcc-comment-item').length > 0;

        // 如果没有评论,先隐藏整个容器,等点赞数据加载后再决定是否显示
        if (!hasComments) {
            $commentContainer.hide();
        }
    });

    // 获取页面上所有文章的点赞数据
    const $likeLists = $('.pcc-like-list');

    $likeLists.each(function() {
        const $likeContainer = $(this);
        const cid = $likeContainer.data('cid');
        if (cid) {
            loadLikeData(cid, $likeContainer);
        }
    });

    // 绑定点赞列表的点击事件
    $(document).on('click', '.pcc-like-list', function(e) {
        e.stopPropagation();
        const cid = $(this).data('cid');
        if (cid) {
            doToggleLike(cid, $(this));
        }
    });
}

// 加载点赞数据
function loadLikeData(cid, $container) {
    const anonymousId = getAnonymousId();

    // 获取评论用户信息(如果用户已经评论过)
    const commentAuthor = localStorage.getItem('icefox_comment_author') || '';
    const commentEmail = localStorage.getItem('icefox_comment_email') || '';

    let url = window.ICEFOX_CONFIG.actionUrl + '?do=getLikes&cid=' + cid + '&anonymous_id=' + encodeURIComponent(anonymousId);

    // 如果有评论用户信息,携带到请求中
    if (commentAuthor && commentEmail) {
        url += '&comment_author=' + encodeURIComponent(commentAuthor) + '&comment_email=' + encodeURIComponent(commentEmail);
    }

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateLikeUI($container, response.likes, response.isLiked, response.likeUsers || []);
            }
        }
    });
}

// 切换点赞状态
function doToggleLike(cid, $container) {
    // 防止重复点击
    if ($container.hasClass('liking')) {
        return;
    }

    $container.addClass('liking');

    const anonymousId = getAnonymousId();

    // 获取评论用户信息(如果用户已经评论过)
    const commentAuthor = localStorage.getItem('icefox_comment_author') || '';
    const commentEmail = localStorage.getItem('icefox_comment_email') || '';

    let url = window.ICEFOX_CONFIG.actionUrl + '?do=like&cid=' + cid + '&anonymous_id=' + encodeURIComponent(anonymousId);

    // 如果有评论用户信息,携带到请求中
    if (commentAuthor && commentEmail) {
        url += '&comment_author=' + encodeURIComponent(commentAuthor) + '&comment_email=' + encodeURIComponent(commentEmail);
    }

    $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateLikeUI($container, response.likes, response.isLiked, response.likeUsers || []);
            }
        },
        complete: function() {
            $container.removeClass('liking');
        }
    });
}

// 更新点赞UI
function updateLikeUI($container, likes, isLiked, likeUsers) {
    const cid = $container.data('cid');

    // 获取父容器 post-comment-container
    const $commentContainer = $container.closest('.post-comment-container');

    // 确保 likes 是数字类型
    likes = parseInt(likes) || 0;

    // 如果没有点赞,隐藏点赞列表
    if (likes === 0) {
        $container.hide();

        // 检查是否有评论,如果也没有评论则隐藏整个 post-comment-container
        const $commentList = $commentContainer.find('.pcc-comment-list');
        const hasComments = $commentList.find('.pcc-comment-item').length > 0;

        if (!hasComments) {
            $commentContainer.hide();
        }

        // 仍需更新菜单按钮状态
        const $menuBtn = $('.like-menu-btn[data-cid="' + cid + '"]');
        const $menuText = $menuBtn.find('.like-menu-text');
        const $menuIcon = $menuBtn.find('.like-menu-icon');
        $menuText.text('点赞');
        $menuIcon.attr('fill', 'none');
        $menuIcon.css('color', '');
        return;
    }

    // 有点赞时显示点赞列表和父容器
    $container.show();
    $commentContainer.show();

    // 更新点赞列表的图标样式
    const $icon = $container.find('.like-icon');
    if (isLiked) {
        // 已点赞 - 填充红色
        $icon.attr('fill', 'currentColor');
        $icon.css('color', '#ff6b6b');
    } else {
        // 未点赞 - 空心
        $icon.attr('fill', 'none');
        $icon.css('color', '');
    }

    // 更新点赞文本
    const $usersText = $container.find('.like-users-text');
    const likesText = generateLikesText(likes, likeUsers);
    $usersText.text(likesText);

    // 更新菜单中的点赞按钮文本和图标
    const $menuBtn = $('.like-menu-btn[data-cid="' + cid + '"]');
    const $menuText = $menuBtn.find('.like-menu-text');
    const $menuIcon = $menuBtn.find('.like-menu-icon');

    if (isLiked) {
        // 已点赞 - 显示"取消点赞"和红色图标
        $menuText.text('取消点赞');
        $menuIcon.attr('fill', 'currentColor');
        $menuIcon.css('color', '#ff6b6b');
    } else {
        // 未点赞 - 显示"点赞"和空心图标
        $menuText.text('点赞');
        $menuIcon.attr('fill', 'none');
        $menuIcon.css('color', '');
    }
}

// 生成点赞文本
function generateLikesText(likes, likeUsers) {
    if (likes === 0) {
        return '0 个点赞';
    }

    if (!likeUsers || likeUsers.length === 0) {
        return likes + ' 个点赞';
    }

    // 显示前3个用户名
    const displayCount = Math.min(3, likeUsers.length);
    const names = likeUsers.slice(0, displayCount).map(user => user.author).join('、');

    // 格式：昵称1、昵称2、昵称3 X个点赞
    return names + '、' + likes + '个点赞';
}

// Alpine.js 中的点赞函数（从菜单点击）
window.toggleLike = function(event, cid) {
    event.stopPropagation();
    const $container = $('.pcc-like-list[data-cid="' + cid + '"]');
    if ($container.length) {
        doToggleLike(cid, $container);
    }
};
function printCopyright() {
    console.log('%cIcefox主题 By xiaopanglian v3.0.3 %chttps://www.xiaopanglian.com', 'color: white;  background-color: #99cc99; padding: 10px;', 'color: white; background-color: #ff6666; padding: 10px;');
}
/**
 * 回到顶部功能
 */
function initBackToTop() {
    const $backToTop = $('#backToTop');
    const showThreshold = 320; // 滚动超过320px时显示按钮

    // 监听滚动事件
    $(window).on('scroll', function() {
        const scrollTop = $(window).scrollTop();

        if (scrollTop > showThreshold) {
            $backToTop.addClass('show');
        } else {
            $backToTop.removeClass('show');
        }
    });

    // 点击按钮回到顶部
    $backToTop.on('click', function() {
        $('html, body').animate({
            scrollTop: 0
        }, 600, 'linear', function() {
            // 动画完成后隐藏按钮
            $backToTop.removeClass('show');
        });
    });
}