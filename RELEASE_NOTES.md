# Icefox 3.1.3

Icefox 3.1.3 将必需的伴生插件目录由 `Icefox` 更名为 `IcefoxPlugin`，避免与 `icefox` 主题名称和目录混淆。主题、IcefoxPlugin 伴生插件和 IcefoxStorage 对象存储插件继续使用统一版本号。

## 重要：伴生插件目录迁移

本次升级包含一次性的目录名变更。`Icefox-Plugin-3.1.3.zip` 的文件名保持不变，但压缩包内的根目录已经改为 `IcefoxPlugin/`。

现有用户不要把新文件解压到旧的 `usr/plugins/Icefox/` 目录，也不要先卸载旧插件。请按以下顺序升级：

1. 备份 Typecho 数据库、主题配置和本地上传目录；使用 R2/S3 时同时备份存储桶。
2. 上传并覆盖主题目录 `usr/themes/icefox/`，以及已安装的 `usr/plugins/IcefoxStorage/`。
3. 将压缩包内的 `IcefoxPlugin/` 并列上传到 `usr/plugins/IcefoxPlugin/`，暂时保留旧的 `usr/plugins/Icefox/`。
4. 在 Typecho 后台启用 `IcefoxPlugin`。首次启用会自动复制旧插件配置、移除旧 `Icefox` 启用记录并重新注册插件路由；原有点赞、相册、附件和主题数据不会被删除。
5. 验证首页、点赞、评论和相册功能正常后，删除已经停用的旧目录 `usr/plugins/Icefox/`。

完成本次迁移后，后续版本可以直接覆盖 `usr/plugins/IcefoxPlugin/` 升级。

## 更新内容

- 伴生插件 PHP 命名空间同步调整为 `TypechoPlugin\IcefoxPlugin`，主题在迁移期间兼容查找新旧插件类。
- 插件首次启用时自动迁移旧配置和启用记录，并清理旧路由后注册新路由，避免目录改名后出现路由冲突。
- 朋友圈相册调整为只读：不再显示编辑入口，前端拒绝打开编辑器，服务端也拒绝修改；图片仍由动态同步，是否显示继续由主题设置控制。
- 为本次变更的主题样式和脚本增加 `3.1.3` 缓存键，覆盖主题文件后即可加载新资源。

## 发行文件

- `icefox-3.1.3.zip`：主题，解压到 `usr/themes/icefox/`。
- `Icefox-Plugin-3.1.3.zip`：必需的伴生插件，压缩包根目录为 `IcefoxPlugin/`，按上方步骤安装到 `usr/plugins/IcefoxPlugin/`。
- `IcefoxStorage-3.1.3.zip`：使用 R2/S3 时安装，解压到 `usr/plugins/IcefoxStorage/`。
- `SHA256SUMS`：三个 ZIP 文件的 SHA-256 校验和。

## 兼容性

- Typecho `>= 1.2.0`
- PHP `>= 7.0`
- 支持从旧 `usr/plugins/Icefox/` 安装原地迁移，保留现有配置和数据。
