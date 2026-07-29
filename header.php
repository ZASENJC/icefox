<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<!DOCTYPE html>
<html lang="zh-CN" class="">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="renderer" content="webkit">
    <meta name="force-rendering" content="webkit">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <!-- SEO信息 -->
    <?php seoInfo($this); ?>

    <!-- 网站图标 -->
    <link rel="icon" href="<?php $this->options->themeUrl('favicon.ico'); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php $this->options->themeUrl('favicon.ico'); ?>" type="image/x-icon">

    <!-- RSS订阅 -->
    <link rel="alternate" type="application/rss+xml" title="<?php $this->options->title() ?> &raquo; RSS 2.0" href="<?php $this->options->feedUrl(); ?>">
    <link rel="alternate" type="application/atom+xml" title="<?php $this->options->title() ?> &raquo; ATOM 1.0" href="<?php $this->options->feedUrl('/atom'); ?>">

    <!-- 主题样式 -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/bulma.min.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/fancybox.css'); ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/css/icefox.css'); ?>">

    <!-- AlpineJS x-cloak 样式 -->
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- 主题模式初始化 (必须在页面渲染前执行,避免闪烁) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('icefox_theme_mode');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <!-- 全局配置（适配伪静态和非伪静态） -->
    <script>
        window.ICEFOX_CONFIG = <?php echo json_encode([
            'actionUrl' => Helper::security()->getIndex('/action/icefox'),
            'homeUrl' => Helper::options()->siteUrl,
            'albumUrl' => rtrim(trim((string) Helper::options()->albumPageUrl), '/') ?: Typecho_Common::url('albums', Helper::options()->index),
            'uploadStorage' => (string) Helper::options()->uploadStorage === 'object' ? 'object' : 'local'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        (function cleanPublishedRefreshParameter() {
            const currentUrl = new URL(window.location.href);
            if (!currentUrl.searchParams.has('icefox_published')) return;

            currentUrl.searchParams.delete('icefox_published');
            window.history.replaceState({}, document.title, currentUrl.toString());
        })();
    </script>

    <!-- Alpine.js 评论回复管理器 -->
    <script>
    // Emoji 数据
    const EMOJI_DATA = {
        '表情': ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥸', '🤩', '🥳'],
        '手势': ['👋', '🤚', '🖐', '✋', '🖖', '👌', '🤌', '🤏', '✌', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '🖕', '👇', '☝', '👍', '👎', '✊', '👊', '🤛', '🤜', '👏', '🙌', '👐', '🤲', '🤝', '🙏'],
        '动物': ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗'],
        '食物': ['🍎', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶', '🌽', '🥕', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨'],
        '活动': ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸', '🥌'],
        '符号': ['❤', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮', '✝', '☪', '🕉', '☸', '✡', '🔯', '🕎', '☯', '☦', '🛐', '⛎', '♈']
    };

    function commentReplyManager() {
        return {
            activeCommentId: null,
            replyForm: null,

            init() {
                // 添加点击空白处隐藏所有菜单的事件监听
                document.addEventListener('click', this.handleClickOutside.bind(this));
            },

            // 切换文章时间评论菜单显示状态
            togglePostTimeComment(event, postId) {
                event.preventDefault();
                event.stopPropagation();

                // 获取当前点击的菜单
                const currentModal = document.getElementById(`ptcm-${postId}`);
                if (!currentModal) return;

                const currentAlpineComponent = Alpine.$data(currentModal);
                if (!currentAlpineComponent) return;

                // 如果当前菜单是显示状态，则隐藏它
                if (currentAlpineComponent.ptcmShow) {
                    currentAlpineComponent.ptcmShow = false;
                    return;
                }

                // 隐藏所有其他菜单
                this.hideAllPostTimeCommentModals();

                // 显示当前菜单
                currentAlpineComponent.ptcmShow = true;
            },

            // 隐藏所有文章时间评论菜单
            hideAllPostTimeCommentModals() {
                const allModals = document.querySelectorAll('.post-time-comment');
                allModals.forEach(modal => {
                    const alpineComponent = Alpine.$data(modal);
                    if (alpineComponent && alpineComponent.ptcmShow !== undefined) {
                        alpineComponent.ptcmShow = false;
                    }
                });
            },

            showReplyForm(event, postId, coid, authorName) {
                event.preventDefault();

                // 获取点击的评论内容元素
                const clickedElement = event.target;

                // 找到对应的 pcc-comment-item 元素
                const commentItem = clickedElement.closest('.pcc-comment-item');

                if (!commentItem) {
                    console.error('未找到评论项元素');
                    return;
                }

                // 如果传递的coid为0，尝试从DOM元素中获取
                if (!coid || coid === '0') {
                    const domCoid = commentItem.dataset.commentId;
                    if (domCoid && domCoid !== '0') {
                        coid = domCoid;
                    }
                }

                // 生成唯一的表单ID
                const formId = `reply-form-${postId}-${Date.now()}`;

                // 如果点击的是同一个评论，则移除表单
                if (this.activeCommentId === commentItem) {
                    this.removeReplyForm();
                    this.activeCommentId = null;
                    return;
                }

                // 移除之前可能存在的表单
                this.removeReplyForm();

                // 创建回复表单
                const replyForm = this.createReplyForm(formId, postId, authorName, coid);

                // 在 pcc-comment-item 后面插入表单
                commentItem.parentNode.insertBefore(replyForm, commentItem.nextSibling);

                // 设置当前激活的评论项
                this.activeCommentId = commentItem;

                // 隐藏下拉菜单
                this.hidePostTimeCommentModal(postId);

                // 聚焦到输入框
                setTimeout(() => {
                    const input = replyForm.querySelector('input[type="text"]');
                    if (input) {
                        input.focus();
                    }
                }, 100);
            },

            showPostReplyForm(event, postId) {
                event.preventDefault();
                event.stopPropagation();

                // 移除之前可能存在的表单
                this.removeReplyForm();

                // 生成唯一的表单ID
                const formId = `post-reply-form-${postId}-${Date.now()}`;

                // 找到当前文章的评论容器
                const postItem = event.target.closest('.post-item');
                if (!postItem) {
                    console.error('未找到文章项元素');
                    return;
                }

                const commentContainer = postItem.querySelector('.post-comment-container');
                if (!commentContainer) {
                    console.error('未找到评论容器');
                    return;
                }

                // 创建文章回复表单
                const replyForm = this.createPostReplyForm(formId, postId);

                // 确保评论容器可见（当没有点赞和评论时容器可能被隐藏）
                commentContainer.style.display = '';

                // 在评论容器末尾添加表单
                commentContainer.appendChild(replyForm);

                // 设置当前激活的文章
                this.activeCommentId = `post-${postId}`;

                // 隐藏下拉菜单
                this.hidePostTimeCommentModal(postId);

                // 聚焦到输入框
                setTimeout(() => {
                    const input = replyForm.querySelector('input[type="text"]');
                    if (input) {
                        input.focus();
                    }
                }, 100);
            },

            createPostReplyForm(formId, postId) {
                const form = document.createElement('div');
                form.className = 'reply-form-container post-reply-form';
                form.id = formId;

                // 从本地存储读取用户信息
                const savedAuthor = localStorage.getItem('icefox_comment_author') || '';
                const savedEmail = localStorage.getItem('icefox_comment_email') || '';
                const savedUrl = localStorage.getItem('icefox_comment_url') || '';

                form.innerHTML = `
                    <div class="reply-form" x-data="{postReplyAuthor: '${this.escapeHtml(savedAuthor)}', postReplyEmail: '${this.escapeHtml(savedEmail)}', postReplyUrl: '${this.escapeHtml(savedUrl)}', postReplyContent: '', emojiPickerShow: false, currentEmojiTab: '表情'}">
                        <div class="reply-form-header">
                            <strong>发表评论</strong>
                            <button type="button" class="reply-form-close">×</button>
                        </div>
                        <form>
                            <div class="reply-form-user-info">
                                <div class="reply-form-input">
                                    <input type="text"
                                           name="author_name"
                                           placeholder="昵称"
                                           required
                                           x-model="postReplyAuthor">
                                </div>
                                <div class="reply-form-input">
                                    <input type="email"
                                           name="author_email"
                                           placeholder="邮箱"
                                           required
                                           x-model="postReplyEmail">
                                </div>
                                <div class="reply-form-input">
                                    <input type="url"
                                           name="author_url"
                                           placeholder="网址"
                                           x-model="postReplyUrl">
                                </div>
                            </div>
                            <div class="reply-form-input">
                                <input type="text"
                                       name="reply_content"
                                       placeholder="写下你的评论..."
                                       required
                                       x-model="postReplyContent">
                            </div>
                            <div class="reply-form-bottom">
                                <div class="reply-form-emoji-container">
                                    <button type="button"
                                            class="reply-form-emoji-toggle"
                                            @click.stop="emojiPickerShow = !emojiPickerShow">
                                        😀 <span>表情</span>
                                    </button>
                                    <div class="reply-form-emoji-picker"
                                         :class="{'show': emojiPickerShow}"
                                         @click.stop>
                                        <div class="emoji-picker-header">
                                            <span class="emoji-picker-title">选择表情</span>
                                            <button type="button"
                                                    class="emoji-picker-close"
                                                    @click="emojiPickerShow = false">×</button>
                                        </div>
                                        <div class="emoji-picker-tabs">
                                            ${Object.keys(EMOJI_DATA).map(tab => `
                                                <button type="button"
                                                        class="emoji-tab"
                                                        :class="{'active': currentEmojiTab === '${tab}'}"
                                                        @click="currentEmojiTab = '${tab}'">${tab}</button>
                                            `).join('')}
                                        </div>
                                        <div class="emoji-picker-content">
                                            ${Object.entries(EMOJI_DATA).map(([category, emojis]) =>
                                                emojis.map(emoji => `
                                                    <span class="emoji-item"
                                                          x-show="currentEmojiTab === '${category}'"
                                                          @click="postReplyContent += '${emoji}'; emojiPickerShow = false">${emoji}</span>
                                                `).join('')
                                            ).join('')}
                                        </div>
                                    </div>
                                </div>
                                <div class="reply-form-actions">
                                    <button type="submit" class="reply-submit-btn">
                                        发表评论
                                    </button>
                                    <button type="button"
                                            class="reply-cancel-btn">
                                        取消
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                `;

                // 添加事件监听器
                const closeBtn = form.querySelector('.reply-form-close');
                const cancelBtn = form.querySelector('.reply-cancel-btn');
                const submitForm = form.querySelector('form');

                closeBtn.addEventListener('click', () => {
                    this.removeReplyForm();
                });

                cancelBtn.addEventListener('click', () => {
                    this.removeReplyForm();
                });

                submitForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitPostReply(e, postId);
                });

                return form;
            },

            async submitPostReply(event, postId) {
                const form = event.target;
                const authorName = form.querySelector('input[name="author_name"]').value.trim();
                const authorEmail = form.querySelector('input[name="author_email"]').value.trim();
                const authorUrl = form.querySelector('input[name="author_url"]').value.trim();
                const content = form.querySelector('input[name="reply_content"]').value.trim();

                if (!authorName || !authorEmail || !content) {
                    alert('请填写必要信息');
                    return;
                }

                // 保存用户信息到本地存储
                localStorage.setItem('icefox_comment_author', authorName);
                localStorage.setItem('icefox_comment_email', authorEmail);
                localStorage.setItem('icefox_comment_url', authorUrl);

                // 禁用提交按钮，防止重复提交
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = '提交中...';

                try {
                    const response = await fetch(window.ICEFOX_PLUGIN.url(window.ICEFOX_PLUGIN.actions.addComment), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            author: authorName,
                            mail: authorEmail,
                            url: authorUrl || '',
                            text: content,
                            cid: postId,
                            coid: 0 // 顶级评论，coid为0
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // 提交成功，动态插入评论到列表
                        this.addCommentToList(postId, result.comment);
                        this.removeReplyForm();
                    } else {
                        // 提交失败
                        alert(result.message || '评论发表失败，请稍后重试');
                    }
                } catch (error) {
                    console.error('评论提交错误:', error);
                    alert('网络错误，请稍后重试');
                } finally {
                    // 恢复提交按钮
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            },

            createReplyForm(formId, postId, authorName, coid) {
                const form = document.createElement('div');
                form.className = 'reply-form-container';
                form.id = formId;
                // 存储被回复评论的ID
                form.dataset.coid = coid;

                // 从本地存储读取用户信息
                const savedAuthor = localStorage.getItem('icefox_comment_author') || '';
                const savedEmail = localStorage.getItem('icefox_comment_email') || '';
                const savedUrl = localStorage.getItem('icefox_comment_url') || '';

                form.innerHTML = `
                    <div class="reply-form" x-data="{replyAuthor: '${this.escapeHtml(savedAuthor)}', replyEmail: '${this.escapeHtml(savedEmail)}', replyUrl: '${this.escapeHtml(savedUrl)}', replyContent: '', emojiPickerShow: false, currentEmojiTab: '表情'}">
                        <div class="reply-form-header">
                            <strong>回复 ${authorName}</strong>
                            <button type="button" class="reply-form-close">×</button>
                        </div>
                        <form>
                            <div class="reply-form-user-info">
                                <div class="reply-form-input">
                                    <input type="text"
                                           name="author_name"
                                           placeholder="昵称"
                                           required
                                           x-model="replyAuthor">
                                </div>
                                <div class="reply-form-input">
                                    <input type="email"
                                           name="author_email"
                                           placeholder="邮箱"
                                           required
                                           x-model="replyEmail">
                                </div>
                                <div class="reply-form-input">
                                    <input type="url"
                                           name="author_url"
                                           placeholder="网址"
                                           x-model="replyUrl">
                                </div>
                            </div>
                            <div class="reply-form-input">
                                <input type="text"
                                       name="reply_content"
                                       placeholder="写下你的回复..."
                                       required
                                       x-model="replyContent">
                            </div>
                            <div class="reply-form-bottom">
                                <div class="reply-form-emoji-container">
                                    <button type="button"
                                            class="reply-form-emoji-toggle"
                                            @click.stop="emojiPickerShow = !emojiPickerShow">
                                        😀 <span>表情</span>
                                    </button>
                                    <div class="reply-form-emoji-picker"
                                         :class="{'show': emojiPickerShow}"
                                         @click.stop>
                                        <div class="emoji-picker-header">
                                            <span class="emoji-picker-title">选择表情</span>
                                            <button type="button"
                                                    class="emoji-picker-close"
                                                    @click="emojiPickerShow = false">×</button>
                                        </div>
                                        <div class="emoji-picker-tabs">
                                            ${Object.keys(EMOJI_DATA).map(tab => `
                                                <button type="button"
                                                        class="emoji-tab"
                                                        :class="{'active': currentEmojiTab === '${tab}'}"
                                                        @click="currentEmojiTab = '${tab}'">${tab}</button>
                                            `).join('')}
                                        </div>
                                        <div class="emoji-picker-content">
                                            ${Object.entries(EMOJI_DATA).map(([category, emojis]) =>
                                                emojis.map(emoji => `
                                                    <span class="emoji-item"
                                                          x-show="currentEmojiTab === '${category}'"
                                                          @click="replyContent += '${emoji}'; emojiPickerShow = false">${emoji}</span>
                                                `).join('')
                                            ).join('')}
                                        </div>
                                    </div>
                                </div>
                                <div class="reply-form-actions">
                                    <button type="submit" class="reply-submit-btn">
                                        发表回复
                                    </button>
                                    <button type="button"
                                            class="reply-cancel-btn">
                                        取消
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                `;

                // 添加事件监听器
                const closeBtn = form.querySelector('.reply-form-close');
                const cancelBtn = form.querySelector('.reply-cancel-btn');
                const submitForm = form.querySelector('form');

                closeBtn.addEventListener('click', () => {
                    this.removeReplyForm();
                });

                cancelBtn.addEventListener('click', () => {
                    this.removeReplyForm();
                });

                submitForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitReply(e, postId, authorName);
                });

                return form;
            },

            async submitReply(event, postId, originalAuthorName) {
                const form = event.target;
                const authorName = form.querySelector('input[name="author_name"]').value.trim();
                const authorEmail = form.querySelector('input[name="author_email"]').value.trim();
                const authorUrl = form.querySelector('input[name="author_url"]').value.trim();
                const content = form.querySelector('input[name="reply_content"]').value.trim();

                if (!authorName || !authorEmail || !content) {
                    alert('请填写必要信息');
                    return;
                }

                // 保存用户信息到本地存储
                localStorage.setItem('icefox_comment_author', authorName);
                localStorage.setItem('icefox_comment_email', authorEmail);
                localStorage.setItem('icefox_comment_url', authorUrl);

                // 获取被回复评论的ID（从.reply-form-container元素上获取）
                const formContainer = form.closest('.reply-form-container');
                const coid = formContainer ? (formContainer.dataset.coid || 0) : 0;

                // 禁用提交按钮，防止重复提交
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = '提交中...';

                try {
                    const response = await fetch(window.ICEFOX_PLUGIN.url(window.ICEFOX_PLUGIN.actions.addComment), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            author: authorName,
                            mail: authorEmail,
                            url: authorUrl || '',
                            text: content,
                            cid: postId,
                            coid: coid // 回复的评论ID
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // 提交成功，动态插入回复到列表
                        this.addCommentToList(postId, result.comment);
                        this.removeReplyForm();
                    } else {
                        // 提交失败
                        alert(result.message || '回复发表失败，请稍后重试');
                    }
                } catch (error) {
                    console.error('回复提交错误:', error);
                    alert('网络错误，请稍后重试');
                } finally {
                    // 恢复提交按钮
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            },

            removeReplyForm() {
                // 查找并移除所有回复表单
                const existingForms = document.querySelectorAll('.reply-form-container');
                existingForms.forEach(form => {
                    // 获取表单所在的评论容器，用于后续检查是否需要隐藏
                    const commentContainer = form.closest('.post-comment-container');
                    form.remove();

                    // 检查容器是否需要隐藏（没有点赞也没有评论时）
                    if (commentContainer) {
                        const likeList = commentContainer.querySelector('.pcc-like-list');
                        const commentList = commentContainer.querySelector('.pcc-comment-list');
                        const hasLikes = likeList && likeList.style.display !== 'none';
                        const hasComments = commentList && commentList.querySelectorAll('.pcc-comment-item').length > 0;

                        if (!hasLikes && !hasComments) {
                            commentContainer.style.display = 'none';
                        }
                    }
                });

                this.activeCommentId = null;
            },

            // 添加评论到列表
            addCommentToList(postId, commentData) {

                // 找到对应文章的评论列表容器
                const postItem = document.querySelector(`.post-item .pcc-comment-list`);
                if (!postItem) {
                    console.error('未找到评论列表容器');
                    return;
                }

                // 找到当前文章的评论列表（因为页面可能有多篇文章，需要精确定位）
                const allPostItems = document.querySelectorAll('.post-item');
                let targetCommentList = null;

                for (const item of allPostItems) {
                    // 通过检查评论容器中的 data-cid 属性来匹配对应的文章
                    const likeList = item.querySelector('.pcc-like-list');
                    if (likeList && likeList.dataset.cid == postId) {
                        targetCommentList = item.querySelector('.pcc-comment-list');
                        break;
                    }
                }

                if (!targetCommentList) {
                    console.error('未找到目标评论列表');
                    return;
                }

                // 生成头像URL（使用SHA256哈希邮箱）
                const getAvatarUrl = async (email) => {
                    const encoder = new TextEncoder();
                    const data = encoder.encode(email);
                    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                    return `https://weavatar.com/avatar/${hashHex}?s=64&d=identicon`;
                };

                // 创建新评论元素
                const commentItem = document.createElement('div');
                commentItem.className = 'pcc-comment-item';
                commentItem.dataset.commentId = commentData.coid;

                // 判断是否为管理员
                const isAdmin = commentData.userGroup === 'administrator';
                const authorBadge = isAdmin ? '<span class="author-badge">作者</span>' : '';

                // 判断是顶级评论还是回复评论
                if (!commentData.parent || commentData.parent == 0) {
                    // 顶级评论
                    commentItem.innerHTML = `
                        <a href="${commentData.url || '#'}">${this.escapeHtml(commentData.author)}</a>
                        ${authorBadge}
                        <span>:</span>
                        <span class="cursor-help pcc-comment-content"
                              @click="showReplyForm($event, '${postId}', '${commentData.coid}', '${this.escapeHtml(commentData.author)}')">${this.escapeHtml(commentData.text)}</span>
                    `;
                } else {
                    // 回复评论 - 需要获取被回复评论的作者
                    const parentComment = targetCommentList.querySelector(`[data-comment-id="${commentData.parent}"]`);
                    let parentAuthor = '原评论';
                    let parentUrl = '#';
                    let parentAuthorBadge = '';

                    if (parentComment) {
                        const parentLink = parentComment.querySelector('a');
                        if (parentLink) {
                            parentAuthor = parentLink.textContent;
                            parentUrl = parentLink.href;
                        }
                        // 检查父评论是否有作者铭牌
                        const parentBadge = parentComment.querySelector('.author-badge');
                        if (parentBadge) {
                            parentAuthorBadge = '<span class="author-badge">作者</span>';
                        }
                    }

                    commentItem.innerHTML = `
                        <a href="${commentData.url || '#'}">${this.escapeHtml(commentData.author)}</a>
                        ${authorBadge}
                        <span>回复</span>
                        <a href="${parentUrl}">${this.escapeHtml(parentAuthor)}</a>
                        ${parentAuthorBadge}
                        <span>:</span>
                        <span class="cursor-help pcc-comment-content"
                              @click="showReplyForm($event, '${postId}', '${commentData.coid}', '${this.escapeHtml(commentData.author)}')">${this.escapeHtml(commentData.text)}</span>
                    `;
                }

                // 根据评论类型决定插入位置
                if (!commentData.parent || commentData.parent == 0) {
                    // 顶级评论 - 插入到列表顶部
                    if (targetCommentList.firstChild) {
                        targetCommentList.insertBefore(commentItem, targetCommentList.firstChild);
                    } else {
                        targetCommentList.appendChild(commentItem);
                    }
                } else {
                    // 回复评论 - 插入到被回复评论的后面
                    const parentComment = targetCommentList.querySelector(`[data-comment-id="${commentData.parent}"]`);
                    if (parentComment) {
                        // 插入到父评论的下一个兄弟节点之前
                        if (parentComment.nextSibling) {
                            targetCommentList.insertBefore(commentItem, parentComment.nextSibling);
                        } else {
                            targetCommentList.appendChild(commentItem);
                        }
                    } else {
                        // 如果找不到父评论，插入到列表末尾
                        targetCommentList.appendChild(commentItem);
                    }
                }

                // 初始化新元素的 Alpine.js
                if (window.Alpine) {
                    Alpine.initTree(commentItem);
                }
            },

            // HTML转义函数，防止XSS攻击
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },

            // 隐藏指定文章时间评论下拉菜单
            hidePostTimeCommentModal(postId) {
                const modal = document.getElementById(`ptcm-${postId}`);
                if (modal) {
                    // 通过Alpine.js的数据绑定来隐藏
                    const alpineComponent = Alpine.$data(modal);
                    if (alpineComponent && alpineComponent.ptcmShow !== undefined) {
                        alpineComponent.ptcmShow = false;
                    }
                }
            },

            // 点击外部区域关闭表单和菜单
            handleClickOutside(event) {
                // 关闭回复表单
                if (this.activeCommentId && !event.target.closest('.reply-form-container') && !event.target.closest('.pcc-comment-content')) {
                    this.removeReplyForm();
                }

                // 关闭所有菜单
                if (!event.target.closest('.ptc-more') && !event.target.closest('.post-time-comment-modal')) {
                    this.hideAllPostTimeCommentModals();
                }
            }
        }
    }

    // 添加点击外部关闭功能
    document.addEventListener('click', function(event) {
        // 如果Alpine.js已经初始化，通过Alpine.js的数据来处理
        if (window.Alpine) {
            // Alpine.js会自动处理，这里不需要额外代码
        }
    });
    </script>

    <!-- 自定义CSS -->
    <?php if ($this->options->customCss): ?>
    <style>
        <?php $this->options->customCss(); ?>
    </style>
    <?php endif; ?>

    <script defer src="<?php $this->options->themeUrl('/assets/js/icefox-plugin.js?v=3.0.3-chunk1'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/jquery.min.js'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/alpinejs.js'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/fancybox.umd.js'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/scrollload.min.js'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/music-player.js'); ?>"></script>
    <script defer src="<?php $this->options->themeUrl('/assets/js/icefox.js'); ?>"></script>

    <?php if ($this->options->customJs): ?>
    <?php $customJs = (string) $this->options->customJs; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const customJs = <?php echo json_encode($customJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            try {
                new Function(customJs)();
            } catch (error) {
                console.warn('Icefox ignored invalid custom JavaScript:', error.message);
            }
        }, { once: true });
    </script>
    <?php endif; ?>

    <!-- Typecho主题API -->
    <?php $this->header(); ?>
</head>
<body>
