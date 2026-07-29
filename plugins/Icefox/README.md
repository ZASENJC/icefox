# Icefox companion plugin

This directory is the installable Typecho companion plugin for the Icefox
theme. Copy the directory to `usr/plugins/Icefox/` and enable it before using
the theme's likes, comments, frontend publishing, or albums. Friend links are
owned by the theme's independent `links-page.php` template.

The plugin owns `/action/icefox`, the `icefox_*` tables, media attachment
metadata, album ordering, and Moments synchronization. When a theme request
uses `storage=object`, image files are delegated to the separately installed
`IcefoxStorage` plugin. Videos remain local.

Album tags reuse Typecho's standard `metas` tag rows and store album-specific
relationships in `icefox_album_tags`; `metas.count` remains the published-post
count. Plugin activation creates the relationship table and backfills legacy
album tags. Deployments that replace plugin files without reactivation can run
`scripts/migrate-album-tags.php` with `TYPECHO_CONFIG` and
`ICEFOX_PLUGIN_MAIN` pointing to the installed files.

Object-backed album images use direct multipart upload while they fit within
PHP's reported `upload_max_filesize` and `post_max_size`. The existing 1 MB
chunk staging path remains as a fallback when those limits are exceeded or
cannot be detected, without exposing R2/S3 credentials to the browser.

The imported upstream source provenance and licensing caveat are documented in
`UPSTREAM.md`; the original release README is preserved as
`README.upstream.md`.
