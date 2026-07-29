<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

const ICEFOX_FRIEND_LINKS_FIELD = 'friendLinks';
const ICEFOX_FRIEND_LINKS_LIMIT = 100;
const ICEFOX_FRIEND_LINKS_MAX_BYTES = 60000;

function icefoxFriendLinksPageUrl($options)
{
    $configuredUrl = trim((string) $options->friendLinksPageUrl);
    return $configuredUrl !== ''
        ? $configuredUrl
        : Typecho_Common::url('links', $options->index);
}

function icefoxFriendLinksNormalizeText($value, $length)
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', trim((string) $value));
    return mb_substr($value, 0, (int) $length, 'UTF-8');
}

function icefoxFriendLinksNormalizeUrl($value, $allowEmpty = false)
{
    $value = trim((string) $value);
    if ($value === '' && $allowEmpty) {
        return '';
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('链接地址必须是有效的 HTTP 或 HTTPS URL');
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('链接地址必须使用 HTTP 或 HTTPS');
    }

    return $value;
}

function icefoxFriendLinksNormalizeId($value)
{
    $value = trim((string) $value);
    return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) ? $value : '';
}

function icefoxFriendLinksCreateId()
{
    try {
        return 'link-' . bin2hex(random_bytes(8));
    } catch (Exception $error) {
        return 'link-' . str_replace('.', '', uniqid('', true));
    }
}

function icefoxFriendLinksNormalizeRecord(array $link, $requireId = false)
{
    $id = icefoxFriendLinksNormalizeId($link['id'] ?? '');
    if ($requireId && $id === '') {
        throw new InvalidArgumentException('友情链接标识无效');
    }

    $name = icefoxFriendLinksNormalizeText($link['name'] ?? '', 100);
    if ($name === '') {
        throw new InvalidArgumentException('请填写友情链接名称');
    }

    return [
        'id' => $id,
        'name' => $name,
        'url' => icefoxFriendLinksNormalizeUrl($link['url'] ?? ''),
        'avatar' => icefoxFriendLinksNormalizeUrl($link['avatar'] ?? '', true),
        'description' => icefoxFriendLinksNormalizeText($link['description'] ?? '', 200),
        'sort' => max(0, (int) ($link['sort'] ?? 0))
    ];
}

function icefoxFriendLinksSort(array $links)
{
    usort($links, function ($left, $right) {
        $sortComparison = ((int) $left['sort']) <=> ((int) $right['sort']);
        if ($sortComparison !== 0) {
            return $sortComparison;
        }

        return strcmp((string) $left['id'], (string) $right['id']);
    });

    return array_values($links);
}

function icefoxFriendLinksRead($cid)
{
    $cid = (int) $cid;
    if ($cid <= 0) {
        return [];
    }

    $db = Typecho_Db::get();
    $field = $db->fetchRow(
        $db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', ICEFOX_FRIEND_LINKS_FIELD)
            ->limit(1)
    );

    if (!$field || trim((string) ($field['str_value'] ?? '')) === '') {
        return [];
    }

    $decoded = json_decode((string) $field['str_value'], true);
    $records = isset($decoded['links']) && is_array($decoded['links'])
        ? $decoded['links']
        : (is_array($decoded) ? $decoded : []);
    $links = [];

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        try {
            $links[] = icefoxFriendLinksNormalizeRecord($record, true);
        } catch (InvalidArgumentException $error) {
            continue;
        }
    }

    return icefoxFriendLinksSort($links);
}

function icefoxFriendLinksWrite($cid, array $links)
{
    $cid = (int) $cid;
    if ($cid <= 0) {
        throw new RuntimeException('友情链接页面不存在');
    }

    $db = Typecho_Db::get();
    $page = $db->fetchRow(
        $db->select('cid')
            ->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'page')
            ->limit(1)
    );
    if (!$page) {
        throw new RuntimeException('友情链接页面不存在');
    }

    $normalizedLinks = [];
    foreach ($links as $link) {
        $normalizedLinks[] = icefoxFriendLinksNormalizeRecord($link, true);
    }

    $json = json_encode([
        'version' => 1,
        'links' => icefoxFriendLinksSort($normalizedLinks)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('友情链接数据编码失败');
    }
    if (strlen($json) > ICEFOX_FRIEND_LINKS_MAX_BYTES) {
        throw new InvalidArgumentException('友情链接数据过大，请缩短名称、描述或图片地址');
    }

    $existing = $db->fetchRow(
        $db->select('cid')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', ICEFOX_FRIEND_LINKS_FIELD)
            ->limit(1)
    );
    $fieldData = [
        'type' => 'str',
        'str_value' => $json,
        'int_value' => 0,
        'float_value' => 0
    ];

    if ($existing) {
        $db->query(
            $db->update('table.fields')
                ->rows($fieldData)
                ->where('cid = ?', $cid)
                ->where('name = ?', ICEFOX_FRIEND_LINKS_FIELD)
        );
    } else {
        $fieldData['cid'] = $cid;
        $fieldData['name'] = ICEFOX_FRIEND_LINKS_FIELD;
        $db->query($db->insert('table.fields')->rows($fieldData));
    }

    $db->query(
        $db->update('table.contents')
            ->rows(['modified' => time()])
            ->where('cid = ?', $cid)
    );
}

