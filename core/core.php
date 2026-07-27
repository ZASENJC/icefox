<?php

/**
 * 提取图片
 */
function extractImageSrcs($html)
{
    $srcs = [];
    preg_match_all('/<img[^>]+src="([^"]+)"/i', $html, $matches);
    if (isset($matches[1])) {
        $srcs = $matches[1];
    }
    return $srcs;
}

/**
 * 提取视频URL
 */
function extractVideoSrc($html)
{
    // 匹配 <video> 标签中的 src 属性
    if (preg_match('/<video[^>]+src="([^"]+)"/i', $html, $matches)) {
        return $matches[1];
    }
    // 匹配 <video> 标签内的 <source> 标签
    if (preg_match('/<video[^>]*>.*?<source[^>]+src="([^"]+)"/is', $html, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * 根据文章id获取文章字段
 */
function getArticleFieldsByCid($cid, $name)
{
    $db = Typecho_Db::get();
    $select = $db->select('*')
        ->from('table.fields')
        ->where('cid = ?', $cid)
        ->where('name = ?', $name);
    return $db->fetchAll($select);
}

/**
 * 过滤内容
 */
function filterContent($html)
{
    $html = strip_tags($html, '<p><a>');

    return $html;
}
/**
 * 截取HTML内容，保留标签但不计入长度
 */
function truncateHtmlWithTags($html, $length) {
    $text_length = 0;
    $output = '';
    $tag_stack = [];
    $i = 0;
    $html_length = mb_strlen($html, 'UTF-8');

    while ($i < $html_length && $text_length < $length) {
        $char = mb_substr($html, $i, 1, 'UTF-8');

        // 检测到标签开始
        if ($char === '<') {
            $tag_end = mb_strpos($html, '>', $i);
            if ($tag_end !== false) {
                $tag_content = mb_substr($html, $i, $tag_end - $i + 1, 'UTF-8');
                $output .= $tag_content;

                // 检查是否是开始标签（非自闭合、非结束标签）
                if (preg_match('/<(\w+)(?:\s|>)/i', $tag_content, $matches)) {
                    $tag_name = $matches[1];
                    // 不是自闭合标签，也不是结束标签
                    if (!preg_match('/<\w+[^>]*\/>/i', $tag_content) && !preg_match('/<\/\w+>/i', $tag_content)) {
                        array_push($tag_stack, $tag_name);
                    }
                }

                // 检查是否是结束标签
                if (preg_match('/<\/(\w+)>/i', $tag_content, $matches)) {
                    $tag_name = $matches[1];
                    // 从栈中移除对应的开始标签
                    $key = array_search($tag_name, array_reverse($tag_stack, true));
                    if ($key !== false) {
                        array_splice($tag_stack, count($tag_stack) - 1 - $key, 1);
                    }
                }

                $i = $tag_end + 1;
                continue;
            }
        }

        // 普通文本字符
        $output .= $char;
        $text_length++;
        $i++;
    }

    // 关闭所有未闭合的标签
    while (!empty($tag_stack)) {
        $tag = array_pop($tag_stack);
        $output .= '</' . $tag . '>';
    }

    // 移除末尾多余的空换行
    // 移除末尾的 <br>、<br/>、<br />、空的 <p></p> 等
    $output = preg_replace('/(<br\s*\/?>|\s|&nbsp;|<p>\s*<\/p>)+$/i', '', $output);

    return $output;
}

/**
 * 获取截取内容
 */
function generateContentWithSummary($full_content, $summary_length = 100) {
    // 生成纯文本用于判断是否需要截取
    $plain_text = strip_tags($full_content);
    $plain_text = preg_replace('/\s+/', ' ', $plain_text);
    $plain_text = trim($plain_text);

    // 判断是否需要截取
    if (mb_strlen($plain_text, 'UTF-8') > $summary_length) {
        // 截取HTML内容，保留标签但不计入长度
        $summary = truncateHtmlWithTags($full_content, $summary_length) . '...';
        $is_truncated = true;
    } else {
        $summary = $full_content;
        $is_truncated = false;
    }

    return [
        'summary' => $summary,
        'full_content' => $full_content,
        'is_truncated' => $is_truncated
    ];
}