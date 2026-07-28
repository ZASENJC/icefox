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
        uploadOnly: false,
        mediaFiles: [],
        submitStatus: '',
        isSubmitting: false,

        get visibilityText() {
            return this.visibility === 'private' ? '私密' : '公开';
        },

        openModal(event) {
            const detail = event.detail || {};
            const album = Object.prototype.hasOwnProperty.call(detail, 'album') ? (detail.album || {}) : detail;
            this.uploadOnly = detail.uploadOnly === true;
            this.albumId = album.id || album.aid || album.albumId || album.slug || '';
            this.albumName = album.name || album.title || '';
            this.cover = album.cover || '';
            this.tags = Array.isArray(album.tags) ? album.tags.join(', ') : (album.tags || '');
            this.address = album.address || album.location || '';
            this.visibility = album.visibility === 'private' ? 'private' : 'public';
            this.mediaFiles = [];
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
            const remaining = Math.max(0, 30 - this.mediaFiles.length);
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

        async submitAlbum() {
            if (this.isSubmitting) return;
            if (this.uploadOnly && !this.albumId) {
                alert('无法识别当前相册，请刷新后重试');
                return;
            }
            if (!this.albumName.trim()) {
                alert('请输入相册名称');
                return;
            }
            if (this.uploadOnly && this.mediaFiles.length === 0) {
                alert('请选择要上传的照片');
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
                this.mediaFiles.forEach((media, index) => formData.append(`media_${index}`, media.file));

                const response = await fetch(`${window.ICEFOX_CONFIG.actionUrl}?do=saveAlbum`, {
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
                </div>
                <div class="album-editor-field">
                    <span x-text="uploadOnly ? '选择照片' : '添加照片'"></span>
                    <input type="file" accept="image/*" multiple @change="handleMediaSelect($event)">
                    <small>最多 30 张，未设置封面时自动使用第一张照片</small>
                </div>
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
