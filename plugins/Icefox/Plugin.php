<?php

namespace TypechoPlugin\Icefox;

use Typecho\Common;
use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Db;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox插件是icefox主题的适配插件，需搭配icefox主题使用
 * @package Icefox
 * @author 小胖脸
 * @version 1.2.1
 * @link https://xiaopanglian.com
 */

class Plugin implements PluginInterface
{
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
    }

    public static function ensureAlbumTableSchema()
    {
        $db = Db::get();
        self::migrateAlbumTable($db, $db->getPrefix());
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
