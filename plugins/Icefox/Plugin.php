<?php

namespace TypechoPlugin\Icefox;

use Typecho\Common;
use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Db;
use Typecho\Router;
use Typecho\Validate;
use Widget\Base\Metas;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox插件是icefox主题的适配插件，需搭配icefox主题使用
 * @package Icefox
 * @author 小胖脸
 * @version 3.1.0
 * @link https://xiaopanglian.com
 */

class Plugin implements PluginInterface
{
    private static $albumTagSchemaReady = false;

    /**
     * 激活插件方法,如果激活失败直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        if (version_compare( phpversion(), '7.0.0', '<' ) ) {
            throw new Exception('请升级到 php 7 以上');
        }
        if(version_compare(Common::VERSION,'1.2.0') < 0){
            throw new Exception('请更新typecho到1.2.0 以上');
        }
        self::checkAndCreateTable();

        // 初始化插件配置，防止进入设置页时缺少配置记录导致报错
        \Utils\Helper::configPlugin('Icefox', ['icefox_init' => '1']);

        // 注册接口路由
        \Helper::addRoute('icefox_route', '/action/icefox', Action::class, 'action');
        \Helper::addRoute('icefox_albums', '/albums', AlbumArchive::class, 'render', 'index');
        \Helper::addRoute('icefox_album_detail', '/albums/[album]', AlbumArchive::class, 'render', 'icefox_albums');

        return 'Icefox 伴生插件已启用';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 移除路由
        \Helper::removeRoute('icefox_route');
        \Helper::removeRoute('icefox_albums');
        \Helper::removeRoute('icefox_album_detail');
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param \Typecho\Widget\Helper\Form $form 配置面板
     * @return void
     */
    public static function config(\Typecho\Widget\Helper\Form $form)
    {
        // 保留插件配置记录；友情链接现由主题独立页面管理。
        $form->addInput(new Hidden('icefox_init', null, '1', 'icefox_init'));
    }

