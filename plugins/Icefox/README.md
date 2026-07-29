# Icefox companion plugin

This directory is the installable Typecho companion plugin for the Icefox
theme. Copy the directory to `usr/plugins/Icefox/` and enable it before using
the theme's likes, comments, frontend publishing, friend links, or albums.

The plugin owns `/action/icefox`, the `icefox_*` tables, media attachment
metadata, album ordering, and Moments synchronization. When a theme request
uses `storage=object`, image files are delegated to the separately installed
`IcefoxStorage` plugin. Videos remain local.

Object-backed album images are staged in 1 MB chunks before `saveAlbum`
uploads the complete original file. This keeps album uploads independent of a
host's low PHP `upload_max_filesize` default without exposing R2/S3 credentials
to the browser.

The imported upstream source provenance and licensing caveat are documented in
`UPSTREAM.md`; the original release README is preserved as
`README.upstream.md`.
