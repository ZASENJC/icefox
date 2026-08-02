# Icefox Repository Guide

## Project Overview

Icefox is a mobile-first, WeChat Moments-style theme for Typecho. It is not a
standalone application and cannot run without a Typecho installation and the
companion plugin installed as `IcefoxPlugin`.

- Theme version in code: `3.1.4`
- Expected Typecho version: `>= 1.2.0`
- Expected PHP version: `>= 7.0`
- Default branch: `main`
- No Composer, npm, or frontend build step is used.
- Third-party browser libraries are committed under `assets/`.

Install the theme files as `usr/themes/icefox/` inside a Typecho installation.
Install `plugins/IcefoxPlugin/` as `usr/plugins/IcefoxPlugin/`; it registers the
`/action/icefox` action route. Install `plugins/IcefoxStorage/` as
`usr/plugins/IcefoxStorage/` when R2/S3 image storage is enabled.

## Upgrade Compatibility

All future theme and companion-plugin releases must support in-place upgrades
by directly replacing the existing files. Any required database, schema, or
configuration migration must run automatically and idempotently after the files
are replaced while preserving existing user data and settings. Do not require
users to uninstall and reinstall components, clear data, or run a manual
migration as part of the normal upgrade path.

Release 3.1.3 has one directory transition: the companion plugin moves from
`usr/plugins/Icefox/` to `usr/plugins/IcefoxPlugin/`. Existing sites install the
new directory beside the old one and enable `IcefoxPlugin` once. Its activation
removes the legacy `Icefox` activation key and rewrites the routes without
changing plugin tables or theme data. After verification, the inactive old
directory can be deleted. Later releases return to direct replacement under
`usr/plugins/IcefoxPlugin/`.

## Runtime Architecture

Typecho loads `functions.php`, then selects one of the PHP templates. The normal
homepage rendering path is:

1. `index.php`
2. `header.php`
3. `components/head.php`
4. `components/post-list.php`
5. modal components under `components/modals/`
6. `footer.php`

Important templates:

- `index.php`: homepage entry point.
- `archive.php`: category, tag, search, and author result pages.
- `post.php`: post detail page.
- `page.php`: regular standalone page.
- `links-page.php`: standalone friend-links page and theme-owned read/write endpoint.
- `archive-page.php`: custom timeline archive page.
- `edit-page.php`: authenticated frontend post creation page.
- `album-page.php`: standalone album index and detail page.
- `sidebar.php`: legacy/general Typecho sidebar widgets; it is not part of the
  primary homepage composition.

Core responsibilities:

- `functions.php`: theme configuration, post fields, SEO, archive queries,
  attachment lookup, pinned-post state, and music shortcode rendering.
- `core/core.php`: image/video extraction and HTML-aware summary helpers.
- `comment_function.php`: direct Typecho comment queries and reply-tree
  construction.
- `header.php`: document head, global browser configuration, comment/reply
  controller, and script loading.
- `assets/js/icefox-plugin.js`: shared companion-plugin action URLs, fresh POST
  security tokens, upload target selection, and album upload fallback decisions.
- `assets/js/friend-links.js`: shared friend-link page/modal loading and logged-in CRUD state.
- `assets/js/icefox.js`: infinite scrolling, likes, content expansion, icon
  state, and back-to-top behavior.
- `assets/js/music-player.js`: per-card audio players and global playback
  coordination.
- `plugins/IcefoxPlugin/`: companion persistence, publishing, and album API.
- `plugins/IcefoxStorage/`: standalone R2/S3 signing, validation, upload, and
  object lifecycle integration.

## Data And Plugin Contract

The theme reads standard Typecho tables directly, especially `contents`,
`comments`, `users`, `metas`, `relationships`, and `fields`. The companion
plugin owns Icefox-specific persistence for likes and albums. Friend links are
stored in the Typecho `friendLinks` field of the independent links page.
`icefox_archive` is only a legacy pinning source for
`scripts/migrate-legacy-pins.php`; runtime pinning uses the Typecho `isTop`
post field.

`header.php` publishes the plugin route as `window.ICEFOX_CONFIG.actionUrl`, and
`assets/js/icefox-plugin.js` exposes it through `window.ICEFOX_PLUGIN`. Keep
pseudo-static and non-pseudo-static Typecho installations compatible by using
that client instead of hardcoding `/action/icefox`. Mutating requests must use
its `postUrl()` helper so they receive a fresh Typecho security token rather
than reusing the token embedded in an older page.

The frontend currently calls these plugin actions:

| Action | Method | Purpose |
| --- | --- | --- |
| `getLikes` | GET | Load like count, users, and current-user state |
| `like` | POST | Toggle a post like |
| `addComment` | POST JSON | Add a top-level comment or reply |
| `createPost` | POST multipart | Create a post and upload media |
| `getAlbums` | GET | Load the visible album list |
| `getAlbum` | GET | Load one album and its photos |
| `getSecurityToken` | GET | Refresh the current user's Typecho security token |
| `stageAlbumUpload` | POST binary | Stage one object-backed album image when PHP multipart limits require fallback |
| `saveAlbum` | POST multipart | Create or update an album, including description, ordering, and photos |

