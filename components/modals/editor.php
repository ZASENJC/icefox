<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$editorUser = \Widget\User::alloc();
if (!$editorUser->hasLogin()) return;
?>

<script>
function editorModalManager() {
    return {
        editorModalShow: false,
        postContent: '',
        mediaFiles: [],
        position: '',
        positionUrl: '',
        visibility: 'public',
        syncToAlbum: false,
        showLocationPicker: false,
        showVisibilityPicker: false,
        submitStatus: '',
        isSubmitting: false,

        get visibilityText() {
            return this.visibility === 'private' ? '私密' : '公开';
        },

        openModal() {
            this.editorModalShow = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.contentInput.focus());
        },

        closeModal() {
            if (this.isSubmitting) return;
            this.editorModalShow = false;
            this.showLocationPicker = false;
            this.showVisibilityPicker = false;
            document.body.style.overflow = '';
        },

        autoResize(event) {
            const textarea = event.target;
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        },

        addMedia(file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                this.mediaFiles.push({ file, type: file.type, preview: event.target.result });
            };
            reader.readAsDataURL(file);
        },

        handleMediaSelect(event) {
            const input = event.target;
            const files = Array.from(input.files);
            const hasVideo = files.some(file => file.type.startsWith('video/'));
            const hasImage = files.some(file => file.type.startsWith('image/'));
            const currentHasVideo = this.mediaFiles.some(file => file.type.startsWith('video/'));
            const currentHasImage = this.mediaFiles.some(file => file.type.startsWith('image/'));

            if (currentHasVideo) {
                alert('已上传视频,不能再添加其他文件');
                input.value = '';
                return;
            }

            if (currentHasImage && hasVideo) {
                alert('已上传图片,不能再上传视频');
                input.value = '';
                return;
            }

            if (hasVideo) {
                const videos = files.filter(file => file.type.startsWith('video/'));
                if (videos.length > 1) {
                    alert('只能上传1个视频');
                } else if (hasImage) {
                    alert('上传视频时不能同时上传图片');
                } else {
                    this.addMedia(videos[0]);
                }
                input.value = '';
                return;
            }

            const remainingSlots = 9 - this.mediaFiles.length;
            if (remainingSlots <= 0) {
                alert('最多只能上传9张图片');
                input.value = '';
                return;
            }

            if (files.length > remainingSlots) {
                alert(`最多只能上传9张图片，已自动选择前${remainingSlots}张`);
            }
            files.slice(0, remainingSlots).forEach(file => this.addMedia(file));
            input.value = '';
        },

        removeMedia(index) {
            this.mediaFiles.splice(index, 1);
        },

        async submitPost() {
            if (this.isSubmitting) return;
            if (!this.postContent.trim() && this.mediaFiles.length === 0) {
                alert('请输入内容或选择图片/视频');
                return;
            }

            this.isSubmitting = true;
            this.submitStatus = '发布中...';

            try {
                const formData = new FormData();
                formData.append('content', this.postContent);
                formData.append('position', this.position);
                formData.append('positionUrl', this.positionUrl);
                formData.append('visibility', this.visibility);
                formData.append('syncToAlbum', this.syncToAlbum ? '1' : '0');
                this.mediaFiles.forEach((media, index) => {
                    formData.append(`media_${index}`, media.file);
                });

                const response = await fetch(`${window.ICEFOX_CONFIG.actionUrl}?do=createPost`, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || '发布失败，请稍后重试');
                }

                this.submitStatus = '发布成功！';
                const configuredHomeUrl = new URL(result.redirect || window.ICEFOX_CONFIG.homeUrl, window.location.href);
                const homeUrl = new URL(configuredHomeUrl.pathname + configuredHomeUrl.search, window.location.origin);
                homeUrl.searchParams.set('icefox_published', Date.now().toString());
                window.setTimeout(() => {
                    window.location.replace(homeUrl.toString());
                }, 500);
            } catch (error) {
                this.isSubmitting = false;
                this.submitStatus = '';
                alert(error.message || '网络错误，请稍后重试');
            }
        }
    };
}
</script>

