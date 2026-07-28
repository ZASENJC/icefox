<?php

namespace TypechoPlugin\IcefoxStorage;

class StorageService
{
    const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];

    private $publicUrl;
    private $pathPrefix;
    private $maxFileSize;
    private $cacheControl;
    private $client;
    private $clock;
    private $randomBytes;

    public function __construct(array $config, $client, callable $clock = null, callable $randomBytes = null)
    {
        $this->publicUrl = rtrim((string) ($config['publicUrl'] ?? ''), '/');
        $this->pathPrefix = trim((string) ($config['pathPrefix'] ?? 'icefox'), '/');
        $this->maxFileSize = max(1, (int) ($config['maxFileSizeMb'] ?? 10)) * 1024 * 1024;
        $this->cacheControl = trim((string) ($config['cacheControl'] ?? 'public, max-age=31536000, immutable'));
        $this->client = $client;
        $this->clock = $clock ?: 'time';
        $this->randomBytes = $randomBytes ?: 'random_bytes';

        $publicParts = parse_url($this->publicUrl);
        if (!$publicParts || !in_array($publicParts['scheme'] ?? '', ['http', 'https'], true) || empty($publicParts['host'])) {
            throw new \InvalidArgumentException('对象存储公开访问域名格式不正确');
        }
        if ($this->pathPrefix !== '' && !preg_match('#^[A-Za-z0-9_/-]+$#', $this->pathPrefix)) {
            throw new \InvalidArgumentException('对象路径前缀只能包含字母、数字、斜杠、下划线和连字符');
        }
        if (strpos('/' . $this->pathPrefix . '/', '/../') !== false || strpos('/' . $this->pathPrefix . '/', '/./') !== false) {
            throw new \InvalidArgumentException('对象路径前缀不能包含相对路径');
        }
    }

    public function uploadFile($filePath, $originalName)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException('上传临时文件不存在或不可读');
        }

        $size = filesize($filePath);
        if ($size === false || $size <= 0) {
            throw new \InvalidArgumentException('不能上传空文件');
        }
        if ($size > $this->maxFileSize) {
            throw new \InvalidArgumentException('图片大小超过对象存储插件限制');
        }

        $mime = $this->detectMimeType($filePath);
        if ($mime === 'image/svg+xml' || strpos($mime, 'image/svg') === 0) {
            throw new \InvalidArgumentException('不允许上传 SVG 图片');
        }
        if (!isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
            throw new \InvalidArgumentException('仅支持 JPEG、PNG、GIF 和 WebP 图片');
        }

        $timestamp = (int) call_user_func($this->clock);
        $random = call_user_func($this->randomBytes, 16);
        if (!is_string($random) || strlen($random) !== 16) {
            throw new \RuntimeException('无法生成安全的对象文件名');
        }

        $segments = [];
        if ($this->pathPrefix !== '') {
            $segments[] = $this->pathPrefix;
        }
        $segments[] = gmdate('Y/m', $timestamp);
        $segments[] = bin2hex($random) . '.' . self::ALLOWED_IMAGE_TYPES[$mime];
        $objectKey = implode('/', $segments);
        $headers = ['Content-Type' => $mime];
        if ($this->cacheControl !== '') {
            $headers['Cache-Control'] = $this->cacheControl;
        }

        $this->client->putObject($objectKey, $filePath, $headers);
        $publicUrl = $this->buildPublicUrl($objectKey);

        return [
            'name' => $this->normalizeOriginalName($originalName),
            'path' => $publicUrl,
            'url' => $publicUrl,
            'storage' => 'object',
            'objectKey' => $objectKey,
            'type' => 'image',
            'mime' => $mime,
            'size' => $size
        ];
    }

    public function deleteObject($objectKey)
    {
        $this->client->deleteObject((string) $objectKey);
    }

    public function cleanupUploadedObjects(array $files)
    {
        foreach ($files as $file) {
            if (($file['storage'] ?? '') !== 'object' || empty($file['objectKey'])) {
                continue;
            }

            try {
                $this->deleteObject($file['objectKey']);
            } catch (\Exception $error) {
                error_log('IcefoxStorage rollback failed for object key ' . $file['objectKey']);
            }
        }
    }

    private function detectMimeType($filePath)
    {
        if (!function_exists('finfo_open')) {
            throw new \RuntimeException('服务器未安装 PHP Fileinfo 扩展');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new \RuntimeException('无法初始化文件类型检查');
        }
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return strtolower(trim((string) $mime));
    }

    private function buildPublicUrl($objectKey)
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $objectKey)));
        return $this->publicUrl . '/' . $encoded;
    }

    private function normalizeOriginalName($name)
    {
        $name = basename(str_replace("\\", '/', (string) $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name);
        return $name !== '' ? $name : 'image';
    }
}