    /**
     * 处理插件配置保存
     *
     * @param array $settings
     * @param bool $isInit
     * @return void
     */
    public static function configHandle(array $settings, bool $isInit)
    {
        \Utils\Helper::configPlugin('Icefox', $settings);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param \Typecho\Widget\Helper\Form $form
     * @return void
     */
    public static function personalConfig(\Typecho\Widget\Helper\Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @param $hed
     * @return string
     * @throws Typecho_Exception
     */
    public static function renderHeader($hed,$new)
    {
    }

    public static function renderFooter()
    {

    }

    // 检查并创建所需数据表
    private static function checkAndCreateTable()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 创建文章扩展信息表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_archive` (
            `cid` int(10) unsigned NOT NULL, -- 文章Id
            `is_top` tinyint(1) NOT NULL DEFAULT '0', -- 是否置顶
            `likes` int(10) unsigned NOT NULL DEFAULT '0', -- 点赞总数
            PRIMARY KEY (`cid`),
            FOREIGN KEY (`cid`) REFERENCES `{$prefix}contents`(`cid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);

        // 创建点赞记录表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_likes` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `cid` int(10) unsigned NOT NULL, -- 文章Id
            `uid` int(10) unsigned DEFAULT NULL, -- 用户Id（登录用户）
            `author` varchar(150) DEFAULT NULL, -- 用户昵称
            `mail` varchar(200) DEFAULT NULL, -- 用户邮箱
            `ip` varchar(45) DEFAULT NULL, -- IP地址
            `anonymous_id` varchar(64) DEFAULT NULL, -- 匿名用户唯一标识
            `created_at` int(10) unsigned NOT NULL, -- 点赞时间
            PRIMARY KEY (`id`),
            KEY `idx_cid` (`cid`),
            KEY `idx_mail_ip` (`mail`(100), `ip`),
            KEY `idx_anonymous` (`anonymous_id`),
            FOREIGN KEY (`cid`) REFERENCES `{$prefix}contents`(`cid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);

        // 检查是否需要添加新字段（用于已有数据库升级）
        $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$prefix}icefox_likes`"));
        $columnNames = array_column($columns, 'Field');

        if (!in_array('author', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `author` varchar(150) DEFAULT NULL AFTER `uid`");
        }
        if (!in_array('mail', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `mail` varchar(200) DEFAULT NULL AFTER `author`");
        }
        if (!in_array('anonymous_id', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `anonymous_id` varchar(64) DEFAULT NULL AFTER `ip`");
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD KEY `idx_anonymous` (`anonymous_id`)");
        }

        // 删除旧的唯一索引，因为现在通过邮箱和IP来识别
        // 先检查索引是否存在
        $indexes = $db->fetchAll($db->query("SHOW INDEX FROM `{$prefix}icefox_likes` WHERE Key_name = 'unique_like'"));
        if (!empty($indexes)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` DROP INDEX `unique_like`");
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_albums` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `slug` varchar(191) NOT NULL,
            `name` varchar(80) NOT NULL,
            `description` text,
            `cover` varchar(1000) DEFAULT NULL,
            `tags` text,
            `address` varchar(255) DEFAULT NULL,
            `visibility` varchar(20) NOT NULL DEFAULT 'public',
            `photos` longtext,
            `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
            `is_moments` tinyint(1) NOT NULL DEFAULT '0',
            `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
            `created_at` int(10) unsigned NOT NULL,
            `updated_at` int(10) unsigned NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_slug` (`slug`),
            KEY `idx_album_order` (`is_moments`, `is_pinned`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->query($sql);
        self::ensureAlbumTableSchema();
        self::migrateLegacyAlbumTags();
    }

    public static function ensureAlbumTableSchema()
    {
        $db = Db::get();
        self::migrateAlbumTable($db, $db->getPrefix());
        self::ensureAlbumTagTableSchema();
    }

    public static function ensureAlbumTagTableSchema()
    {
        if (self::$albumTagSchemaReady) {
            return;
        }

        $db = Db::get();
        $prefix = $db->getPrefix();
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_album_tags` (
            `album_id` int(10) unsigned NOT NULL,
            `mid` int(10) unsigned NOT NULL,
            `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`album_id`, `mid`),
            KEY `idx_mid` (`mid`),
            FOREIGN KEY (`album_id`) REFERENCES `{$prefix}icefox_albums`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`mid`) REFERENCES `{$prefix}metas`(`mid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->query($sql);
        self::$albumTagSchemaReady = true;
    }

    public static function normalizeAlbumTags($rawTags)
    {
        $parts = is_array($rawTags)
            ? $rawTags
            : preg_split('/[,，]/u', (string) $rawTags);
        $result = [];
        $seen = [];

        foreach (is_array($parts) ? $parts : [] as $tag) {
            $name = trim((string) $tag);
            if ($name === '' || !Validate::xssCheck($name) || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $result[] = $name;
        }

        return $result;
    }

    public static function syncAlbumTags($albumId, $rawTags, $replace = true)
    {
        $albumId = (int) $albumId;
        if ($albumId <= 0) {
            return ['inserted' => 0, 'removed' => 0, 'total' => 0];
        }

        self::ensureAlbumTagTableSchema();
        $db = Db::get();
        $tagNames = self::normalizeAlbumTags($rawTags);
        $tagIds = $tagNames ? Metas::alloc()->scanTags($tagNames) : [];
        $tagIds = array_values(array_unique(array_map('intval', is_array($tagIds) ? $tagIds : [])));
        $existingRows = $db->fetchAll(
            $db->select('mid')->from('table.icefox_album_tags')->where('album_id = ?', $albumId)
        );
        $existingIds = array_map('intval', array_column($existingRows, 'mid'));
        $removed = 0;
        $inserted = 0;

        if ($replace) {
            foreach (array_diff($existingIds, $tagIds) as $mid) {
                $removed += (int) $db->query(
                    $db->delete('table.icefox_album_tags')
                        ->where('album_id = ?', $albumId)
                        ->where('mid = ?', $mid)
                );
            }
        }

        foreach ($tagIds as $position => $mid) {
            if (in_array($mid, $existingIds, true)) {
                $db->query(
                    $db->update('table.icefox_album_tags')
                        ->rows(['sort_order' => $position])
                        ->where('album_id = ?', $albumId)
                        ->where('mid = ?', $mid)
                );
                continue;
            }

            $db->query($db->insert('table.icefox_album_tags')->rows([
                'album_id' => $albumId,
                'mid' => $mid,
                'sort_order' => $position
            ]));
            $inserted++;
        }

        // Typecho's metas.count remains the published-post count; album counts live in the join table.
        return ['inserted' => $inserted, 'removed' => $removed, 'total' => count($tagIds)];
    }

    public static function migrateLegacyAlbumTags()
    {
        self::ensureAlbumTagTableSchema();
        $db = Db::get();
        $albums = $db->fetchAll(
            $db->select('id', 'tags')->from('table.icefox_albums')
                ->where('tags IS NOT NULL')
                ->where('tags <> ?', '')
        );
        $migratedAlbums = 0;
        $insertedRelationships = 0;

        foreach ($albums as $album) {
            $result = self::syncAlbumTags((int) $album['id'], $album['tags'], false);
            if ($result['inserted'] > 0) {
                $migratedAlbums++;
                $insertedRelationships += $result['inserted'];
            }
        }

        return [
            'albums' => $migratedAlbums,
            'relationships' => $insertedRelationships
        ];
    }

    public static function getAlbumTagLinks($albumId, $legacyTags = '')
    {
        self::ensureAlbumTagTableSchema();
        $db = Db::get();
        $albumId = (int) $albumId;
        $rows = self::fetchAlbumTagRows($albumId);
        $legacyNames = self::normalizeAlbumTags($legacyTags);
        $linkedNames = array_values(array_unique(array_map(function ($row) {
            return (string) ($row['name'] ?? '');
        }, $rows)));

        if ($legacyNames && array_diff($legacyNames, $linkedNames)) {
            self::syncAlbumTags((int) $albumId, $legacyNames, false);
            $rows = self::fetchAlbumTagRows($albumId);
        }

        if (!$rows && $legacyNames) {
            $metaRows = $db->fetchAll(
                $db->select()->from('table.metas')
                    ->where('type = ?', 'tag')
                    ->where('name IN ?', $legacyNames)
            );
            $rowsByName = [];
            foreach ($metaRows as $row) {
                $rowsByName[$row['name']] = $row;
            }
            foreach ($legacyNames as $name) {
                $rows[] = $rowsByName[$name] ?? [
                    'mid' => 0,
                    'name' => $name,
                    'slug' => '',
                    'type' => 'tag'
                ];
            }
        }

        $links = [];
        foreach ($rows as $row) {
            $links[] = [
                'name' => (string) $row['name'],
                'slug' => (string) ($row['slug'] ?? ''),
                'url' => !empty($row['mid'])
                    ? Router::url('tag', $row, \Widget\Options::alloc()->index)
                    : ''
            ];
        }

        return $links;
    }

    private static function fetchAlbumTagRows($albumId)
    {
        $db = Db::get();
        return $db->fetchAll(
            $db->select('table.metas.*')
                ->from('table.metas')
                ->join('table.icefox_album_tags', 'table.icefox_album_tags.mid = table.metas.mid')
                ->where('table.icefox_album_tags.album_id = ?', (int) $albumId)
                ->where('table.metas.type = ?', 'tag')
                ->order('table.icefox_album_tags.sort_order', Db::SORT_ASC)
        );
    }

    public static function getVisibleTagAlbumCount($mid, $includePrivate = false)
    {
        $mid = (int) $mid;
        if ($mid <= 0) {
            return 0;
        }

        self::ensureAlbumTagTableSchema();
        $db = Db::get();
        $query = $db->select(['COUNT(table.icefox_album_tags.album_id)' => 'num'])
            ->from('table.icefox_album_tags')
            ->join(
                'table.icefox_albums',
                'table.icefox_albums.id = table.icefox_album_tags.album_id'
            )
            ->where('table.icefox_album_tags.mid = ?', $mid);
        if (!$includePrivate) {
            $query->where('table.icefox_albums.visibility = ?', 'public');
        }

        $result = $db->fetchObject($query);
        return $result ? (int) $result->num : 0;
    }

    public static function getTagArchiveAlbums($slug, $includePrivate = false)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return [];
        }

        self::ensureAlbumTagTableSchema();
        $db = Db::get();
        $tag = $db->fetchRow(
            $db->select('mid')->from('table.metas')
                ->where('type = ?', 'tag')
                ->where('slug = ?', $slug)
                ->limit(1)
        );
        if (!$tag) {
            return [];
        }

        $query = $db->select('table.icefox_albums.*')
            ->from('table.icefox_albums')
            ->join(
                'table.icefox_album_tags',
                'table.icefox_album_tags.album_id = table.icefox_albums.id'
            )
            ->where('table.icefox_album_tags.mid = ?', (int) $tag['mid']);
        if (!$includePrivate) {
            $query->where('table.icefox_albums.visibility = ?', 'public');
        }

        $rows = $db->fetchAll(
            $query->order('table.icefox_albums.is_moments', Db::SORT_DESC)
                ->order('table.icefox_albums.is_pinned', Db::SORT_DESC)
                ->order('table.icefox_albums.sort_order', Db::SORT_ASC)
                ->order('table.icefox_albums.id', Db::SORT_ASC)
        );
        $albums = [];
        foreach ($rows as $row) {
            $photos = json_decode((string) ($row['photos'] ?? '[]'), true);
            $photos = is_array($photos) ? $photos : [];
            $cover = trim((string) ($row['cover'] ?? ''));
            if ($cover === '' && !empty($photos)) {
                $firstPhoto = $photos[0];
                $cover = is_array($firstPhoto)
                    ? trim((string) ($firstPhoto['src'] ?? $firstPhoto['url'] ?? ''))
                    : trim((string) $firstPhoto);
            }

            $albums[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'cover' => $cover,
                'address' => (string) ($row['address'] ?? ''),
                'photoCount' => count($photos)
            ];
        }

        return $albums;
    }

    private static function migrateAlbumTable($db, $prefix)
    {
        $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$prefix}icefox_albums`"));
        $columnNames = array_column($columns, 'Field');
        if (!in_array('is_pinned', $columnNames, true)) {
            $db->query("ALTER TABLE `{$prefix}icefox_albums` ADD COLUMN `is_pinned` tinyint(1) NOT NULL DEFAULT '0'");
        }
        if (!in_array('is_moments', $columnNames, true)) {
            $db->query("ALTER TABLE `{$prefix}icefox_albums` ADD COLUMN `is_moments` tinyint(1) NOT NULL DEFAULT '0'");
        }
        if (!in_array('sort_order', $columnNames, true)) {
            $db->query("ALTER TABLE `{$prefix}icefox_albums` ADD COLUMN `sort_order` int(10) unsigned NOT NULL DEFAULT '0'");
        }
        if (!in_array('description', $columnNames, true)) {
            $db->query("ALTER TABLE `{$prefix}icefox_albums` ADD COLUMN `description` text AFTER `name`");
        }
    }

}