<div class="editor-modal" x-cloak x-data="editorModalManager()" x-show="editorModalShow"
     @editor-modal-open.window="openModal()" @keydown.escape.window="closeModal()"
     @click.self="closeModal()" x-transition.opacity>
    <div class="editor-modal-container" role="dialog" aria-modal="true" aria-labelledby="editor-modal-title"
         x-transition.scale.duration.200ms>
        <form class="editor-modal-form" @submit.prevent="submitPost">
            <header class="editor-modal-header">
                <button type="button" class="editor-modal-close" aria-label="关闭发布窗口" @click="closeModal()">×</button>
                <h2 id="editor-modal-title">发布动态</h2>
                <button type="submit" class="edit-publish-btn" :disabled="isSubmitting"
                        x-text="isSubmitting ? '发布中...' : '发表'"></button>
            </header>

            <div class="editor-modal-body">
                <div class="edit-content-area">
                    <textarea name="content" x-ref="contentInput" placeholder="这一刻的想法..."
                              x-model="postContent" @input="autoResize($event)" rows="4"></textarea>
                </div>

                <div class="edit-media-section">
                    <div class="edit-media-preview" :class="'media-count-' + mediaFiles.length" x-show="mediaFiles.length > 0">
                        <template x-for="(file, index) in mediaFiles" :key="index">
                            <div class="media-preview-item" :class="{'is-video': file.type.startsWith('video/')}">
                                <template x-if="file.type.startsWith('image/')"><img :src="file.preview" alt="预览图片"></template>
                                <template x-if="file.type.startsWith('video/')"><video :src="file.preview" muted></video></template>
                                <button type="button" class="media-remove-btn" aria-label="移除媒体" @click="removeMedia(index)">×</button>
                                <div class="video-indicator" x-show="file.type.startsWith('video/')">▶</div>
                            </div>
                        </template>
                        <button type="button" class="media-add-btn" aria-label="添加媒体"
                                @click="$refs.mediaInput.click()" x-show="mediaFiles.length > 0 && mediaFiles.length < 9">+</button>
                    </div>
                    <button type="button" class="media-empty-add" @click="$refs.mediaInput.click()" x-show="mediaFiles.length === 0">
                        <span class="media-empty-icon">+</span>
                        <span class="media-empty-text">图片/视频</span>
                    </button>
                </div>
                <input type="file" x-ref="mediaInput" accept="image/*,video/*" multiple
                       @change="handleMediaSelect($event)" hidden>

                <div class="edit-options">
                    <button type="button" class="edit-option-item" @click="showLocationPicker = !showLocationPicker">
                        <span class="option-icon" aria-hidden="true">⌖</span>
                        <span class="option-content"><span class="option-label">所在位置</span></span>
                        <span class="option-value" x-text="position || '未设置'"></span>
                        <span class="option-arrow">›</span>
                    </button>
                    <div class="edit-location-picker" x-show="showLocationPicker" x-transition>
                        <div class="location-picker-input"><input type="text" placeholder="输入位置名称" x-model="position"></div>
                        <div class="location-picker-input"><input type="url" placeholder="输入跳转地址(选填)" x-model="positionUrl"></div>
                        <div class="location-picker-actions"><button type="button" class="location-done-btn" @click="showLocationPicker = false">完成</button></div>
                    </div>

                    <button type="button" class="edit-option-item" @click="showVisibilityPicker = !showVisibilityPicker">
                        <span class="option-icon" aria-hidden="true">◉</span>
                        <span class="option-content"><span class="option-label">谁可以看</span></span>
                        <span class="option-value" x-text="visibilityText"></span>
                        <span class="option-arrow">›</span>
                    </button>
                    <div class="edit-visibility-picker" x-show="showVisibilityPicker" x-transition>
                        <button type="button" class="visibility-option" :class="{'active': visibility === 'public'}"
                                @click="visibility = 'public'; showVisibilityPicker = false">
                            <span class="visibility-text"><span class="visibility-label">公开</span><span class="visibility-desc">所有人可见</span></span>
                            <span class="visibility-check" x-show="visibility === 'public'">✓</span>
                        </button>
                        <button type="button" class="visibility-option" :class="{'active': visibility === 'private'}"
                                @click="visibility = 'private'; showVisibilityPicker = false">
                            <span class="visibility-text"><span class="visibility-label">私密</span><span class="visibility-desc">仅自己可见</span></span>
                            <span class="visibility-check" x-show="visibility === 'private'">✓</span>
                        </button>
                    </div>

                    <label class="edit-option-item">
                        <span class="option-icon" aria-hidden="true">▧</span>
                        <span class="option-content">
                            <span class="option-label">同步到「朋友圈」相册</span>
                        </span>
                        <span class="option-switch">
                            <span class="switch">
                                <input type="checkbox" name="syncToAlbum" x-model="syncToAlbum">
                                <span class="slider"></span>
                            </span>
                        </span>
                    </label>
                </div>

                <div class="edit-status" x-show="submitStatus" x-text="submitStatus"></div>
            </div>
        </form>
    </div>
</div>
