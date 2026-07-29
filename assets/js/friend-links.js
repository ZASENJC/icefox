(function (global) {
    'use strict';

    const emptyForm = () => ({
        id: '',
        name: '',
        url: '',
        avatar: '',
        description: '',
        sort: 0
    });

    function endpoint(token) {
        const configuredUrl = global.ICEFOX_CONFIG && global.ICEFOX_CONFIG.friendLinksUrl;
        const url = new URL(configuredUrl || '/links', global.location.href);
        url.searchParams.set('icefox_links_api', '1');
        if (token) {
            url.searchParams.set('_', token);
        }
        return url.toString();
    }

    function isHttpUrl(value) {
        try {
            const url = new URL(String(value || '').trim());
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (error) {
            return false;
        }
    }

    function manager(config) {
        const settings = config || {};
        return {
            mode: settings.mode || 'modal',
            linksModalShow: settings.mode === 'page',
            links: Array.isArray(settings.initialLinks) ? settings.initialLinks : [],
            canEdit: Boolean(settings.canEdit),
            token: '',
            loading: false,
            error: '',
            notice: '',
            editorOpen: false,
            isSubmitting: false,
            formError: '',
            form: emptyForm(),

            get isPage() {
                return this.mode === 'page';
            },

            async initializePage() {
                if (this.isPage && this.canEdit) {
                    await this.loadLinks();
                }
            },

            async openModal() {
                this.linksModalShow = true;
                global.document.body.style.overflow = 'hidden';
                await this.loadLinks();
            },

            closeModal() {
                if (this.isSubmitting || this.isPage) return;
                this.linksModalShow = false;
                this.editorOpen = false;
                this.formError = '';
                global.document.body.style.overflow = '';
            },

            async loadLinks() {
                this.loading = true;
                this.error = '';
                this.notice = '';
                try {
                    const response = await global.fetch(endpoint(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || '友情链接加载失败');
                    }

                    this.applyResult(result);
                } catch (error) {
                    this.error = error.message || '网络错误，请稍后重试';
                } finally {
                    this.loading = false;
                }
            },

            applyResult(result) {
                this.links = Array.isArray(result.data) ? result.data : [];
                this.canEdit = Boolean(result.canEdit);
                this.token = result.token || '';
                this.notice = result.message || '';
            },

            startCreate() {
                if (!this.canEdit) return;
                this.form = emptyForm();
                this.formError = '';
                this.notice = '';
                this.editorOpen = true;
                if (typeof this.$nextTick === 'function') {
                    this.$nextTick(() => {
                        if (this.$refs && this.$refs.nameInput) this.$refs.nameInput.focus();
                    });
                }
            },

            startEdit(link) {
                if (!this.canEdit || !link) return;
                this.form = {
                    id: link.id || '',
                    name: link.name || '',
                    url: link.url || '',
                    avatar: link.avatar || '',
                    description: link.description || '',
                    sort: Number(link.sort) || 0
                };
                this.formError = '';
                this.notice = '';
                this.editorOpen = true;
            },

            closeEditor() {
                if (this.isSubmitting) return;
                this.editorOpen = false;
                this.form = emptyForm();
                this.formError = '';
            },

            validateForm() {
                if (!String(this.form.name || '').trim()) {
                    return '请填写友情链接名称';
                }
                if (!isHttpUrl(this.form.url)) {
                    return '链接地址必须是有效的 HTTP 或 HTTPS URL';
                }
                if (String(this.form.avatar || '').trim() && !isHttpUrl(this.form.avatar)) {
                    return '头像地址必须是有效的 HTTP 或 HTTPS URL';
                }
                return '';
            },

            async saveLink() {
                if (this.isSubmitting || !this.canEdit) return;
                this.formError = this.validateForm();
                if (this.formError) return;

                await this.mutate({
                    action: 'save',
                    link: {
                        id: this.form.id || '',
                        name: String(this.form.name || '').trim(),
                        url: String(this.form.url || '').trim(),
                        avatar: String(this.form.avatar || '').trim(),
                        description: String(this.form.description || '').trim(),
                        sort: Math.max(0, Number.parseInt(this.form.sort, 10) || 0)
                    }
                });
            },

            async deleteLink() {
                if (this.isSubmitting || !this.canEdit || !this.form.id) return;
                if (typeof global.confirm === 'function' && !global.confirm(`确定删除友情链接「${this.form.name}」吗？`)) {
                    return;
                }
                await this.mutate({ action: 'delete', id: this.form.id });
            },

            async mutate(payload) {
                if (!this.token) {
                    this.formError = '登录状态已变化，请重新打开友情链接窗口';
                    return;
                }

                this.isSubmitting = true;
                this.formError = '';
                try {
                    const response = await global.fetch(endpoint(this.token), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || '友情链接保存失败');
                    }

                    this.applyResult(result);
                    this.editorOpen = false;
                    this.form = emptyForm();
                } catch (error) {
                    this.formError = error.message || '网络错误，请稍后重试';
                } finally {
                    this.isSubmitting = false;
                }
            },

            avatarInitial(link) {
                const name = String(link && link.name || '').trim();
                return name ? Array.from(name)[0] : '友';
            },

            clearBrokenAvatar(link) {
                if (link) link.avatar = '';
            }
        };
    }

    global.icefoxFriendLinksManager = manager;
})(window);