function icefoxFriendLinksResponse($archive, array $payload, $status = 200)
{
    $archive->response->setStatus((int) $status);
    $archive->response->setContentType('application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function icefoxFriendLinksSessionPayload($archive, array $links, $message = null)
{
    $user = \Widget\User::alloc();
    $security = \Widget\Security::alloc();
    $canEdit = $user->hasLogin();
    $payload = [
        'success' => true,
        'data' => icefoxFriendLinksSort($links),
        'canEdit' => $canEdit,
        'token' => $canEdit ? $security->getToken($archive->request->getReferer()) : null
    ];

    if ($message !== null) {
        $payload['message'] = $message;
    }

    return $payload;
}

function icefoxHandleFriendLinksRequest($archive)
{
    $request = $archive->request;
    $user = \Widget\User::alloc();
    $security = \Widget\Security::alloc();
    $links = icefoxFriendLinksRead($archive->cid);

    if (!$request->isPost()) {
        icefoxFriendLinksResponse($archive, icefoxFriendLinksSessionPayload($archive, $links));
    }

    if (!$user->hasLogin()) {
        icefoxFriendLinksResponse($archive, [
            'success' => false,
            'message' => '请先登录后再管理友情链接'
        ], 403);
    }

    $providedToken = (string) $request->get('_', '');
    $expectedToken = $security->getToken($request->getReferer());
    if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        icefoxFriendLinksResponse($archive, [
            'success' => false,
            'code' => 'security_token_expired',
            'message' => '登录状态已变化，请重新打开友情链接窗口'
        ], 403);
    }
    $security->protect();

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        icefoxFriendLinksResponse($archive, [
            'success' => false,
            'message' => '请求数据格式无效'
        ], 400);
    }

    try {
        $action = (string) ($input['action'] ?? '');
        if ($action === 'save') {
            $incoming = isset($input['link']) && is_array($input['link']) ? $input['link'] : [];
            $record = icefoxFriendLinksNormalizeRecord($incoming);
            $isNew = $record['id'] === '';
            $updated = false;

            if ($isNew) {
                if (count($links) >= ICEFOX_FRIEND_LINKS_LIMIT) {
                    throw new InvalidArgumentException('友情链接最多保存 100 条');
                }
                $record['id'] = icefoxFriendLinksCreateId();
                $links[] = $record;
                $updated = true;
            } else {
                foreach ($links as $index => $link) {
                    if ($link['id'] === $record['id']) {
                        $links[$index] = $record;
                        $updated = true;
                        break;
                    }
                }
            }

            if (!$updated) {
                throw new InvalidArgumentException('要编辑的友情链接不存在');
            }

            icefoxFriendLinksWrite($archive->cid, $links);
            icefoxFriendLinksResponse(
                $archive,
                icefoxFriendLinksSessionPayload($archive, icefoxFriendLinksRead($archive->cid), '友情链接已保存')
            );
        }

        if ($action === 'delete') {
            $id = icefoxFriendLinksNormalizeId($input['id'] ?? '');
            if ($id === '') {
                throw new InvalidArgumentException('友情链接标识无效');
            }

            $remaining = array_values(array_filter($links, function ($link) use ($id) {
                return $link['id'] !== $id;
            }));
            if (count($remaining) === count($links)) {
                throw new InvalidArgumentException('要删除的友情链接不存在');
            }

            icefoxFriendLinksWrite($archive->cid, $remaining);
            icefoxFriendLinksResponse(
                $archive,
                icefoxFriendLinksSessionPayload($archive, icefoxFriendLinksRead($archive->cid), '友情链接已删除')
            );
        }

        throw new InvalidArgumentException('不支持的友情链接操作');
    } catch (InvalidArgumentException $error) {
        icefoxFriendLinksResponse($archive, [
            'success' => false,
            'message' => $error->getMessage()
        ], 400);
    } catch (Exception $error) {
        icefoxFriendLinksResponse($archive, [
            'success' => false,
            'message' => '友情链接保存失败，请稍后重试'
        ], 500);
    }
}
