<?php

namespace TypechoPlugin\IcefoxStorage;

use Typecho\Db;
use Typecho\Common;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Plugins\Edit;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/S3Client.php';
require_once __DIR__ . '/StorageService.php';

/**
 * Icefox R2/S3 对象存储
 *
 * @package IcefoxStorage
 * @author Icefox contributors
 * @version 3.1.0
 * @link https://github.com/ZASENJC/icefox
 */
class Plugin implements PluginInterface
{
    const SECRET_OPTION = 'icefox_storage_secret';

    public static function activate()
    {
        TypechoPlugin::factory(\Widget\Upload::class)->attachmentHandle = [__CLASS__, 'attachmentHandle'];
        TypechoPlugin::factory(\Widget\Upload::class)->deleteHandle = [__CLASS__, 'deleteHandle'];
        return 'IcefoxStorage 已启用，请先完成存储桶配置。';
    }

    public static function deactivate()
    {
    }

    public static function config(Form $form)
    {
        $form->addInput(new Radio('provider', [
            'r2' => _t('Cloudflare R2'),
            's3' => _t('通用 S3 兼容存储')
        ], 'r2', _t('服务类型')));
        $form->addInput(new Text('endpoint', null, '', _t('Endpoint'), _t('例如 https://&lt;account-id&gt;.r2.cloudflarestorage.com')));
        $form->addInput(new Text('region', null, 'auto', _t('Region'), _t('Cloudflare R2 使用 auto，AWS S3 填写实际区域。')));
        $form->addInput(new Text('bucket', null, '', _t('Bucket')));
        $form->addInput(new Text('accessKey', null, '', _t('Access Key ID'), _t('也可通过 ICEFOX_STORAGE_ACCESS_KEY 环境变量配置。')));
        $form->addInput(new Password('secretKey', null, '', _t('Secret Access Key'), _t('保存后不会重新显示；留空表示保留现有密钥。也可使用 ICEFOX_STORAGE_SECRET_KEY 环境变量。')));
        $form->addInput(new Text('publicUrl', null, '', _t('公开访问域名'), _t('推荐填写长期固定的自定义域名，例如 https://img.example.com。')));
        $form->addInput(new Text('pathPrefix', null, 'icefox', _t('对象路径前缀')));
        $form->addInput(new Radio('pathStyle', [
            '1' => _t('路径模式（推荐用于 R2 和多数兼容服务）'),
            '0' => _t('虚拟主机模式')
        ], '1', _t('请求地址模式')));
        $form->addInput(new Text('maxFileSizeMb', null, '10', _t('单张图片上限（MB）')));
        $form->addInput(new Text('cacheControl', null, 'public, max-age=31536000, immutable', _t('Cache-Control')));
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function configCheck(array $settings)
    {
        foreach (['endpoint', 'bucket', 'accessKey', 'publicUrl'] as $required) {
            if (trim((string) ($settings[$required] ?? '')) === '') {
                return _t('对象存储配置不完整：%s 不能为空。', $required);
            }
        }

        foreach (['endpoint', 'publicUrl'] as $urlField) {
            $parts = parse_url((string) $settings[$urlField]);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
                return _t('%s 必须是有效的 HTTP/HTTPS 地址。', $urlField);
            }
        }

        return null;
    }

    public static function configHandle(array $settings, $isInit)
    {
        $secretKey = trim((string) ($settings['secretKey'] ?? ''));
        unset($settings['secretKey']);

        if ($secretKey !== '') {
            self::saveSecret($secretKey);
        }

        Edit::configPlugin('IcefoxStorage', $settings);
    }

    public static function isConfigured()
    {
        try {
            self::createService();
            return true;
        } catch (\Exception $error) {
            return false;
        }
    }

