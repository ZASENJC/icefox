# Icefox - Typecho 博客主题

![Version](https://img.shields.io/badge/version-3.1.1-blue)
![Typecho](https://img.shields.io/badge/typecho-%3E%3D1.2.0-orange)
![PHP](https://img.shields.io/badge/php-%3E%3D7.0.0-purple)

一个现代化、响应式的 Typecho 博客主题，采用 Bulma 和 Alpine.js 构建，提供流畅的用户体验和丰富的交互功能。

## ✨ 核心特性

### 🎨 界面设计
- **响应式布局** - 基于 Bulma CSS 框架，适配各种设备
- **主题切换** - 支持日间/夜间模式切换
- **现代化 UI** - 简洁优雅的视觉设计
- **图片灯箱** - 使用 Fancybox 展示图片

### 🚀 核心功能
- **点赞系统** - 支持匿名和登录用户点赞
- **评论系统** - 支持嵌套回复，实时交互
- **无限滚动** - 自动加载更多文章
- **音乐卡片** - 内置音乐播放器短代码
- **内容展开/收起** - 长文章智能折叠
- **友情链接** - 独立页面、全站弹窗和登录态直接管理
- **独立相册** - 相册首页、相册详情、三列照片网格和登录后的相册编辑

### 📄 特色页面
- **归档页面** - 优雅的文章归档展示
- **编辑页面** - 可视化内容编辑

## 📦 技术栈

### 后端
- **PHP** >= 7.0.0
- **Typecho** >= 1.2.0
- **MySQL/MariaDB**

### 前端
- **CSS**: Bulma + 主题样式
- **JavaScript 库**:
  - jQuery - DOM 操作
  - Alpine.js - 响应式状态管理
  - Fancybox - 图片灯箱
  - ScrollLoad - 无限滚动

### 字体
- HarmonyOS Sans
- DingTalk

## 📥 安装

### 前置要求
- Typecho >= 1.2.0
- PHP >= 7.0.0
- MySQL/MariaDB 数据库

### 安装步骤

1. **下载主题**
   ```bash
   cd /path/to/typecho/usr/themes/
   git clone https://github.com/xiaopanglian/icefox.git
   ```

2. **安装配套插件**（必需）

   源码仓库在 `plugins/Icefox/` 中包含配套插件；从 GitHub Release 安装时，请下载同版本的 `Icefox-Plugin-3.1.1.zip`。解压或复制到 Typecho 插件目录并启用：
   ```bash
   cp -R plugins/Icefox /path/to/typecho/usr/plugins/Icefox
   ```

   插件负责：
   - 创建和升级点赞、相册等插件数据
   - 注册 `/action/icefox` 和相册详情路由
   - 处理点赞、评论写入、前台发布和相册接口

   详细的主题/插件分工见 [`docs/plugin-boundaries.md`](docs/plugin-boundaries.md)。

   旧版文章置顶数据需按分工文档运行一次迁移脚本；新版置顶由主题的 `isTop` 文章字段直接管理和排序。迁移后请使用 Typecho 文章编辑页的“置顶文章”，不要再使用旧插件后台的置顶按钮。

3. **安装对象存储插件**（使用 R2/S3 时必需）

   从 GitHub Release 安装时，请下载同版本的 `IcefoxStorage-3.1.1.zip`。

   ```bash
   cp -R plugins/IcefoxStorage /path/to/typecho/usr/plugins/IcefoxStorage
   ```

   启用 `IcefoxStorage`，在插件设置页填写 Endpoint、Region、Bucket、访问凭证、公开访问域名和路径前缀。Cloudflare R2 的 Region 使用 `auto`。Endpoint 是 S3 API 地址，公开访问域名应填写绑定到存储桶的稳定图片域名。

   建议同时把仓库中的 [`deploy/php-uploads.ini`](deploy/php-uploads.ini) 加载到 PHP。Dockerfile 可以直接复制该文件：

   ```dockerfile
   COPY deploy/php-uploads.ini /usr/local/etc/php/conf.d/icefox-uploads.ini
   ```

   其他部署方式、Nginx 配置和生效验证见下方 [PHP 上传限制](#php-上传限制)。

4. **启用主题**

   登录 Typecho 后台 → 外观 → 启用 Icefox 主题

5. **配置主题**

   在主题设置页面将“图片默认上传位置”设为“R2/S3 对象存储”。该选项只影响图片，视频仍使用本地存储。

## 🗂️ 目录结构

```
icefox/
├── assets/              # 静态资源
│   ├── css/             # 样式文件
│   ├── js/              # JavaScript 脚本
│   ├── fonts/           # 字体文件
│   └── images/          # 图片资源
├── components/          # 组件目录
│   ├── modals/          # 模态框组件
│   ├── post/            # 文章相关组件
│   └── svgs/            # SVG 图标
├── core/                # 核心工具函数
├── plugins/
│   ├── Icefox/          # 配套数据与上传插件
│   └── IcefoxStorage/   # 独立 R2/S3 对象存储插件
├── index.php            # 首页模板
├── header.php           # 头部模板
├── footer.php           # 底部模板
├── post.php             # 文章详情页
├── page.php             # 独立页面
├── links-page.php       # 友情链接独立页面模板
├── album-page.php       # 相册独立页面模板
├── archive.php          # 归档页
├── functions.php        # 主题函数库
└── comment_function.php # 评论函数
```

## 🎯 使用说明

### 音乐卡片短代码

在文章中插入音乐播放器：
```
[music title="歌曲名" artist="歌手" cover="封面图URL" src="音频URL"]
```

### 自定义样式

主题支持通过后台 "自定义 CSS" 添加样式，或直接编辑 `assets/css/icefox.css`

### 主题切换

用户可通过页面右上角的图标切换日间/夜间模式，设置会自动保存到 localStorage

### R2/S3 图片存储与恢复

选择对象存储后，动态和相册上传的图片会先写入 R2/S3。文章正文、Typecho 附件元数据和相册照片数据保存完整公开 URL，同时保留 `objectKey` 供删除和迁移使用。对象存储失败会终止发布，不会静默保存到本地。

相册图片在单文件和整次请求均不超过 PHP 上限时直接上传；只有超过 `upload_max_filesize`、预计超过 `post_max_size`，或服务器未能报告有效限制时，才使用 1MB 分片暂存作为备用逻辑。两种路径最终都会由服务端上传完整原图到 R2/S3。

建议使用长期固定的自定义图片域名，例如 `https://img.example.com`。恢复数据库后，只要该域名、存储桶和对象仍然存在，历史图片无需迁移即可继续显示。

数据库备份只保存图片引用，不包含图片文件。完整灾备必须同时包含：

- Typecho 数据库备份
- R2/S3 存储桶同步或快照
- 自定义域名配置
- `Icefox` 与 `IcefoxStorage` 插件代码
- 独立安全保存的上传凭证

### 相册页面

1. 在 Typecho 后台新建一个独立页面，选择 `album-page.php` 模板。
2. 相册入口由配套插件固定注册为 `/albums`，相册详情使用 `/albums/{相册名称拼音}`，无需在主题设置中填写页面地址。主题设置中的“相册页顶部图片”可自定义页面头图；“显示‘朋友圈’相册”默认开启，关闭后只隐藏该相册，不删除已同步图片。
3. 配套 `icefox` 插件需要提供相册数据和写入动作：`getAlbums`（GET）、`getAlbum`（GET，参数 `album`）和 `saveAlbum`（POST multipart）。主题会为缺失的“朋友圈”相册补充 `moments` 入口；插件需支持 `getAlbum&album=moments`，并返回同步到该相册的图片。
4. 插件返回的相册对象可使用 `id`/`slug`、`name`、`description`、`cover`、`tags`、`address`、`visibility`、`isPinned`、`sortOrder` 和 `photos` 字段；“朋友圈”始终排在最前，其他相册先显示置顶项，再按 `sortOrder` 从小到大稳定排序。未设置 `cover` 时主题使用 `photos` 的第一张图片作为封面。
5. 文章编辑页中的“相册内容”字段开启后，主题会从博客首页、归档和搜索结果中过滤该图文；`albumId` 可用于把图文关联到插件相册。
6. 前端发布动态时可开启“同步到「朋友圈」相册”；主题会向 `createPost` 发送 `syncToAlbum=1`，配套插件负责把正文中的 Markdown/HTML 图片和本次上传的图片去重后追加到具有稳定身份标识的“朋友圈”相册，动态本身仍保留在信息流中。

### 友情链接页面

1. 在 Typecho 后台新建并公开发布一个独立页面，选择 `links-page.php` 模板，建议缩略名使用 `links`。
2. 在主题设置中把“友情链接页面地址”设为该页面地址；留空时主题会根据站点地址生成 `links` 路径。
3. 游客可以从顶部入口打开友链弹窗或访问独立页面。任意 Typecho 登录用户都可以在弹窗或页面中添加、编辑和删除友链。
4. 友链以 JSON 保存在该独立页面的 `friendLinks` 自定义字段中，不依赖 Icefox 插件或额外数据表。

从旧版插件升级时，先备份数据库并创建友情链接页面，再复制旧数据：

```sh
TYPECHO_CONFIG=/absolute/path/to/typecho/config.inc.php \
FRIEND_LINKS_PAGE_CID=123 \
php scripts/migrate-plugin-links.php
```

脚本会校验写入后的 SHA-256，并保留原 `icefox_links` 表。目标页面已经存在友链数据时默认拒绝覆盖；确认后可显式设置 `ICEFOX_OVERWRITE_LINKS=1`。

## 🛠️ 开发指南

### 修改主题样式
编辑 `assets/css/icefox.css` 或在后台添加自定义 CSS

### 添加新功能
1. **主题功能**: 在 `functions.php` 中添加
2. **前端交互**: 在 `assets/js/icefox.js` 中添加
3. **API 接口**: 在插件的 `Action.php` 中添加

### 数据库查询
使用 Typecho 的数据库 API：
```php
$db = Typecho_Db::get();
$posts = $db->fetchAll(
    $db->select()
       ->from('table.contents')
       ->where('status = ?', 'publish')
);
```

## 🔌 API 接口

所有接口通过 `/action/icefox?do={action}` 访问：

| 接口 | 方法 | 说明 |
|------|------|------|
| `do=getLikes` | GET | 获取文章点赞数据 |
| `do=like` | POST | 切换点赞状态 |
| `do=addComment` | POST | 添加评论 |
| `do=createPost` | POST multipart | 发布动态；`storage=object` 上传图片到 R2/S3，`syncToAlbum=1` 时同步到“朋友圈”相册 |
| `do=getAlbums` | GET | 获取可见相册列表 |
| `do=getAlbum&album={id}` | GET | 获取相册详情和照片 |
| `do=stageAlbumUpload` | POST binary | PHP 上传限制不足时暂存相册图片分片 |
| `do=saveAlbum` | POST multipart | 新建或编辑相册并上传照片；支持 `description`、`storage=local/object`、`isPinned` 和 `sortOrder` |

## ⚙️ 配置要求

### PHP 扩展
- PDO
- mbstring
- json
- fileinfo
- curl（使用 R2/S3 时）

### PHP 上传限制

PHP 会在 Icefox 插件运行前解析上传请求，因此不能在插件代码中通过 `ini_set()` 临时提高这些限制。推荐加载仓库中的 [`deploy/php-uploads.ini`](deploy/php-uploads.ini)：

```ini
upload_max_filesize = 20M
post_max_size = 512M
max_file_uploads = 100
```

| 配置项 | 作用 |
| --- | --- |
| `upload_max_filesize = 20M` | 每个上传文件最大 20MB。超过该值的单个文件不能通过普通 multipart 上传。 |
| `post_max_size = 512M` | 一次 HTTP POST 请求的总上限，包括所有文件、表单字段和 multipart 开销；必须大于 `upload_max_filesize`。 |
| `max_file_uploads = 100` | 一次 multipart 请求最多接收 100 个文件。它只限制数量，不代表可以同时上传 100 个 20MB 文件，整次请求仍受 `post_max_size` 限制。 |

#### Docker 或 Docker Compose

使用基于官方 PHP 镜像的 Dockerfile 时，把配置复制到 PHP 自动扫描的 `conf.d` 目录：

```dockerfile
COPY deploy/php-uploads.ini /usr/local/etc/php/conf.d/99-icefox-uploads.ini
```

重新构建并创建 PHP/Typecho 容器，服务名按自己的 `compose.yml` 修改：

```bash
docker compose build typecho
docker compose up -d --force-recreate typecho
```

不构建自定义镜像时，也可以在 `compose.yml` 中只读挂载配置：

```yaml
services:
  typecho:
    volumes:
      - ./deploy/php-uploads.ini:/usr/local/etc/php/conf.d/99-icefox-uploads.ini:ro
```

修改挂载后同样运行 `docker compose up -d --force-recreate typecho`。

#### PHP-FPM 或 Apache

先用 `php --ini` 查看当前 PHP CLI 的配置目录，再确认网站使用的 PHP-FPM 或 Apache SAPI 版本。CLI、PHP-FPM 和 Apache 可能读取不同目录，不能只修改 CLI 的 `php.ini`。

以 Debian/Ubuntu 的 PHP 8.2 FPM 为例：

```bash
sudo install -m 0644 deploy/php-uploads.ini /etc/php/8.2/fpm/conf.d/99-icefox-uploads.ini
sudo systemctl restart php8.2-fpm
```

如果使用 Apache `mod_php`，目标目录通常是 `/etc/php/8.2/apache2/conf.d/`，复制后运行 `sudo systemctl restart apache2`。请将示例中的 `8.2` 替换为服务器实际 PHP 版本。

#### 共享主机的 `.user.ini`

无法修改系统 PHP 配置时，可以在 Typecho 根目录创建或修改 `.user.ini`：

```ini
upload_max_filesize = 20M
post_max_size = 512M
max_file_uploads = 100
```

`.user.ini` 只对 PHP-FPM/CGI 生效，且主机商可能设置不可突破的上限。修改后通常需要等待最多 5 分钟，或在主机面板中重启 PHP。

#### Nginx 和其他反向代理

Nginx 必须允许完整请求到达 PHP。在对应的 `http`、`server` 或 Typecho `location` 配置中加入：

```nginx
client_max_body_size 512M;
```

然后检查并重新加载配置：

```bash
sudo nginx -t
sudo systemctl reload nginx
```

如果前面还有 CDN、面板反向代理或网关，它们的请求体上限也不能低于实际上传大小。出现 `413 Request Entity Too Large` 通常表示请求在 Nginx、CDN 或其他代理层就被拒绝，尚未到达 Icefox。

#### 验证是否生效

Docker 环境可以在 PHP/Typecho 容器内检查，服务名按实际配置替换：

```bash
docker compose exec typecho php -r 'foreach (["upload_max_filesize", "post_max_size", "max_file_uploads"] as $key) { echo $key, "=", ini_get($key), PHP_EOL; }'
```

普通服务器可以运行同一段 `php -r` 命令，但要注意 CLI 配置可能不同于网站使用的 PHP-FPM。访问 Icefox 页面后，还可以在浏览器控制台检查 Web 运行时实际读取的前两项：

```js
window.ICEFOX_CONFIG.phpUploadMaxBytes // 20971520，即 20MB
window.ICEFOX_CONFIG.phpPostMaxBytes   // 536870912，即 512MB
```

如果命令或页面仍显示旧值，通常是配置文件放错 SAPI 目录、PHP/容器尚未重启，或主机商限制了最大值。

配置容量允许时，相册图片使用普通 multipart 直接上传；单个文件或整次请求超过 PHP 上限时，对象存储相册会自动改用 1MB 分片暂存。该备用逻辑不绕过视频上传、Typecho 后台原生附件上传或反向代理的限制，这些请求仍需要正确配置 PHP 和 Web 服务器。

### 数据库
- 支持外键约束
- InnoDB 引擎

## 🐛 常见问题

### 点赞/评论功能不工作
请确保已安装并启用 `icefox` 插件

### 无限滚动不生效
检查 `assets/js/icefox.js` 是否正确加载

### 样式错乱
清除浏览器缓存，确保 `assets/css/` 目录下的文件完整

## 📄 许可证

本项目采用 GPL-3.0 许可证 - 详见 [LICENSE](LICENSE) 文件

## 👤 作者

**小胖脸**
- 网站: [https://xiaopanglian.com](https://xiaopanglian.com)

## 🙏 致谢

- [Typecho](http://typecho.org/) - 优秀的博客平台
- [Bulma](https://bulma.io/) - 现代化 CSS 框架
- [Alpine.js](https://alpinejs.dev/) - 轻量级响应式框架

## 📝 更新日志

### v3.1.1 (2026)
- 修复 Typecho 1.2.x 查询构造器导致 Icefox 插件重新启用时报 `Database Query Error` 的相册标签迁移问题
- 保持相册说明、排序、标签关系和旧表结构的幂等迁移，旧用户覆盖后重新启用即可完成升级
- 发布主题、Icefox 伴生插件和 IcefoxStorage 的统一 3.1.1 版本包

### v3.1.0 (2026)
- 新增独立相册、相册标签、说明、排序、置顶和朋友圈同步
- 新增 R2/S3 对象存储插件与大文件分片备用上传
- 将友情链接迁移为主题独立页面字段，并支持前台管理
- 新增全局文章置顶、顶部首页入口和信息流返回位置恢复
- 加强发布、评论、点赞、上传和插件接口的安全与兼容性

### v3.0.0 (2024)
- ✨ 全新设计的 UI 界面
- ✨ 新增点赞系统
- ✨ 优化评论交互
- ✨ 新增音乐卡片功能
- 🐛 修复若干已知问题

---

如有问题或建议，欢迎提交 [Issue](https://github.com/yourusername/icefox/issues) 或 [Pull Request](https://github.com/yourusername/icefox/pulls)