Do not implement these endpoints inside the theme unless the architecture is
explicitly being changed. They belong to the companion plugin.

Album responses expose a plain-text `description`; regular albums also expose
`sortOrder`. Descriptions are limited to 1000 characters and must render as
escaped text. A regular album holds at most 100 photos. The Moments album accepts
photos only through post synchronization, does not participate in manual
ordering, and always renders first; remaining albums preserve pinning priority
and then sort by ascending `sortOrder`.

Object-backed album uploads should use normal multipart upload when the selected
files fit the published PHP upload limits. Use `stageAlbumUpload` only as the
fallback for requests that would exceed those limits, then pass the returned
receipts to `saveAlbum`.

## Theme Configuration And Fields

`themeConfig()` defines these options:

- `topVideo`, `topImage`, `logoUrl`, `avatarLink`
- `beianInfo`, `beianUrl`, `gravatarUrl`
- `customCss`, `customJs`, `analytics`
- `editPageUrl`, `friendLinksPageUrl`, `albumTopImage`
- `showMomentsAlbum`, `autoCollapse`
- `uploadStorage`

The theme also observes Typecho's `pageSize` option.

`themeFields()` defines post fields `position`, `positionUrl`, `isTop`,
`albumOnly`, and `albumId`. Other code also reads `thumbnail`, `customFields`,
and attachment records. Preserve string and integer representations when
interpreting legacy boolean-like field values such as `isTop` and `albumOnly`.

## Content Rendering

The information feed intentionally resembles a social timeline rather than a
traditional blog index:

- Text is summarized to 100 visible characters when `autoCollapse` is enabled.
- Image and video URLs are extracted from rendered post content.
- Video takes precedence over the image gallery in list views.
- Music cards use this shortcode:

  `[music title="Song" artist="Artist" cover="URL" src="URL"]`

- List pages show recent top-level comments and all replies associated with
  those comments.
- Dark mode and anonymous visitor/comment identity are stored in
  `localStorage`.

When outputting user-controlled values, preserve the existing use of
`htmlspecialchars`, Alpine `x-text`, or equivalent escaping. Treat custom CSS,
custom JavaScript, analytics snippets, post HTML, URLs, and plugin JSON as
system boundaries requiring deliberate validation.

## Frontend Dependencies

The repository vendors these main libraries:

- Bulma `1.0.4`
- jQuery `2.2.4`
- Alpine.js `3.15.0`
- Fancybox `6.0.29`
- ScrollLoad

There is no asset compilation step. Edit source files in `assets/css/` and
`assets/js/` directly. Avoid editing minified vendor files for application
behavior.

## Development And Verification

This repository alone cannot provide a meaningful local preview. Use a Typecho
test installation with the Icefox plugin enabled and a configured database.

Scale verification effort to the risk and complexity of the change. For simple,
localized changes, make the requested edit, inspect the diff, and leave runtime
verification to the user; do not run broad or unrelated tests unless explicitly
requested. Use targeted automated checks when the affected behavior or blast
radius warrants them.

For changes that warrant automated verification, run the relevant checks
available in the environment:

```sh
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
node --check assets/js/icefox.js
node --check assets/js/icefox-plugin.js
node --check assets/js/music-player.js
git diff --check
git diff
```

Exercise these workflows after behavior changes:

- Homepage and archive infinite scrolling, including non-rewrite URLs.
- Post expansion/collapse and dynamically loaded Alpine components.
- Anonymous and logged-in likes.
- Top-level comments and nested replies.
- Image gallery, video, and music-card rendering.
- Light/dark mode persistence.
- Frontend post creation with image/video limits.
- Friend-links page and modal loading, plus logged-in add/edit/delete flows.
- Album list, detail, editing, pinning, and Moments synchronization.
- Local and object-backed image publishing, partial-upload rollback, and
  Typecho attachment deletion.
- Mobile and desktop responsive layouts.

Do not claim PHP validation succeeded when `php` or a compatible Typecho runtime
is unavailable.

## Known Repository Gaps

These are existing repository conditions, not automatically part of unrelated
tasks:

- `README.md` claims GPL-3.0, but no `LICENSE` file is committed.
- `favicon.ico` is referenced but missing.
- `assets/fonts/HarmonyOS-Sans.woff2` is referenced but missing.
- The upstream companion plugin README declares MIT, but its release omits the
  referenced `LICENSE`; provenance is documented in `plugins/IcefoxPlugin/UPSTREAM.md`.
- Standard templates rely on browser HTML recovery for final document closing
  tags; `footer.php` currently only renders the back-to-top control.

Keep fixes scoped. Do not add a package manager, build system, or generated
vendor assets while addressing an unrelated theme change.
