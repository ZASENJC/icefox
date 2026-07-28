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
- 通过 `isTop` 文章字段记录置顶状态，并让置顶文章优先显示
- 根据 `albumOnly` 文章字段决定是否在信息流显示

## 主题界面 + 插件后端

这些功能的界面留在主题，但数据请求必须由插件处理：

| 功能 | 主题负责 | 插件负责 |
| --- | --- | --- |
| 点赞 | 按钮、点赞人列表和状态更新 | `getLikes`、`like`、匿名用户标识和点赞数据 |
| 评论 | 表单、回复树展示 | `addComment` 的校验和写入 |
| 友情链接 | 弹窗和列表样式 | `getFriendLinks` 和链接数据 |
| 前台发布 | 编辑器、图片预览和选项 | `createPost`、媒体上传和文章写入 |
| 独立相册 | 相册列表、三列网格、灯箱和编辑弹窗 | `getAlbums`、`getAlbum`、`saveAlbum` |

主题中的所有插件 URL 都必须通过 `window.ICEFOX_PLUGIN` 生成，不要在组件里手写 `?do=...`。

## 插件完全负责

下列逻辑不应放进主题：

- 注册 `/action/icefox` 和 `/albums/{slug}` 等服务端路由
- 验证用户权限、请求参数和私密相册访问
- 创建和升级 `icefox_*` 数据表
- 图片和视频上传、文件类型检查与存储
- 点赞、友情链接、相册和“朋友圈”同步的持久化
- 从动态正文提取、去重并同步相册图片

## 旧置顶数据迁移

文章置顶已改由主题的 Typecho `isTop` 文章字段管理。主题会在 Typecho 完成分类、标签、搜索、权限和分页条件后，把置顶文章排在最前，再按发布时间倒序排列。页面运行时不再读取插件表。

升级现有站点时，先备份数据库，再在主题目录执行：

```sh
TYPECHO_CONFIG=/absolute/path/to/typecho/config.inc.php php scripts/migrate-legacy-pins.php
```

该脚本只会把 `icefox_archive.is_top=1` 复制为 `isTop=1`。已经存在的 `isTop` 选择会被保留，插件表也不会被删除，因此脚本可以重复执行。

迁移完成后，统一在 Typecho 文章编辑页的“置顶文章”选项中管理。旧版插件后台如果仍显示“置顶/取消置顶”按钮，说明插件还没有完成职责迁移，不要再使用该按钮。

## 配套插件升级要求

配套插件源码不在本主题仓库中。发布与本主题配套的新插件版本时，需要完成以下清理：

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
