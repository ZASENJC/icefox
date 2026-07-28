<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$albumEditorUser = \Widget\User::alloc();
if (!$albumEditorUser->hasLogin()) return;
?>

<script>
function albumEditorManager() {
    return {
        albumEditorShow: false,
        albumId: '',
        albumName: '',
        cover: '',
        tags: '',
        address: '',
        visibility: 'public',
        isPinned: false,
        uploadOnly: false,
        mediaFiles: [],
        remotePhotoUrls: '',
        submitStatus: '',
        isSubmitting: false,

        get visibilityText() {
            return this.visibility === 'private' ? '私密' : '公开';
        },

        normalizePinnedValue(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        },

        openModal(event) {
            const detail = event.detail || {};
            const album = Object.prototype.hasOwnProperty.call(detail, 'album') ? (detail.album || {}) : detail;
            const pinnedValue = Object.prototype.hasOwnProperty.call(album, 'isPinned')
                ? album.isPinned
                : (Object.prototype.hasOwnProperty.call(album, 'pinned') ? album.pinned : album.is_pinned);
            this.uploadOnly = detail.uploadOnly === true;
            this.albumId = album.id || album.aid || album.albumId || album.slug || '';
            this.albumName = album.name || album.title || '';
            this.cover = album.cover || '';
            this.tags = Array.isArray(album.tags) ? album.tags.join(', ') : (album.tags || '');
            this.address = album.address || album.location || '';
            this.visibility = album.visibility === 'private' ? 'private' : 'public';
            this.isPinned = this.normalizePinnedValue(pinnedValue);
            this.mediaFiles = [];
            this.remotePhotoUrls = '';
            this.submitStatus = '';
            this.albumEditorShow = true;
            document.body.style.overflow = 'hidden';
        },

        closeModal() {
            if (this.isSubmitting) return;
            this.albumEditorShow = false;
            document.body.style.overflow = '';
        },

        handleMediaSelect(event) {
            const files = Array.from(event.target.files).filter(file => file.type.startsWith('image/'));
            const remaining = Math.max(0, 30 - this.mediaFiles.length - this.parseRemotePhotoUrls().length);
            files.slice(0, remaining).forEach(file => {
                const reader = new FileReader();
                reader.onload = loadEvent => this.mediaFiles.push({ file, preview: loadEvent.target.result });
                reader.readAsDataURL(file);
            });
            event.target.value = '';
        },

        removeMedia(index) {
            this.mediaFiles.splice(index, 1);
        },

        parseRemotePhotoUrls() {
            return this.remotePhotoUrls
                .split(/\r?\n/)
                .map(url => url.trim())
                .filter(Boolean);
        },

        isRemotePhotoUrlValid(value) {
            try {
                const url = new URL(value);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (error) {
                return false;
            }
        },

        async submitAlbum() {
            if (this.isSubmitting) return;
            const remotePhotos = this.parseRemotePhotoUrls();
            const invalidRemotePhoto = remotePhotos.find(url => !this.isRemotePhotoUrlValid(url));
            if (this.uploadOnly && !this.albumId) {
                alert('无法识别当前相册，请刷新后重试');
                return;
            }
            if (!this.albumName.trim()) {
                alert('请输入相册名称');
                return;
            }
            if (invalidRemotePhoto) {
                alert(`图片链接格式不正确：${invalidRemotePhoto}`);
                return;
            }
            if (this.mediaFiles.length + remotePhotos.length > 30) {
                alert('本地图片和远程图片合计最多 30 张');
                return;
            }
            if (this.uploadOnly && this.mediaFiles.length === 0 && remotePhotos.length === 0) {
                alert('请选择照片或填写远程图片链接');
                return;
            }

            this.isSubmitting = true;
            this.submitStatus = this.uploadOnly ? '上传中...' : '保存中...';
            try {
                const formData = new FormData();
                if (this.albumId) formData.append('albumId', this.albumId);
                formData.append('name', this.albumName.trim());
                formData.append('cover', this.cover.trim());
                formData.append('tags', this.tags);
                formData.append('address', this.address);
                formData.append('visibility', this.visibility);
                if (!this.uploadOnly) formData.append('isPinned', this.isPinned ? '1' : '0');
                formData.append('remotePhotos', JSON.stringify(remotePhotos));
                this.mediaFiles.forEach((media, index) => formData.append(`media_${index}`, media.file));

                const response = await fetch(window.ICEFOX_PLUGIN.url(window.ICEFOX_PLUGIN.actions.saveAlbum), {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || (this.uploadOnly ? '照片上传失败' : '相册保存失败'));
                }

                this.submitStatus = this.uploadOnly ? '上传成功' : '保存成功';
                this.$dispatch('album-updated');
                window.setTimeout(() => this.closeModal(), 350);
            } catch (error) {
                this.submitStatus = '';
                alert(error.message || '网络错误，请稍后重试');
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>

<div class="album-editor-modal" x-cloak x-data="albumEditorManager()"
     x-show="albumEditorShow" @album-editor-open.window="openModal($event)"
     @keydown.escape.window="closeModal()" @click.self="closeModal()" x-transition.opacity>
    <div class="album-editor-container" role="dialog" aria-modal="true" aria-labelledby="album-editor-title" x-transition.scale.duration.200ms>
        <form class="album-editor-form" @submit.prevent="submitAlbum">
            <header class="album-editor-header">
                <button type="button" class="album-editor-close" aria-label="关闭相册编辑" @click="closeModal()">×</button>
                <h2 id="album-editor-title" x-text="uploadOnly ? '上传照片' : (albumId ? '编辑相册' : '新建相册')"></h2>
                <button type="submit" class="edit-publish-btn" :disabled="isSubmitting" x-text="isSubmitting ? (uploadOnly ? '上传中...' : '保存中...') : (uploadOnly ? '上传' : '保存')"></button>
            </header>
            <div class="album-editor-body">
                <div x-show="!uploadOnly">
                    <label class="album-editor-field">
                        <span>相册名称</span>
                        <input type="text" x-model="albumName" maxlength="80" required placeholder="例如：春日散步">
                    </label>
                    <label class="album-editor-field">
                        <span>封面图 URL</span>
                        <input type="url" x-model="cover" placeholder="留空则使用首图">
                    </label>
                    <label class="album-editor-field">
                        <span>标签</span>
                        <input type="text" x-model="tags" placeholder="用逗号分隔">
                    </label>
                    <label class="album-editor-field">
                        <span>地址</span>
                        <input type="text" x-model="address" placeholder="例如：成都·天府广场">
                    </label>
                    <div class="album-editor-field">
                        <span>谁可以看</span>
                        <div class="album-visibility-options">
                            <button type="button" :class="{active: visibility === 'public'}" @click="visibility = 'public'">公开</button>
                            <button type="button" :class="{active: visibility === 'private'}" @click="visibility = 'private'">私密</button>
                        </div>
                    </div>
                    <label class="album-editor-pin-option">
                        <span class="album-editor-pin-label">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M8.5 3h7l-.85 6.3 2.85 3.2V14h-11v-1.5l2.85-3.2L8.5 3Z" />
                                <path d="M11 14h2v7h-2z" />
                            </svg>
                            <span>置顶相册</span>
                        </span>
                        <span class="option-switch">
                            <span class="switch">
                                <input type="checkbox" name="isPinned" x-model="isPinned" aria-label="置顶相册">
                                <span class="slider"></span>
                            </span>
                        </span>
                    </label>
                </div>
                <div class="album-editor-field">
                    <span x-text="uploadOnly ? '选择照片' : '添加照片'"></span>
                    <input type="file" accept="image/*" multiple @change="handleMediaSelect($event)">
                    <small>本地图片与远程链接合计最多 30 张，未设置封面时自动使用第一张照片</small>
                </div>
                <label class="album-editor-field">
                    <span>远程图片链接</span>
                    <textarea x-model="remotePhotoUrls" rows="5" placeholder="https://example.com/photo-1.jpg&#10;https://example.com/photo-2.jpg"></textarea>
                    <small>支持图片直链或图床链接，一行一张，仅支持 HTTP/HTTPS</small>
                </label>
                <div class="album-editor-media" x-show="mediaFiles.length">
                    <template x-for="(media, index) in mediaFiles" :key="index">
                        <div class="album-editor-media-item">
                            <img :src="media.preview" alt="照片预览">
                            <button type="button" aria-label="移除照片" @click="removeMedia(index)">×</button>
                        </div>
                    </template>
                </div>
                <div class="album-editor-status" x-show="submitStatus" x-text="submitStatus"></div>
            </div>
        </form>
    </div>
</div>
