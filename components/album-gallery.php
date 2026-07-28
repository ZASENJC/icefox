<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$albumKey = trim((string) $this->request->get('album', ''));
$configuredAlbumUrl = trim((string) $this->options->albumPageUrl);
$albumPageUrl = $configuredAlbumUrl !== '' ? rtrim($configuredAlbumUrl, '/') : Typecho_Common::url('albums', $this->options->index);
$albumEditorUser = \Widget\User::alloc();
$canEditAlbums = $albumEditorUser->hasLogin();
$showMomentsAlbum = (string) $this->options->showMomentsAlbum !== '0';
?>

<script>
function albumGalleryManager(initialAlbumKey, showMomentsAlbum) {
    return {
        albumKey: initialAlbumKey || '',
        showMomentsAlbum: showMomentsAlbum !== false,
        albums: [],
        album: null,
        loading: true,
        error: '',

        get isDetail() {
            return this.albumKey !== '';
        },

        get albumTitle() {
            return this.album && this.album.name ? this.album.name : '相册';
        },

        async init() {
            await this.load();
        },

        normalizePhoto(photo) {
            if (typeof photo === 'string') return { src: photo, alt: '' };
            if (!photo || typeof photo !== 'object') return { src: '', alt: '' };
            return {
                src: photo.src || photo.url || photo.path || photo.image || '',
                alt: photo.alt || photo.title || ''
            };
        },

        isAlbumPinned(album) {
            const source = album || {};
            let value = false;
            for (const key of ['isPinned', 'pinned', 'is_pinned']) {
                if (Object.prototype.hasOwnProperty.call(source, key)) {
                    value = source[key];
                    break;
                }
            }
            return value === true || value === 1 || value === '1' || value === 'true';
        },

        normalizeAlbum(album) {
            const source = album || {};
            const rawPhotos = source.photos || source.images || source.media || [];
            const photos = Array.isArray(rawPhotos) ? rawPhotos.map(photo => this.normalizePhoto(photo)).filter(photo => photo.src) : [];
            const tags = Array.isArray(source.tags) ? source.tags : (source.tags ? String(source.tags).split(',').map(tag => tag.trim()).filter(Boolean) : []);
            const cover = source.cover || source.coverUrl || source.firstImage || source.first_image || (photos[0] ? photos[0].src : '');
            return {
                ...source,
                id: source.id || source.aid || source.albumId || '',
                slug: source.slug || source.id || source.aid || '',
                name: source.name || source.title || '未命名相册',
                cover,
                tags,
                photos,
                address: source.address || source.location || '',
                isPinned: this.isAlbumPinned(source)
            };
        },

        isMomentsAlbum(album) {
            const source = album || {};
            const slug = String(source.slug || source.id || source.aid || '').toLowerCase();
            const type = String(source.type || source.kind || '').toLowerCase();
            const name = String(source.name || source.title || '').trim();
            const markedAsMoments = source.isMoments === true || source.isMoments === 1 || source.isMoments === '1';
            return markedAsMoments || type === 'moments' || ['moments', 'pengyouquan'].includes(slug) || name === '朋友圈';
        },

        sortPinnedAlbums(albums) {
            return albums
                .map((album, index) => ({ album, index }))
                .sort((left, right) => Number(right.album.isPinned) - Number(left.album.isPinned) || left.index - right.index)
                .map(item => item.album);
        },

        normalizeAlbumList(albums) {
            const normalizedAlbums = (Array.isArray(albums) ? albums : []).map(album => this.normalizeAlbum(album));
            const momentsAlbum = normalizedAlbums.find(album => this.isMomentsAlbum(album));
            const regularAlbums = normalizedAlbums.filter(album => !this.isMomentsAlbum(album));

            if (!this.showMomentsAlbum) {
                return this.sortPinnedAlbums(regularAlbums);
            }

            if (!momentsAlbum) {
                regularAlbums.unshift(this.normalizeAlbum({
                    id: 'moments',
                    slug: 'moments',
                    name: '朋友圈',
                    type: 'moments',
                    isMoments: true,
                    photos: []
                }));
                return this.sortPinnedAlbums(regularAlbums);
            }

            regularAlbums.unshift(momentsAlbum);
            return this.sortPinnedAlbums(regularAlbums);
        },

        unwrap(payload) {
            const data = payload && payload.data !== undefined ? payload.data : payload;
            if (Array.isArray(data)) return { albums: data, album: null };
            if (!data || typeof data !== 'object') return { albums: [], album: null };
            return {
                albums: Array.isArray(data.albums) ? data.albums : [],
                album: data.album || data
            };
        },

        async load() {
            this.loading = true;
            this.error = '';
            try {
                const query = this.isDetail ? `&album=${encodeURIComponent(this.albumKey)}` : '';
                const action = this.isDetail ? 'getAlbum' : 'getAlbums';
                const response = await fetch(`${window.ICEFOX_CONFIG.actionUrl}?do=${action}${query}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.success === false) {
                    throw new Error(result.message || '相册加载失败');
                }

                const data = this.unwrap(result);
                if (this.isDetail) {
                    this.album = this.normalizeAlbum(data.album);
                    this.updateHeader(this.album.cover);
                } else {
                    this.albums = this.normalizeAlbumList(data.albums);
                }
            } catch (error) {
                this.error = error.message || '相册加载失败';
            } finally {
                this.loading = false;
            }
        },

        albumHref(album) {
            const url = new URL(window.ICEFOX_CONFIG.albumUrl, window.location.href);
            url.pathname = `${url.pathname.replace(/\/+$/, '')}/${encodeURIComponent(album.slug || album.pinyin || album.id)}`;
            url.search = '';
            return url.toString();
        },

        updateHeader(cover) {
            const header = document.querySelector('[data-album-header]');
            if (!header || !cover) return;
            header.style.backgroundImage = `url("${String(cover).replace(/"/g, '%22')}")`;
        },

        openPrimaryAction() {
            if (this.isDetail) {
                if (!this.album) return;
                this.openEditor(this.album, true);
                return;
            }

            this.openEditor();
        },

        openEditor(album, uploadOnly = false) {
            window.dispatchEvent(new CustomEvent('album-editor-open', {
                detail: {
                    album: album || null,
                    uploadOnly
                }
            }));
        }
    };
}
</script>

<div class="album-page-content" x-data="albumGalleryManager(<?php echo htmlspecialchars(json_encode($albumKey, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $showMomentsAlbum ? 'true' : 'false'; ?>)" x-init="init()" @album-primary-action.window="openPrimaryAction()" @album-updated.window="load()">
    <div class="album-page-heading">
        <div>
            <a class="album-back-link" href="<?php echo htmlspecialchars($albumPageUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>" x-show="isDetail">相册</a>
            <h1 class="album-page-title" x-text="albumTitle"></h1>
            <p class="album-page-subtitle" x-show="!isDetail">记录每一个值得留下的瞬间</p>
        </div>
        <button type="button" class="album-add-button" aria-label="新建相册" x-show="!isDetail" @click="openEditor()">+</button>
    </div>

    <div class="album-loading" x-show="loading">正在加载相册...</div>
    <div class="album-error" x-show="!loading && error" x-text="error"></div>

    <div class="album-list-grid" x-show="!loading && !error && !isDetail">
        <template x-for="album in albums" :key="album.id || album.slug">
            <div class="album-card">
                <a class="album-card-link" :href="albumHref(album)">
                    <div class="album-card-cover">
                        <span class="album-card-pinned" x-cloak x-show="album.isPinned" aria-label="置顶相册">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M8.5 3h7l-.85 6.3 2.85 3.2V14h-11v-1.5l2.85-3.2L8.5 3Z" />
                                <path d="M11 14h2v7h-2z" />
                            </svg>
                        </span>
                        <template x-if="album.cover"><img :src="album.cover" :alt="album.name" decoding="async"></template>
                        <template x-if="!album.cover"><div class="album-card-placeholder">相册</div></template>
                    </div>
                    <div class="album-card-name" x-text="album.name"></div>
                    <div class="album-card-meta" x-show="album.photos.length" x-text="album.photos.length + ' 张照片'"></div>
                </a>
                <?php if ($canEditAlbums): ?>
                    <button type="button" class="album-card-edit" aria-label="编辑相册" @click="openEditor(album)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.687a1.875 1.875 0 1 1 2.652 2.652L9.832 16.821a4.5 4.5 0 0 1-1.897 1.13l-3.2.96.96-3.2a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" />
                        </svg>
                    </button>
                <?php endif; ?>
            </div>
        </template>
        <div class="album-empty" x-show="albums.length === 0">还没有相册</div>
    </div>

    <div class="album-detail-view" x-show="!loading && !error && isDetail">
        <div class="album-detail-meta" x-show="album">
            <span class="album-detail-address" x-show="album && album.address" x-text="album && album.address"></span>
            <span class="album-detail-tags" x-show="album && album.tags.length" x-text="album && album.tags.join(' · ')"></span>
        </div>
        <div class="album-grid" x-show="album && album.photos.length">
            <template x-for="(photo, index) in (album ? album.photos : [])" :key="photo.src + '-' + index">
                <a class="album-grid-item" :href="photo.src" data-fancybox="album-photos" :data-caption="photo.alt || albumTitle">
                    <img :src="photo.src" :alt="photo.alt || albumTitle" decoding="async">
                </a>
            </template>
        </div>
        <div class="album-empty" x-show="album && album.photos.length === 0">这个相册还没有照片</div>
    </div>
</div>
