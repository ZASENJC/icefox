<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Return whether the current Typecho archive is an article list.
 */
function icefoxShouldOrderPinnedPosts($archive)
{
    if (!is_object($archive) || !isset($archive->parameter->type)) {
        return false;
    }

    return in_array((string) $archive->parameter->type, array(
        'index',
        'index_page',
        'archive',
        'archive_page',
        'category',
        'category_page',
        'tag',
        'tag_page',
        'author',
        'author_page',
        'archive_year',
        'archive_year_page',
        'archive_month',
        'archive_month_page',
        'archive_day',
        'archive_day_page',
        'search',
        'search_page'
    ), true);
}

/**
 * Register the final archive-query handler after Typecho has built its filters.
 */
function icefoxRegisterPinnedPostOrdering()
{
    static $registered = false;

    if ($registered) {
        return true;
    }

    if (class_exists('Typecho\\Plugin')) {
        $pluginClass = 'Typecho\\Plugin';
        $archiveHandle = 'Widget\\Archive';
    } elseif (class_exists('Typecho_Plugin')) {
        $pluginClass = 'Typecho_Plugin';
        $archiveHandle = 'Widget_Archive';
    } else {
        return false;
    }

    $pluginState = call_user_func(array($pluginClass, 'export'));
    $queryHandle = $archiveHandle . ':query';
    $factory = call_user_func(array($pluginClass, 'factory'), $archiveHandle);

    if (!empty($pluginState['handles'][$queryHandle])) {
        $component = 'query_1';
        $factory->{$component} = 'icefoxPreparePinnedPostQuery';
    } else {
        $factory->query = 'icefoxQueryPinnedPosts';
    }

    $registered = true;
    return true;
}

/**
 * Replace Typecho's date-only ordering while preserving all filters and paging.
 */
function icefoxApplyPinnedPostOrdering($select, $db)
{
    $adapter = $db->getAdapter();
    $fieldName = $adapter->quoteValue('isTop');
    $one = $adapter->quoteValue('1');
    $true = $adapter->quoteValue('true');
    $yes = $adapter->quoteValue('yes');

    $select->join('table.fields AS icefox_pin',
        'table.contents.cid = icefox_pin.cid AND icefox_pin.name = ' . $fieldName
        . ' AND (icefox_pin.int_value = 1 OR icefox_pin.str_value IN ('
        . $one . ', ' . $true . ', ' . $yes . '))',
        Typecho_Db::LEFT_JOIN
    );

    $select->cleanAttribute('order')
        ->order('icefox_pin.cid IS NULL', Typecho_Db::SORT_ASC)
        ->order('table.contents.created', Typecho_Db::SORT_DESC)
        ->order('table.contents.cid', Typecho_Db::SORT_DESC);

    return $select;
}

/**
 * Mutate a query that another Typecho query handler is responsible for running.
 */
function icefoxPreparePinnedPostQuery($archive, $select)
{
    if (icefoxShouldOrderPinnedPosts($archive)) {
        icefoxApplyPinnedPostOrdering($select, Typecho_Db::get());
    }
}

/**
 * Apply pinned ordering and run the query when no other handler owns it.
 */
function icefoxQueryPinnedPosts($archive, $select)
{
    $db = Typecho_Db::get();

    if (icefoxShouldOrderPinnedPosts($archive)) {
        icefoxApplyPinnedPostOrdering($select, $db);
    }

    $db->fetchAll($select, array($archive, 'push'));
}
