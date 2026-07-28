<?php
/**
 * 发布文章页面模板
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 初始化上下文
\Widget\Options::alloc()->to($options);
\Widget\User::alloc()->to($user);

// 检查用户是否登录
$isLoggedIn = $user->hasLogin();

// 包含头部文件
$this->need('header.php');
?>

<?php if ($isLoggedIn): ?>
<script>
function editPageManager() {
    return {
        postContent: '',
        mediaFiles: [],
        position: '',
        positionUrl: '',
        visibility: 'public',
        syncToAlbum: false,
        showLocationPicker: false,
        showVisibilityPicker: false,
        submitStatus: '',

        get visibilityText() {
            const texts = {
                'public': '公开',
                'private': '私密'
            };
            return texts[this.visibility] || '公开';
        },

        autoResize(event) {
            const textarea = event.target;
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        },

        handleMediaSelect(event) {
            const files = Array.from(event.target.files);

            // 检查是否有视频文件
            const hasVideo = files.some(f => f.type.startsWith('video/'));
            const hasImage = files.some(f => f.type.startsWith('image/'));
            const currentHasVideo = this.mediaFiles.some(f => f.type.startsWith('video/'));
            const currentHasImage = this.mediaFiles.some(f => f.type.startsWith('image/'));

            // 规则1: 如果已有视频,不能再添加任何文件
            if (currentHasVideo) {
                alert('已上传视频,不能再添加其他文件');
                event.target.value = '';
                return;
            }

            // 规则2: 如果已有图片,不能上传视频
            if (currentHasImage && hasVideo) {
                alert('已上传图片,不能再上传视频');
                event.target.value = '';
                return;
            }

            // 规则3: 如果选择了视频,只能上传一个视频,不能有图片
            if (hasVideo) {
                const videoFiles = files.filter(f => f.type.startsWith('video/'));
                if (videoFiles.length > 1) {
                    alert('只能上传1个视频');
                    event.target.value = '';
                    return;
                }
                if (hasImage) {
                    alert('上传视频时不能同时上传图片');
                    event.target.value = '';
                    return;
                }
                // 只添加这一个视频
                const videoFile = videoFiles[0];
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.mediaFiles.push({
                        file: videoFile,
                        type: videoFile.type,
                        preview: e.target.result
                    });
                };
                reader.readAsDataURL(videoFile);
                event.target.value = '';
                return;
            }

            // 规则4: 上传图片,最多9张
            const remainingSlots = 9 - this.mediaFiles.length;

            if (remainingSlots <= 0) {
                alert('最多只能上传9张图片');
                event.target.value = '';
                return;
            }

            const filesToAdd = files.slice(0, remainingSlots);

            if (files.length > remainingSlots) {
                alert(`最多只能上传9张图片，已自动选择前${remainingSlots}张`);
            }

            filesToAdd.forEach(file => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.mediaFiles.push({
                        file: file,
                        type: file.type,
                        preview: e.target.result
                    });
                };
                reader.readAsDataURL(file);
            });

            // 清空input，允许重复选择同一文件
            event.target.value = '';
        },

        removeMedia(index) {
            this.mediaFiles.splice(index, 1);
        },

        async submitPost() {
            if (!this.postContent.trim() && this.mediaFiles.length === 0) {
                alert('请输入内容或选择图片/视频');
                return;
            }

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
                if (typeof window.ICEFOX_PLUGIN.appendStorageTarget === 'function') {
                    window.ICEFOX_PLUGIN.appendStorageTarget(formData);
                }

                const response = await fetch(window.ICEFOX_PLUGIN.url(window.ICEFOX_PLUGIN.actions.createPost), {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    this.submitStatus = result.message || '发布成功！';
                    setTimeout(() => {
                        window.location.href = result.redirect || '/';
                    }, 1000);
                } else {
                    this.submitStatus = '';
                    alert(result.message || '发布失败，请稍后重试');
                }
            } catch (error) {
                this.submitStatus = '';
                alert(error.message || '网络错误，请稍后重试');
            }
        }
    }
}
</script>
<?php endif; ?>

<main>
    <!-- 发布页面顶部栏 -->
    <section class="edit-top-bar">
        <div class="edit-top-left">
            <a href="javascript:history.back()" class="edit-back-btn">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </a>
        </div>
        <div class="edit-top-right">
            <button type="button" class="edit-publish-btn" id="publishBtn" <?php echo !$isLoggedIn ? 'disabled' : ''; ?>>
                发表
            </button>
        </div>
    </section>

    <section class="edit-container">
        <?php if (!$isLoggedIn): ?>
            <!-- 未登录提示 -->
            <div class="edit-login-required" x-data>
                <div class="login-required-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <h3>请先登录</h3>
                <p>登录后即可发布内容</p>
                <button type="button" class="login-required-btn"
                        @click="$nextTick(() => { document.querySelector('.setting-modal')._x_dataStack[0].settingModalShow = true })">
                    立即登录
                </button>
            </div>
        <?php else: ?>
            <div x-data="editPageManager()">
            <!-- 发布表单 -->
            <form id="editForm" @submit.prevent="submitPost">
                <!-- 文章内容输入区 -->
                <div class="edit-content-area">
                    <textarea
                        name="content"
                        id="postContent"
                        placeholder="这一刻的想法..."
                        x-model="postContent"
                        @input="autoResize($event)"
                        rows="4"></textarea>
                </div>

                <!-- 媒体预览区 - 微信朋友圈九宫格样式 -->
                <div class="edit-media-section" x-show="mediaFiles.length > 0 || true">
                    <div class="edit-media-preview" :class="'media-count-' + mediaFiles.length" x-show="mediaFiles.length > 0">
                        <template x-for="(file, index) in mediaFiles" :key="index">
                            <div class="media-preview-item" :class="{'is-video': file.type.startsWith('video/')}">
                                <template x-if="file.type.startsWith('image/')">
                                    <img :src="file.preview" alt="预览图片">
                                </template>
                                <template x-if="file.type.startsWith('video/')">
                                    <video :src="file.preview" muted></video>
                                </template>
                                <button type="button" class="media-remove-btn" @click="removeMedia(index)">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="14" height="14">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <div class="video-indicator" x-show="file.type.startsWith('video/')">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                            </div>
                        </template>
                        <!-- 添加更多按钮 -->
                        <div class="media-add-btn" @click="$refs.mediaInput.click()" x-show="mediaFiles.length > 0 && mediaFiles.length < 9">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                    </div>

                    <!-- 空状态添加按钮 -->
                    <div class="media-empty-add" @click="$refs.mediaInput.click()" x-show="mediaFiles.length === 0">
                        <div class="media-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" width="32" height="32">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <span class="media-empty-text">图片/视频</span>
                    </div>
                </div>
                <input type="file"
                       x-ref="mediaInput"
                       accept="image/*,video/*"
                       multiple
                       @change="handleMediaSelect($event)"
                       style="display: none;">

                <!-- 功能选项区 -->
                <div class="edit-options">
                    <!-- 所在位置 -->
                    <div class="edit-option-item" @click="showLocationPicker = !showLocationPicker">
                        <div class="option-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                        </div>
                        <div class="option-content">
                            <span class="option-label">所在位置</span>
                        </div>
                        <div class="option-value" x-show="position">
                            <span x-text="position"></span>
                        </div>
                        <div class="option-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </div>

                    <!-- 位置编辑弹窗 -->
                    <div class="edit-location-picker" x-show="showLocationPicker" x-transition>
                        <div class="location-picker-input">
                            <input type="text"
                                   placeholder="输入位置名称"
                                   x-model="position">
                            <button type="button" class="location-clear-btn" @click="position = ''" x-show="position">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="location-picker-input">
                            <input type="text"
                                   placeholder="输入跳转地址(选填)"
                                   x-model="positionUrl">
                            <button type="button" class="location-clear-btn" @click="positionUrl = ''" x-show="positionUrl">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="location-picker-actions">
                            <button type="button" class="location-done-btn" @click="showLocationPicker = false">完成</button>
                        </div>
                    </div>

                    <!-- 谁可以看 -->
                    <div class="edit-option-item" @click="showVisibilityPicker = !showVisibilityPicker">
                        <div class="option-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                        </div>
                        <div class="option-content">
                            <span class="option-label">谁可以看</span>
                        </div>
                        <div class="option-value">
                            <span x-text="visibilityText"></span>
                        </div>
                        <div class="option-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </div>

                    <!-- 可见性选择弹窗 -->
                    <div class="edit-visibility-picker" x-show="showVisibilityPicker" x-transition>
                        <div class="visibility-option"
                             :class="{'active': visibility === 'public'}"
                             @click="visibility = 'public'; showVisibilityPicker = false">
                            <div class="visibility-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12.75 3.03v.568c0 .334.148.65.405.864l1.068.89c.442.369.535 1.01.216 1.49l-.51.766a2.25 2.25 0 0 1-1.161.886l-.143.048a1.107 1.107 0 0 0-.57 1.664c.369.555.169 1.307-.427 1.605L9 13.125l.423 1.059a.956.956 0 0 1-1.652.928l-.679-.906a1.125 1.125 0 0 0-1.906.172L4.5 15.75l-.612.153M12.75 3.031a9 9 0 1 0 6.712 14.374M12.75 3.031a9 9 0 0 1 6.712 14.374m0 0-.177-.529A2.25 2.25 0 0 0 17.128 15H16.5l-.324-.324a1.453 1.453 0 0 0-2.328.377l-.036.073a1.586 1.586 0 0 1-.982.816l-.99.282c-.55.157-.894.702-.8 1.267l.073.438c.08.474.49.821.97.821.846 0 1.598.542 1.865 1.345l.215.643m5.276-3.67a9.012 9.012 0 0 1-5.276 3.67m0 0a9 9 0 0 1-10.275-4.835M15.75 9c0 .896-.393 1.7-1.016 2.25" />
                                </svg>
                            </div>
                            <div class="visibility-text">
                                <span class="visibility-label">公开</span>
                                <span class="visibility-desc">所有人可见</span>
                            </div>
                            <div class="visibility-check" x-show="visibility === 'public'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </div>
                        <div class="visibility-option"
                             :class="{'active': visibility === 'private'}"
                             @click="visibility = 'private'; showVisibilityPicker = false">
                            <div class="visibility-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <div class="visibility-text">
                                <span class="visibility-label">私密</span>
                                <span class="visibility-desc">仅自己可见</span>
                            </div>
                            <div class="visibility-check" x-show="visibility === 'private'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- 同步到朋友圈相册 -->
                    <label class="edit-option-item">
                        <span class="option-icon album-sync-image-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z" />
                            </svg>
                        </span>
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

                <!-- 提交状态提示 -->
                <div class="edit-status" x-show="submitStatus" x-transition>
                    <span x-text="submitStatus"></span>
                </div>
            </form>
            </div>
        <?php endif; ?>
    </section>

    <?php $this->need('components/modals/setting.php'); ?>
</main>

<?php if ($isLoggedIn): ?>
<script>
// 绑定发表按钮
document.addEventListener('DOMContentLoaded', function() {
    const publishBtn = document.getElementById('publishBtn');
    const editForm = document.getElementById('editForm');

    if (publishBtn && editForm) {
        publishBtn.addEventListener('click', function() {
            const submitEvent = new Event('submit', { cancelable: true });
            editForm.dispatchEvent(submitEvent);
        });
    }
});
</script>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
