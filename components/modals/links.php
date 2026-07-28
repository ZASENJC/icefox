<?php
/** 初始化上下文 */
\Widget\Options::alloc()->to($options);
?>

<div class="links-modal" x-cloak
     x-data="{
         linksModalShow: false,
         links: [],
         loading: false,
         error: null,
         async loadLinks() {
             this.loading = true;
             this.error = null;
             try {
                 const response = await fetch(window.ICEFOX_PLUGIN.url(window.ICEFOX_PLUGIN.actions.getFriendLinks));
                 const result = await response.json();

                 if (result.success) {
                     this.links = result.data || [];
                 } else {
                     this.error = result.message || '加载失败';
                 }
             } catch (error) {
                 this.error = '网络错误，请稍后重试';
             } finally {
                 this.loading = false;
             }
         }
     }"
     x-show="linksModalShow"
     x-transition.opacity.duration.300ms
     @click.self="linksModalShow = false"
     @links-modal-open.window="linksModalShow = true; loadLinks();">

    <div class="links-container" x-transition.scale.duration.300ms>
        <div>
            <!-- 弹框标题 -->
            <div class="links-modal-header">
                <div class="links-modal-title">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="20" height="20" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    友情链接
                </div>
                <button type="button" class="links-modal-close" @click="linksModalShow = false">×</button>
            </div>

            <!-- 友情链接区域 -->
            <div class="links-section">
                <!-- 加载状态 -->
                <div x-show="loading" class="links-loading">
                    <svg class="links-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="40" height="40">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p>加载中...</p>
                </div>

                <!-- 错误提示 -->
                <div x-show="error && !loading" class="links-error">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="24" height="24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <p x-text="error"></p>
                </div>

                <!-- 友情链接列表 -->
                <div x-show="!loading && !error && links.length > 0" class="links-list">
                    <template x-for="link in links" :key="link.id">
                        <a :href="link.url" target="_blank" rel="noopener noreferrer" class="link-item">
                            <div class="link-avatar">
                                <img :src="link.avatar || '/usr/themes/icefox/assets/images/default-avatar.png'"
                                     :alt="link.name"
                                     loading="lazy"
                                     onerror="this.src='/usr/themes/icefox/assets/images/default-avatar.png'">
                            </div>
                            <div class="link-info">
                                <div class="link-name" x-text="link.name"></div>
                                <div class="link-description" x-text="link.description || '暂无描述'"></div>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor" class="link-arrow">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </template>
                </div>

                <!-- 空状态 -->
                <div x-show="!loading && !error && links.length === 0" class="links-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="48" height="48" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                    <p>暂无友情链接</p>
                </div>
            </div>
        </div>
    </div>
</div>
