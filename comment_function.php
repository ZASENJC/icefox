<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 检查评论是否与顶级评论相关（递归查找）
 *
 * @param int $commentId 要检查的评论ID
 * @param int $topCommentId 顶级评论ID
 * @param array $commentMap 评论映射表
 * @return bool 是否相关
 */
function isCommentRelatedToTopComment($commentId, $topCommentId, $commentMap) {
    // 如果评论不存在，返回false
    if (!isset($commentMap[$commentId])) {
        return false;
    }

    $comment = $commentMap[$commentId];

    // 如果直接是顶级评论的子评论，返回true
    if ($comment['parent'] == $topCommentId) {
        return true;
    }

    // 如果没有父评论，返回false
    if ($comment['parent'] == 0) {
        return false;
    }

    // 递归检查父评论是否与顶级评论相关
    return isCommentRelatedToTopComment($comment['parent'], $topCommentId, $commentMap);
}

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

    // 3. 创建评论映射表（用于快速查找父评论信息）
    $commentMap = array();
    foreach ($topLevelComments as $comment) {
        $commentMap[$comment['coid']] = $comment;
    }
    foreach ($allChildComments as $comment) {
        $commentMap[$comment['coid']] = $comment;
    }

    // 4. 构建评论树
    $commentTree = array();

    foreach ($topLevelComments as $topComment) {
        // 初始化顶级评论
        $topComment['replies'] = array();
        $topComment['level'] = 0;

        // 查找所有与这个顶级评论相关的子评论（包括任意层级）
        $relatedReplies = array();

        foreach ($allChildComments as $childComment) {
            // 使用递归函数检查是否与顶级评论相关
            if (isCommentRelatedToTopComment($childComment['coid'], $topComment['coid'], $commentMap)) {
                // 添加父评论信息
                $parentComment = $commentMap[$childComment['parent']];
                $childComment['parentAuthor'] = $parentComment['author'];
                $childComment['parentAuthorId'] = $parentComment['authorId'] ?? '';
                $childComment['parentUrl'] = $parentComment['url'] ?? '';
                $childComment['parentUserGroup'] = $parentComment['userGroup'] ?? '';

                $relatedReplies[] = $childComment;
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
