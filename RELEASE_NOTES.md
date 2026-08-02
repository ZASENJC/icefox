# Icefox 3.1.4

Icefox 3.1.4 优化前台发布动态时的图片添加体验。主题、IcefoxPlugin 伴生插件和 IcefoxStorage 对象存储插件继续使用统一版本号。

## 更新内容

- 发布动态时支持直接粘贴剪贴板中的图片，图片会加入现有待发布媒体列表。
- 首页发布弹窗和独立发布页面使用相同的剪贴板图片处理逻辑，并继续遵守最多 9 个媒体文件的限制。
- 发布图片区固定为一行三列等宽方格，图片和添加按钮保持相同尺寸，添加按钮紧跟在已有图片右侧。
- 主题主样式使用 CSS 文件修改时间生成缓存版本，直接覆盖主题文件后浏览器会自动加载新布局。

## 升级方式

本版本没有新的数据库或配置迁移。已完成 3.1.3 伴生插件目录迁移的站点可以直接覆盖以下目录：

1. 将主题包覆盖到 `usr/themes/icefox/`。
2. 将伴生插件包覆盖到 `usr/plugins/IcefoxPlugin/`。
3. 使用 R2/S3 时，将对象存储插件包覆盖到 `usr/plugins/IcefoxStorage/`。

覆盖前仍建议备份 Typecho 数据库、主题配置和本地上传目录。

从 3.1.2 或更早版本跨版本升级时，不要把新伴生插件解压到旧的 `usr/plugins/Icefox/`。请将 `IcefoxPlugin/` 并列安装到 `usr/plugins/IcefoxPlugin/` 并启用一次；插件会自动迁移旧配置、停用旧插件键并更新路由，确认正常后再删除旧目录。

## 发行文件

- `icefox-3.1.4.zip`：主题，解压到 `usr/themes/icefox/`。
- `Icefox-Plugin-3.1.4.zip`：必需的伴生插件，解压到 `usr/plugins/IcefoxPlugin/`。
- `IcefoxStorage-3.1.4.zip`：使用 R2/S3 时安装，解压到 `usr/plugins/IcefoxStorage/`。
- `SHA256SUMS`：三个 ZIP 文件的 SHA-256 校验和。

## 兼容性

- Typecho `>= 1.2.0`
- PHP `>= 7.0`
- 支持从 3.1.3 直接覆盖升级，并支持从 3.1.2 或更早版本执行一次性的伴生插件目录迁移，保留现有配置和数据。
