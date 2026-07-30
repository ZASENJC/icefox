# Upstream provenance

The companion plugin baseline comes from `Icefox-Plugin-1.1.9.zip`, attached to
the upstream Icefox `3.0.3` GitHub release published on 2025-12-12:

<https://github.com/xiaopanglian/icefox/releases/tag/3.0.3>

SHA-256 of the downloaded archive:

```text
b37bc452709dfc21352a593f8ded5d4ceebf0398ca82338a3ac77815f04e22d8
```

The release accidentally contains the plugin repository's `.git` directory,
whose configured upstream is `https://gitee.com/xiaopanglian/icefox_plugin.git`.
Repository metadata and local `.claude` settings are intentionally excluded
from this copy.

The upstream README declares the plugin to be MIT-licensed but the referenced
`LICENSE` file is missing from both the release archive and repository history
included in that archive. The declaration is preserved in
`README.upstream.md`; redistributors should obtain the missing license text or
confirmation from the upstream author.
