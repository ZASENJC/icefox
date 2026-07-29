<?php

namespace TypechoPlugin\Icefox;

use Typecho\Config;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Maps album root and detail URLs to the Typecho "albums" page.
 */
class AlbumArchive extends \Widget\Archive
{
    protected function initParameter(Config $parameter)
    {
        $this->request->setParam('slug', 'albums');
        parent::initParameter($parameter);
        $parameter->type = 'page';
        $parameter->checkPermalink = false;
    }
}
