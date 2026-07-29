# IcefoxStorage

IcefoxStorage 是 Icefox 的独立 R2/S3 对象存储插件。将 `IcefoxStorage`
目录安装到 Typecho 的 `usr/plugins/` 后启用，并在插件设置页填写 Endpoint、
Region、Bucket、访问凭证、公开访问域名和对象路径前缀。

Cloudflare R2 的 Region 使用 `auto`。Endpoint 是 S3 API 地址，公开访问域名则
应填写绑定到存储桶的长期稳定域名；文章正文和相册数据库保存的是后者。

Secret Access Key 保存后不会回显。生产环境优先使用以下环境变量：

- `ICEFOX_STORAGE_ENDPOINT`
- `ICEFOX_STORAGE_REGION`
- `ICEFOX_STORAGE_BUCKET`
- `ICEFOX_STORAGE_ACCESS_KEY`
- `ICEFOX_STORAGE_SECRET_KEY`
- `ICEFOX_STORAGE_PUBLIC_URL`

插件要求 PHP `fileinfo` 和 `curl` 扩展。对象存储失败时不会静默回退本地，避免
在管理员认为图片已经进入存储桶时产生未备份的本地文件。

相册图片由 Icefox 伴生插件按 1MB 分片暂存后再上传完整原图，因此不受 PHP
默认 2MB 文件上传上限影响。Typecho 后台原生附件上传仍受服务器自身的
`upload_max_filesize` 和 `post_max_size` 配置约束。
