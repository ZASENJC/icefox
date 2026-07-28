<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Read the pinned-post state from the companion plugin's legacy table.
 *
 * This compatibility bridge is the only theme module allowed to query an
 * Icefox-owned table directly. A future plugin API or Typecho field should
 * replace this database coupling.
 */
function getPostIsTop($cid)
{
    static $topCache = [];
    static $archiveAvailable = true;

    $cid = (int) $cid;
    if (!$archiveAvailable) {
        return false;
    }
    if (isset($topCache[$cid])) {
        return $topCache[$cid];
    }

    $db = Typecho_Db::get();
    $prefix = $db->getPrefix();

    try {
        $result = $db->fetchRow(
            $db->select('is_top')
                ->from($prefix . 'icefox_archive')
                ->where('cid = ?', $cid)
        );

        $topCache[$cid] = !empty($result) && $result['is_top'] == 1;
        return $topCache[$cid];
    } catch (Exception $e) {
        $archiveAvailable = false;
        $topCache[$cid] = false;
        return false;
    }
}
