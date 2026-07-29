# Icefox 主题与插件职责

这份文档说明代码应该放在主题还是配套 Icefox 插件中。判断原则很简单：页面怎么显示归主题，数据怎么写入和保存归插件或 Typecho。

## 主题直接实现

这些功能不需要 Icefox 插件：

- 首页、归档、文章和独立页面模板
- 响应式布局、深色模式、内容展开和无限滚动
- 从文章 HTML 提取图片或视频，显示九宫格和 Fancybox
- 音乐短代码和播放器
- 读取 Typecho 自带的文章、分类、标签、用户和评论
- Typecho 自带的登录、退出、搜索和后台链接
- 友情链接独立页面、弹窗、登录态管理和 `friendLinks` 页面字段持久化
- 通过 `isTop` 文章字段记录置顶状态，并让置顶文章优先显示
- 根据 `albumOnly` 文章字段决定是否在信息流显示

## 主题界面 + 插件后端

这些功能的界面留在主题，但数据请求必须由插件处理：

| 功能 | 主题负责 | 插件负责 |
| --- | --- | --- |
| 点赞 | 按钮、点赞人列表和状态更新 | `getLikes`、`like`、匿名用户标识和点赞数据 |
| 评论 | 表单、回复树展示 | `addComment` 的校验和写入 |
| 前台发布 | 编辑器、图片预览和选项 | `createPost`、媒体上传和文章写入 |
| 独立相册 | 相册列表、三列网格、灯箱和编辑弹窗 | `getAlbums`、`getAlbum`、`stageAlbumUpload`、`saveAlbum` |
| 对象存储 | 选择默认上传目标 | `Icefox` 调用 `IcefoxStorage` 上传、回滚和保存对象元数据 |

主题中的所有插件 URL 都必须通过 `window.ICEFOX_PLUGIN` 生成，不要在组件里手写 `?do=...`。

## 插件完全负责

下列逻辑不应放进主题：

- 注册 `/action/icefox` 和 `/albums/{slug}` 等服务端路由
- 验证用户权限、请求参数和私密相册访问
- 创建和升级 `icefox_*` 数据表
- 图片和视频上传、文件类型检查与存储
- 点赞、相册和“朋友圈”同步的持久化
- 从动态正文提取、去重并同步相册图片

友情链接是这里的例外：它使用 `links-page.php` 作为独立页面和同源读写入口，数据以 JSON 存在该页面的 Typecho `friendLinks` 自定义字段中。游客只读，登录用户写入时必须通过 Typecho CSRF token 校验。主题不再读取或创建插件的 `icefox_links` 表。

## 对象存储边界

`plugins/IcefoxStorage/` 是独立 Typecho 插件，负责 S3 Signature V4、R2/S3 配置、图片真实性与大小校验、上传、删除和公开 URL 生成。Access Key、Secret、Endpoint 和 Bucket 不得进入主题配置或浏览器全局变量。

`plugins/Icefox/` 读取主题提交的 `storage=local/object`，但必须在服务端白名单化。选择 `object` 时，只有图片交给 `IcefoxStorage`；视频继续使用本地存储。对象上传后若文章、附件、相册或朋友圈同步写入失败，伴生插件必须删除本次新对象作为补偿回滚。

相册选择对象存储时，未超过 PHP 单文件和整次请求上限的图片直接通过 `saveAlbum` 上传。只有超过限制或无法读取有效限制时，浏览器才通过 `stageAlbumUpload` 按 1MB 分片暂存原图，再由 `saveAlbum` 调用 `IcefoxStorage` 上传完整文件。两条路径都不会压缩图片，分片仅作为低上传上限环境的备用逻辑。

普通相册去重后最多保存 100 张照片。“朋友圈”相册不设数量上限，但 `saveAlbum` 不接受任何本地、远程或分片图片；其中的照片只能在发布动态并勾选同步选项时，由 `createPost` 被动写入。

数据库保存完整公开 URL，并额外保存 `storage` 和 `objectKey`。完整 URL 保证数据库恢复后可直接显示，`objectKey` 用于后台删除、存储迁移和失败清理。数据库备份不包含对象文件，存储桶仍需独立备份。

## 旧置顶数据迁移

文章置顶已改由主题的 Typecho `isTop` 文章字段管理。主题会在 Typecho 完成分类、标签、搜索、权限和分页条件后，把置顶文章排在最前，再按发布时间倒序排列。页面运行时不再读取插件表。

升级现有站点时，先备份数据库，再在主题目录执行：

```sh
TYPECHO_CONFIG=/absolute/path/to/typecho/config.inc.php php scripts/migrate-legacy-pins.php
```

该脚本只会把 `icefox_archive.is_top=1` 复制为 `isTop=1`。已经存在的 `isTop` 选择会被保留，插件表也不会被删除，因此脚本可以重复执行。

迁移完成后，统一在 Typecho 文章编辑页的“置顶文章”选项中管理。旧版插件后台如果仍显示“置顶/取消置顶”按钮，说明插件还没有完成职责迁移，不要再使用该按钮。

## 配套插件升级要求

配套插件源码位于 `plugins/Icefox/`。该版本已经完成以下清理：

- 删除 `Widget\\Archive` 的旧 `indexHandle` 置顶排序钩子
- 删除 `do=top`、`setTop` 和插件文章列表中的旧置顶按钮
- 前台创建文章时不再主动写入 `is_top`
- 如果 `icefox_archive` 仍保存点赞计数，可以保留表和 `likes` 数据；确认迁移和回滚期限结束后，再单独移除废弃的 `is_top` 列

相册排序还要求插件完成以下数据契约：

- 相册表新增非负整数 `sort_order`，默认值为 `0`，并为现有表提供幂等迁移
- `saveAlbum` 读取普通相册的 `sortOrder`，校验为非负整数后写入 `sort_order`
- `getAlbums` 和 `getAlbum` 返回数值型 `sortOrder`
- 相册列表依次按 `is_moments` 降序、`is_pinned` 降序、`sort_order` 升序排列；相同序号保留确定的原有顺序
- “朋友圈”相册忽略手动排序值，始终置于列表第一位

## 插件契约测试

`tests/album-plugin-*.sh` 和 `tests/album-plugin-moments-sync.php` 是插件契约测试，不是纯主题测试。它们需要通过 `ICEFOX_PLUGIN_ACTION`、`ICEFOX_PLUGIN_MAIN` 和可选的 `TYPECHO_CONFIG` 指向真实插件与 Typecho 环境。