    public static function upload(array $file)
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('图片上传未成功到达服务器');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('无效的上传临时文件');
        }

        return self::createService()->uploadFile($file['tmp_name'], $file['name'] ?? 'image');
    }

    public static function uploadPath($filePath, $originalName)
    {
        return self::createService()->uploadFile((string) $filePath, (string) $originalName);
    }

    public static function maxFileSizeBytes()
    {
        $config = self::getConfig();
        return max(1, (int) ($config['maxFileSizeMb'] ?? 10)) * 1024 * 1024;
    }

    public static function delete($objectKey)
    {
        self::createService()->deleteObject($objectKey);
    }

    public static function cleanup(array $files)
    {
        self::createService()->cleanupUploadedObjects($files);
    }

    public static function attachmentHandle($attachment)
    {
        if (is_array($attachment)) {
            $attachment = $attachment['attachment'] ?? null;
        }
        if (!$attachment) {
            return '';
        }

        $path = (string) ($attachment->path ?? '');
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $options = \Widget\Options::alloc();
        return Common::url(
            $path,
            defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : $options->siteUrl
        );
    }

    public static function deleteHandle(array $content)
    {
        $attachment = $content['attachment'] ?? null;
        if (!$attachment) {
            return false;
        }

        $storage = (string) ($attachment->storage ?? 'local');
        $objectKey = (string) ($attachment->objectKey ?? '');
        if ($storage === 'object' && $objectKey !== '') {
            self::delete($objectKey);
            return true;
        }

        $path = (string) ($attachment->path ?? '');
        if ($path === '' || preg_match('#^https?://#i', $path)) {
            return true;
        }

        return @unlink(__TYPECHO_ROOT_DIR__ . '/' . ltrim($path, '/'));
    }

    public static function createService()
    {
        $config = self::getConfig();
        $client = new S3Client($config);
        return new StorageService($config, $client);
    }

    public static function getConfig()
    {
        $options = \Helper::options()->plugin('IcefoxStorage');
        $config = [
            'provider' => self::readOption($options, 'provider', 'r2'),
            'endpoint' => self::environmentValue('ICEFOX_STORAGE_ENDPOINT', self::readOption($options, 'endpoint', '')),
            'region' => self::environmentValue('ICEFOX_STORAGE_REGION', self::readOption($options, 'region', 'auto')),
            'bucket' => self::environmentValue('ICEFOX_STORAGE_BUCKET', self::readOption($options, 'bucket', '')),
            'accessKey' => self::environmentValue('ICEFOX_STORAGE_ACCESS_KEY', self::readOption($options, 'accessKey', '')),
            'secretKey' => self::environmentValue('ICEFOX_STORAGE_SECRET_KEY', self::loadSecret()),
            'publicUrl' => self::environmentValue('ICEFOX_STORAGE_PUBLIC_URL', self::readOption($options, 'publicUrl', '')),
            'pathPrefix' => self::readOption($options, 'pathPrefix', 'icefox'),
            'pathStyle' => self::readOption($options, 'pathStyle', '1') === '1',
            'maxFileSizeMb' => self::readOption($options, 'maxFileSizeMb', '10'),
            'cacheControl' => self::readOption($options, 'cacheControl', 'public, max-age=31536000, immutable')
        ];

        return $config;
    }

    private static function readOption($options, $name, $default)
    {
        if (is_object($options) && isset($options->{$name})) {
            return $options->{$name};
        }
        if (is_array($options) && array_key_exists($name, $options)) {
            return $options[$name];
        }
        return $default;
    }

    private static function environmentValue($name, $fallback)
    {
        if (defined($name) && constant($name) !== '') {
            return constant($name);
        }
        $value = getenv($name);
        return $value !== false && $value !== '' ? $value : $fallback;
    }

    private static function saveSecret($secret)
    {
        $db = Db::get();
        $existing = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', self::SECRET_OPTION)->where('user = ?', 0));
        if ($existing) {
            $db->query($db->update('table.options')->rows(['value' => $secret])->where('name = ?', self::SECRET_OPTION)->where('user = ?', 0));
            return;
        }

        $db->query($db->insert('table.options')->rows([
            'name' => self::SECRET_OPTION,
            'user' => 0,
            'value' => $secret
        ]));
    }

    private static function loadSecret()
    {
        $db = Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::SECRET_OPTION)->where('user = ?', 0));
        return $row ? (string) $row['value'] : '';
    }
}
