<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 获取文章的最新5条顶级评论及其所有回复
 *
 * @param int $postId 文章ID
 * @param int $limit 顶级评论数量限制
 * @return array 评论数组
 */
function getPostLatestCommentsWithReplies($postId, $limit = 5) {
    $db = Typecho_Db::get();

    // 1. 获取最新的5条顶级评论，并 LEFT JOIN 用户表获取用户组信息
    $topLevelComments = $db->fetchAll($db->select('c.*, u.`group` as userGroup')
        ->from('table.comments AS c')
        ->join('table.users AS u', 'c.authorId = u.uid', Typecho_Db::LEFT_JOIN)
        ->where('c.cid = ?', $postId)
        ->where('c.status = ?', 'approved')
        ->where('c.type = ?', 'comment')
        ->where('c.parent = ?', 0)
        ->order('c.created', Typecho_Db::SORT_DESC)
        ->limit($limit));

    if (empty($topLevelComments)) {
        return array();
    }

    // 2. 获取所有相关的子评论（按创建时间排序），并 LEFT JOIN 用户表
    $allChildComments = $db->fetchAll($db->select('c.*, u.`group` as userGroup')
        ->from('table.comments AS c')
        ->join('table.users AS u', 'c.authorId = u.uid', Typecho_Db::LEFT_JOIN)
        ->where('c.cid = ?', $postId)
        ->where('c.status = ?', 'approved')
        ->where('c.type = ?', 'comment')
        ->where('c.parent > ?', 0)
        ->order('c.created', Typecho_Db::SORT_ASC));

    // 创建评论索引和父子索引，后续组树只需遍历每条评论一次。
    $commentMap = array();
    $childrenByParent = array();
    foreach ($topLevelComments as $comment) {
        $commentMap[$comment['coid']] = $comment;
    }
    foreach ($allChildComments as $comment) {
        $commentMap[$comment['coid']] = $comment;
        $childrenByParent[$comment['parent']][] = $comment;
    }

    // 构建评论树，避免对每个顶级评论重复扫描全部子评论并递归查找父级。
    $commentTree = array();

    foreach ($topLevelComments as $topComment) {
        $topComment['replies'] = array();
        $topComment['level'] = 0;
        $relatedReplies = array();

        $pendingParents = array($topComment['coid']);
        $visited = array($topComment['coid'] => true);
        while (!empty($pendingParents)) {
            $parentId = array_pop($pendingParents);
            foreach ($childrenByParent[$parentId] ?? array() as $childComment) {
                if (isset($visited[$childComment['coid']])) {
                    continue;
                }
                $visited[$childComment['coid']] = true;

                $parentComment = $commentMap[$childComment['parent']] ?? null;
                if (!$parentComment) {
                    continue;
                }
                $childComment['parentAuthor'] = $parentComment['author'];
                $childComment['parentAuthorId'] = $parentComment['authorId'] ?? '';
                $childComment['parentUrl'] = $parentComment['url'] ?? '';
                $childComment['parentUserGroup'] = $parentComment['userGroup'] ?? '';
                $relatedReplies[] = $childComment;
                $pendingParents[] = $childComment['coid'];
            }
        }

        // 按创建时间排序子评论
        usort($relatedReplies, function($a, $b) {
            return $a['created'] - $b['created'];
        });

        $topComment['replies'] = $relatedReplies;
        $commentTree[] = $topComment;
    }

    return $commentTree;
}

/**
 * 获取文章的最新5条顶级评论及其所有回复（简化版）
 *
 * @param int $postId 文章ID
 * @param int $limit 顶级评论数量限制
 * @return array 评论数组
 */
function getPostCommentsTree($postId, $limit = 5) {
    $db = Typecho_Db::get();

    // 获取顶级评论
    $topComments = $db->fetchAll($db->select()
        ->from('table.comments')
        ->where('cid = ?', $postId)
        ->where('status = ?', 'approved')
        ->where('type = ?', 'comment')
        ->where('parent = ?', 0)
        ->order('created', Typecho_Db::SORT_DESC)
        ->limit($limit));

    // 获取所有子评论
    $allChildComments = $db->fetchAll($db->select()
        ->from('table.comments')
        ->where('cid = ?', $postId)
        ->where('status = ?', 'approved')
        ->where('type = ?', 'comment')
        ->where('parent > ?', 0)
        ->order('created', Typecho_Db::SORT_ASC));

    // 构建评论树
    $result = array();

    foreach ($topComments as $topComment) {
        $commentData = array(
            'coid' => $topComment['coid'],
            'cid' => $topComment['cid'],
            'created' => $topComment['created'],
            'author' => $topComment['author'],
            'authorId' => $topComment['authorId'],
            'mail' => $topComment['mail'],
            'url' => $topComment['url'],
            'text' => $topComment['text'],
            'parent' => $topComment['parent'],
            'level' => 0,
            'replies' => array()
        );

        // 查找该顶级评论的所有直接回复
        foreach ($allChildComments as $childComment) {
            if ($childComment['parent'] == $topComment['coid']) {
                $commentData['replies'][] = array(
                    'coid' => $childComment['coid'],
                    'cid' => $childComment['cid'],
                    'created' => $childComment['created'],
                    'author' => $childComment['author'],
                    'authorId' => $childComment['authorId'],
                    'mail' => $childComment['mail'],
                    'url' => $childComment['url'],
                    'text' => $childComment['text'],
                    'parent' => $childComment['parent'],
                    'level' => 1
                );
            }
        }

        $result[] = $commentData;
    }

    return $result;
}

/**
 * 格式化评论时间
 *
 * @param int $timestamp 时间戳
 * @return string 格式化后的时间
 */
function formatCommentTime($timestamp) {
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y年m月d日', $timestamp);
    }
}

?>
