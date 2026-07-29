<?php
/**
 * 友情链接页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if ((string) $this->request->get('icefox_links_api', '') === '1') {
    icefoxHandleFriendLinksRequest($this);
}

$friendLinksUser = \Widget\User::alloc();
$friendLinks = icefoxFriendLinksRead($this->cid);
$friendLinksCanEdit = $friendLinksUser->hasLogin();

$this->need('header.php');
?>

<main class="links-page">
    <?php $this->need('components/head.php'); ?>

    <section class="content-container">
        <div class="links-page-content" x-cloak
             x-data="icefoxFriendLinksManager({
                 mode: 'page',
                 initialLinks: <?php echo htmlspecialchars(json_encode($friendLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>,
                 canEdit: <?php echo $friendLinksCanEdit ? 'true' : 'false'; ?>
             })"
             x-init="initializePage()">
            <header class="links-page-header">
                <div>
                    <h1><?php $this->title(); ?></h1>
                    <p>记录一路相遇的朋友与站点</p>
                </div>
                <button type="button" class="links-primary-button" x-show="canEdit && !editorOpen" @click="startCreate()">
                    添加友链
                </button>
            </header>

            <?php if (trim((string) $this->content) !== ''): ?>
                <div class="links-page-description"><?php echo themeContent($this); ?></div>
            <?php endif; ?>

            <div class="links-status links-loading" x-show="loading && links.length === 0">
                <span class="links-spinner" aria-hidden="true"></span>
                <p>加载中...</p>
            </div>

            <div class="links-status links-error" x-show="error && links.length === 0">
                <p x-text="error"></p>
                <button type="button" class="links-secondary-button" @click="loadLinks()">重新加载</button>
            </div>

            <div class="links-notice" x-show="notice" x-text="notice"></div>

            <div class="links-list" x-show="!editorOpen && links.length > 0">
                <template x-for="link in links" :key="link.id">
                    <article class="link-item">
                        <a class="link-main" :href="link.url" target="_blank" rel="noopener noreferrer">
                            <span class="link-avatar">
                                <template x-if="link.avatar">
                                    <img :src="link.avatar" :alt="link.name" loading="lazy" @error="clearBrokenAvatar(link)">
                                </template>
                                <template x-if="!link.avatar">
                                    <span class="link-avatar-fallback" x-text="avatarInitial(link)"></span>
                                </template>
                            </span>
                            <span class="link-info">
                                <span class="link-name" x-text="link.name"></span>
                                <span class="link-description" x-text="link.description || link.url"></span>
                            </span>
                            <span class="link-arrow" aria-hidden="true">›</span>
                        </a>
                        <button type="button" class="link-edit-button" x-show="canEdit" :aria-label="`编辑${link.name}`" @click="startEdit(link)">编辑</button>
                    </article>
                </template>
            </div>

            <div class="links-status links-empty" x-show="!loading && !error && !editorOpen && links.length === 0">
                <div class="links-empty-icon" aria-hidden="true">↗</div>
                <h2>暂无友情链接</h2>
                <p x-text="canEdit ? '添加第一位朋友吧' : '站长还没有添加友情链接'"></p>
            </div>

            <form class="links-editor" x-show="editorOpen" @submit.prevent="saveLink()">
                <div class="links-editor-heading">
                    <div>
                        <h2 x-text="form.id ? '编辑友情链接' : '添加友情链接'"></h2>
                        <p>名称和链接地址为必填项</p>
                    </div>
                    <button type="button" class="links-secondary-button" @click="closeEditor()">返回列表</button>
                </div>

                <label class="links-field">
                    <span>名称</span>
                    <input type="text" x-ref="nameInput" x-model="form.name" maxlength="100" placeholder="朋友或站点名称" required>
                </label>
                <label class="links-field">
                    <span>链接地址</span>
                    <input type="url" x-model="form.url" maxlength="500" placeholder="https://example.com" required>
                </label>
                <label class="links-field">
                    <span>头像地址</span>
                    <input type="url" x-model="form.avatar" maxlength="500" placeholder="可选，留空显示名称首字">
                </label>
                <label class="links-field">
                    <span>描述</span>
                    <textarea x-model="form.description" maxlength="200" rows="3" placeholder="简单介绍一下这位朋友"></textarea>
                </label>
                <label class="links-field links-sort-field">
                    <span>排序</span>
                    <input type="number" x-model="form.sort" min="0" step="1">
                    <small>数字越小越靠前</small>
                </label>

                <div class="links-form-error" x-show="formError" x-text="formError"></div>
                <div class="links-editor-actions">
                    <button type="button" class="links-danger-button" x-show="form.id" :disabled="isSubmitting" @click="deleteLink()">删除</button>
                    <span class="links-editor-actions-spacer"></span>
                    <button type="button" class="links-secondary-button" :disabled="isSubmitting" @click="closeEditor()">取消</button>
                    <button type="submit" class="links-primary-button" :disabled="isSubmitting" x-text="isSubmitting ? '保存中...' : '保存'"></button>
                </div>
            </form>
        </div>
    </section>

    <?php $this->need('components/modals/setting.php'); ?>
    <?php $this->need('components/modals/editor.php'); ?>
</main>

<?php $this->need('footer.php'); ?>
