<?php

namespace TypechoPlugin\IcefoxPlugin;

use Typecho\Widget;
use Typecho\Db;
use Typecho\Request;
use Widget\ActionInterface;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget implements ActionInterface {
    const ALBUM_PHOTO_LIMIT = 100;
    const STAGED_UPLOAD_CHUNK_SIZE = 1048576;
    const STAGED_UPLOAD_TTL = 3600;

    public function action(){
        $request = Request::getInstance();
        $user = Widget::widget('Widget_User');

        // 操作类型
        $do = $request->get('do');
        if (empty($do)) {
            $this->returnJson(['success' => false, 'message' => '操作类型缺失']);
            return;
        }

        if ($do === 'getSecurityToken') {
            if (!$user->hasLogin()) {
                $this->returnJson(['success' => false, 'message' => '请先登录']);
                return;
            }
            $security = Widget::widget('Widget_Security');
            $this->returnJson([
                'success' => true,
                'token' => $security->getToken($this->request->getReferer())
            ]);
            return;
        }

        $postActions = ['like', 'addComment', 'deleteLikeRecord', 'createPost', 'saveAlbum', 'stageAlbumUpload'];
        if (in_array($do, $postActions, true) && !$this->request->isPost()) {
            $this->returnJson(['success' => false, 'message' => '该操作仅支持 POST 请求']);
            return;
        }
        if ($this->request->isPost()) {
            $security = Widget::widget('Widget_Security');
            $providedToken = (string) $request->get('_', '');
            $expectedToken = $security->getToken($this->request->getReferer());
            if (!hash_equals($expectedToken, $providedToken)) {
                $this->returnJson([
                    'success' => false,
                    'code' => 'security_token_expired',
                    'message' => '登录状态已变化，请重新提交'
                ]);
                return;
            }
            $security->protect();
        }

        // 公开读取、点赞和评论操作不需要管理员权限
        if ($do === 'like' || $do === 'getLikes' || $do === 'addComment' || $do === 'getAlbums' || $do === 'getAlbum') {
            if ($do === 'like') {
                $this->toggleLike();
            } else if ($do === 'getLikes') {
                $this->getLikes();
            } else if ($do === 'addComment') {
                $this->addComment();
            } else if ($do === 'getAlbums') {
                $this->getAlbums();
            } else if ($do === 'getAlbum') {
                $this->getAlbum();
            }
            return;
        }

        // 获取点赞记录列表（需要管理员权限）
        if ($do === 'getLikeRecords') {
            if (!$user->pass('administrator')) {
                $this->returnJson(['success' => false, 'message' => '无权操作']);
                return;
            }
            $this->getLikeRecords();
            return;
        }

        // 删除单条点赞记录（需要管理员权限）
        if ($do === 'deleteLikeRecord') {
            if (!$user->pass('administrator')) {
                $this->returnJson(['success' => false, 'message' => '无权操作']);
                return;
            }
            $this->deleteLikeRecord();
            return;
        }

        // 发布文章和保存相册需要登录
        if ($do === 'createPost' || $do === 'saveAlbum' || $do === 'stageAlbumUpload') {
            if (!$user->hasLogin()) {
                $this->returnJson(['success' => false, 'message' => '请先登录']);
                return;
            }
            if ($do === 'createPost') {
                $this->createPost();
            } else if ($do === 'stageAlbumUpload') {
                $this->stageAlbumUpload();
            } else {
                $this->saveAlbum();
            }
            return;
        }

        $this->returnJson(['success' => false, 'message' => '不支持的操作']);
    }

    /**
     * 切换点赞状态（点赞/取消点赞）
     */
    private function toggleLike() {
        $request = Request::getInstance();
        $cid = (int) $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Db::get();
        $prefix = $db->getPrefix();
        $user = Widget::widget('Widget_User');

        // 获取用户信息
        $uid = $user->hasLogin() ? $user->uid : null;
        $ip = $this->request->getIp();
        $anonymousId = $request->get('anonymous_id');
        $commentAuthor = $request->get('comment_author'); // 评论用户昵称
        $commentEmail = $request->get('comment_email'); // 评论用户邮箱
        $currentTime = time();
        $author = null;
        $mail = null;

        // 判断用户身份：登录用户 > 评论用户 > 匿名用户
        if ($uid) {
            // 已登录用户
            $author = $user->screenName;
            $mail = $user->mail;
        } elseif ($commentAuthor && $commentEmail) {
            // 已评论用户(有昵称和邮箱)
            $author = $commentAuthor;
            $mail = $commentEmail;
        }
        // 纯匿名用户不保存 author 和 mail，只保存 anonymous_id

        try {
            // 特殊处理：如果是评论用户,检查是否需要升级匿名点赞记录
            if ($commentEmail && $anonymousId) {
                // 检查是否存在该匿名ID的点赞记录(且没有邮箱信息)
                $anonymousLike = $db->fetchRow(
                    $db->select()->from('table.icefox_likes')
                        ->where('cid = ?', $cid)
                        ->where('anonymous_id = ?', $anonymousId)
                        ->where('mail IS NULL OR mail = ?', '')
                );

                if ($anonymousLike) {
                    // 升级匿名点赞记录：添加用户信息,清除anonymous_id
                    $db->query(
                        $db->update('table.icefox_likes')
                            ->rows([
                                'author' => $author,
                                'mail' => $mail,
                                'anonymous_id' => null  // 清除匿名ID,避免身份混乱
                            ])
                            ->where('cid = ?', $cid)
                            ->where('anonymous_id = ?', $anonymousId)
                    );
                }
            }

            // 检查用户是否已经点赞
            $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

            if ($uid) {
                // 已登录用户：通过 uid 识别
                $query->where('uid = ?', $uid);
            } elseif ($commentEmail) {
                // 已评论用户：通过邮箱识别
                $query->where('mail = ?', $commentEmail);
            } elseif ($anonymousId) {
                // 匿名用户：通过 anonymous_id 识别
                $query->where('anonymous_id = ?', $anonymousId);
            } else {
                // 无任何识别信息：不允许操作
                $this->returnJson(['success' => false, 'message' => '请刷新页面后重试']);
                return;
            }

            $liked = $db->fetchRow($query);

            if ($liked) {
                // 已点赞，执行取消点赞
                $deleteQuery = $db->delete('table.icefox_likes')->where('cid = ?', $cid);

                if ($uid) {
                    $deleteQuery->where('uid = ?', $uid);
                } elseif ($commentEmail) {
                    $deleteQuery->where('mail = ?', $commentEmail);
                } elseif ($anonymousId) {
                    $deleteQuery->where('anonymous_id = ?', $anonymousId);
                }

                $db->query($deleteQuery);

                // 减少点赞数
                $db->query("UPDATE `{$prefix}icefox_archive` SET likes = GREATEST(likes - 1, 0) WHERE cid = " . (int) $cid);

                $isLiked = false;
                $message = '取消点赞成功';
            } else {
                // 未点赞，执行点赞
                $data = [
                    'cid' => $cid,
                    'uid' => $uid,
                    'author' => $author,
                    'mail' => $mail,
                    'ip' => $ip,
                    'anonymous_id' => ($commentEmail ? null : $anonymousId), // 评论用户不保存anonymous_id
                    'created_at' => $currentTime
                ];
                $db->query($db->insert('table.icefox_likes')->rows($data));

                // 增加点赞数，如果记录不存在则创建
                $sql = "INSERT INTO `{$prefix}icefox_archive` (cid, likes)
                        VALUES (" . (int) $cid . ", 1)
                        ON DUPLICATE KEY UPDATE likes = likes + 1";
                $db->query($sql);

                $isLiked = true;
                $message = '点赞成功';
            }

            // 获取最新点赞数和点赞用户列表
            $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
            $likes = $archive ? $archive['likes'] : 0;

            // 获取点赞用户列表
            $likeUsers = $this->getLikeUsers($cid);

            $this->returnJson([
                'success' => true,
                'message' => $message,
                'isLiked' => $isLiked,
                'likes' => $likes,
                'likeUsers' => $likeUsers
            ]);
        } catch (\Exception $e) {
            $this->returnJson(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    /**
     * 从评论记录中查找用户信息
     */
    private function getUserInfoFromComments($ip) {
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 验证并转义IP地址防止SQL注入
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        if (empty($ip)) {
            return null;
        }

        $comment = $db->fetchRow(
            $db->select('author', 'mail')
                ->from('table.comments')
                ->where('ip = ?', $ip)
                ->where('mail IS NOT NULL')
                ->where('mail != ?', '')
                ->order('created', Db::SORT_DESC)
                ->limit(1)
        );

        return $comment;
    }

    /**
     * 获取点赞用户列表
     */
    private function getLikeUsers($cid) {
        $db = Db::get();

        $likes = $db->fetchAll(
            $db->select('author', 'mail', 'created_at')
                ->from('table.icefox_likes')
                ->where('cid = ?', $cid)
                ->order('created_at', Db::SORT_DESC)
        );

        $users = [];
        foreach ($likes as $like) {
            if (!empty($like['author'])) {
                $users[] = [
                    'author' => $like['author'],
                    'mail' => $like['mail']
                ];
            }
        }

        return $users;
    }

    /**
     * 获取点赞信息（点赞数和当前用户是否已点赞）
     */
    private function getLikes() {
        $request = Request::getInstance();
        $cid = (int) $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Db::get();
        $user = Widget::widget('Widget_User');

        // 获取点赞数
        $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
        $likes = $archive ? $archive['likes'] : 0;

        // 检查当前用户是否已点赞
        $uid = $user->hasLogin() ? $user->uid : null;
        $commentEmail = $request->get('comment_email'); // 评论用户邮箱
        $anonymousId = $request->get('anonymous_id');

        $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

        if ($uid) {
            // 已登录用户：通过 uid 识别
            $query->where('uid = ?', $uid);
        } elseif ($commentEmail) {
            // 已评论用户：通过邮箱识别
            $query->where('mail = ?', $commentEmail);
        } elseif ($anonymousId) {
            // 匿名用户：通过 anonymous_id 识别
            $query->where('anonymous_id = ?', $anonymousId);
        } else {
            // 无任何识别信息：视为新用户，不匹配任何点赞记录
            $liked = null;
            $likeUsers = $this->getLikeUsers($cid);
            $this->returnJson([
                'success' => true,
                'likes' => $likes,
                'isLiked' => false,
                'likeUsers' => $likeUsers
            ]);
            return;
        }

        $liked = $db->fetchRow($query);

        // 获取点赞用户列表
        $likeUsers = $this->getLikeUsers($cid);

        $this->returnJson([
            'success' => true,
            'likes' => $likes,
            'isLiked' => !empty($liked),
            'likeUsers' => $likeUsers
        ]);
    }

    /**
     * 添加评论
     */
    private function addComment() {
        $request = Request::getInstance();

        // 获取POST数据
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            $this->returnJson(['success' => false, 'message' => '无效的请求数据']);
            return;
        }

        // 验证必要字段
        $author = isset($data['author']) ? trim($data['author']) : '';
        $mail = isset($data['mail']) ? trim($data['mail']) : '';
        $text = isset($data['text']) ? trim($data['text']) : '';
        $cid = isset($data['cid']) ? intval($data['cid']) : 0;
        $coid = isset($data['coid']) ? intval($data['coid']) : 0;
        $url = isset($data['url']) ? trim($data['url']) : '';

        if (empty($author) || empty($mail) || empty($text) || empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '请填写必要信息']);
            return;
        }

        // 验证邮箱格式
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $this->returnJson(['success' => false, 'message' => '邮箱格式不正确']);
            return;
        }

        $db = Db::get();
        $ip = $this->request->getIp();
        $agent = $this->request->getAgent();
        $currentTime = time();

        // 获取系统设置
        $options = Widget::widget('Widget_Options');
        $user = Widget::widget('Widget_User');

        try {
            // 确定评论状态：根据系统设置和用户身份
            $status = 'approved'; // 默认通过

            // 检查是否需要审核
            if ($options->commentsRequireModeration) {
                // 如果用户已登录且有管理权限，直接通过
                if ($user->hasLogin() && $user->pass('administrator', true)) {
                    $status = 'approved';
                } else {
                    $status = 'waiting';
                }
            }

            // 插入评论数据
            $comment = [
                'cid' => $cid,
                'created' => $currentTime,
                'author' => $author,
                'authorId' => $user->hasLogin() ? $user->uid : 0,
                'ownerId' => 0,
                'mail' => $mail,
                'url' => $url,
                'ip' => $ip,
                'agent' => $agent,
                'text' => $text,
                'type' => 'comment',
                'status' => $status,
                'parent' => $coid
            ];

            $insertId = $db->query($db->insert('table.comments')->rows($comment));

            // 根据审核状态返回不同消息
            $message = ($status === 'waiting')
                ? '评论已提交，等待审核'
                : '评论发表成功';

            // 返回评论信息用于前端展示（XSS防护：对用户输入进行HTML转义）
            $this->returnJson([
                'success' => true,
                'message' => $message,
                'status' => $status,
                'comment' => [
                    'coid' => $insertId,
                    'author' => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
                    'mail' => htmlspecialchars($mail, ENT_QUOTES, 'UTF-8'),
                    'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    'text' => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
                    'created' => $currentTime,
                    'parent' => $coid,
                    'status' => $status,
                    'userGroup' => $user->hasLogin() ? $user->group : null
                ]
            ]);
        } catch (\Exception $e) {
            $this->returnJson(['success' => false, 'message' => '评论发表失败：' . $e->getMessage()]);
        }
    }

    /**
     * 创建文章
     */
    private function createPost() {
        $request = Request::getInstance();
        $user = Widget::widget('Widget_User');
        $db = Db::get();

        // 获取表单数据
        $content = $request->get('content', '');
        $position = $request->get('position', '');
        $positionUrl = $request->get('positionUrl', '');
        $visibility = $request->get('visibility', 'public');
        $storageTarget = $this->resolveStorageTarget($request->get('storage', 'local'));
        $syncToAlbum = $request->get('syncToAlbum', '0') === '1';

        // 验证内容
        $hasMedia = false;
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'media_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                $hasMedia = true;
                break;
            }
        }

        if (empty(trim($content)) && !$hasMedia) {
            $this->returnJson(['success' => false, 'message' => '请输入内容或选择图片/视频']);
            return;
        }

        $uploadedFiles = [];
        $insertId = 0;
        try {
            // 处理文件上传
            $uploadedFiles = $this->handleMediaUpload($storageTarget, 9, true);

            // 构建文章内容（将媒体文件以HTML形式插入）
            $postContent = $this->buildPostContent($content, $uploadedFiles);

            // 生成slug
            $slug = $this->generateSlug($content);

            // 确定文章状态
            $status = ($visibility === 'private') ? 'private' : 'publish';

            // 插入文章到contents表
            $postData = [
                'title' => $this->generateTitle($content),
                'slug' => $slug,
                'created' => time(),
                'modified' => time(),
                'text' => '<!--markdown-->' . $postContent,
                'order' => 0,
                'authorId' => $user->uid,
                'type' => 'post',
                'status' => $status,
                'password' => null,
                'commentsNum' => 0,
                'allowComment' => '1',
                'allowPing' => '1',
                'allowFeed' => '1',
                'parent' => 0
            ];

            $insertId = $db->query($db->insert('table.contents')->rows($postData));

            if (!$insertId) {
                throw new \Exception('文章创建失败');
            }

            // 保存扩展信息到icefox_archive表
            $archiveData = [
                'cid' => $insertId,
                'likes' => 0
            ];
            $db->query($db->insert('table.icefox_archive')->rows($archiveData));

            // 保存文章元数据
            if (!empty($position)) {
                $this->savePostField($insertId, 'position', 'str', $position);
            }
            if (!empty($positionUrl)) {
                $this->savePostField($insertId, 'positionUrl', 'str', $positionUrl);
            }

            // 保存上传的文件记录
            if (!empty($uploadedFiles)) {
                $this->savePostAttachments($insertId, $uploadedFiles);
            }

            $syncedCount = $syncToAlbum
                ? $this->appendImagesToMomentsAlbum($postContent, $uploadedFiles)
                : 0;

            // 跳转到首页
            $options = Widget::widget('Widget_Options');
            $homeUrl = $options->siteUrl;

            $this->returnJson([
                'success' => true,
                'message' => $syncedCount > 0
                    ? '发布成功，已同步 ' . $syncedCount . ' 张图片到朋友圈相册'
                    : '发布成功',
                'cid' => $insertId,
                'redirect' => $homeUrl
            ]);

        } catch (\Exception $e) {
            if ($insertId) {
                $this->cleanupFailedPost($insertId);
            }
            $this->cleanupUploadedObjects($uploadedFiles);
            $this->returnJson(['success' => false, 'message' => '发布失败：' . $e->getMessage()]);
        }
    }

    /**
     * 处理媒体文件上传
     */
    private function handleMediaUpload($storageTarget = 'local', $maxImages = 9, $allowVideo = true) {
        $uploadedFiles = [];
        $imageCount = 0;
        $videoCount = 0;

        try {
            foreach ($_FILES as $key => $file) {
                if (strpos($key, 'media_') !== 0) {
                    continue;
                }
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('文件上传失败，错误码：' . (int) $file['error']);
                }

                $mimeType = $this->detectUploadMime($file['tmp_name']);
                $isVideo = strpos($mimeType, 'video/') === 0;
                if ($isVideo) {
                    $videoCount++;
                    if (!$allowVideo || $videoCount > 1 || $imageCount > 0) {
                        throw new \InvalidArgumentException('视频不能与图片混传，且每次最多上传一个视频');
                    }
                } else {
                    $imageCount++;
                    if ($imageCount > (int) $maxImages || $videoCount > 0) {
                        throw new \InvalidArgumentException('上传图片数量超过限制，或图片与视频发生混传');
                    }
                }
                if ($storageTarget === 'object' && strpos($mimeType, 'image/') === 0) {
                    $uploadedFiles[] = $this->uploadObjectFile($file);
                    continue;
                }

                $uploadedFiles[] = $this->saveLocalUpload($file, $mimeType);
            }
        } catch (\Exception $error) {
            $this->cleanupUploadedObjects($uploadedFiles);
            throw $error;
        }

        return $uploadedFiles;
    }

    private function resolveStorageTarget($value) {
        return $value === 'object' ? 'object' : 'local';
    }

    private function uploadObjectFile(array $file) {
        $pluginClass = '\\TypechoPlugin\\IcefoxStorage\\Plugin';
        if (!class_exists($pluginClass) || !$pluginClass::isConfigured()) {
            throw new \RuntimeException('IcefoxStorage 插件未启用或对象存储配置不完整');
        }

        return $pluginClass::upload($file);
    }

    private function detectUploadMime($filePath) {
        if (!function_exists('finfo_open')) {
            throw new \RuntimeException('服务器未安装 PHP Fileinfo 扩展');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $filePath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/quicktime'
        ];
        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new \InvalidArgumentException('不支持的图片或视频类型');
        }

        return $mimeType;
    }

    private function saveLocalUpload(array $file, $mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov'
        ];
        $relativeDirectory = '/usr/uploads/' . date('Y/m/');
        $uploadDirectory = __TYPECHO_ROOT_DIR__ . $relativeDirectory;
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('无法创建本地上传目录');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $targetPath = $uploadDirectory . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('无法保存本地上传文件');
        }

        return [
            'name' => basename(str_replace('\\', '/', (string) $file['name'])),
            'path' => $relativeDirectory . $fileName,
            'storage' => 'local',
            'type' => strpos($mimeType, 'video/') === 0 ? 'video' : 'image',
            'mime' => $mimeType,
            'size' => filesize($targetPath)
        ];
    }

    private function cleanupUploadedObjects(array $uploadedFiles) {
        $pluginClass = '\\TypechoPlugin\\IcefoxStorage\\Plugin';
        $objectFiles = array_values(array_filter($uploadedFiles, function ($file) {
            return ($file['storage'] ?? '') === 'object';
        }));
        if ($objectFiles && class_exists($pluginClass)) {
            $pluginClass::cleanup($objectFiles);
        }

        foreach ($uploadedFiles as $file) {
            if (($file['storage'] ?? '') !== 'local' || empty($file['path'])) {
                continue;
            }
            $absolutePath = __TYPECHO_ROOT_DIR__ . '/' . ltrim($file['path'], '/');
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function cleanupFailedPost($cid) {
        $db = Db::get();
        $attachmentRows = $db->fetchAll($db->select('cid')->from('table.contents')->where('type = ?', 'attachment')->where('parent = ?', (int) $cid));
        foreach ($attachmentRows as $attachment) {
            $db->query($db->delete('table.fields')->where('cid = ?', (int) $attachment['cid']));
        }
        $db->query($db->delete('table.contents')->where('type = ?', 'attachment')->where('parent = ?', (int) $cid));
        $db->query($db->delete('table.fields')->where('cid = ?', (int) $cid));
        $db->query($db->delete('table.icefox_archive')->where('cid = ?', (int) $cid));
        $db->query($db->delete('table.contents')->where('cid = ?', (int) $cid));
    }

    /**
     * 构建文章内容（插入媒体）
     */
    private function buildPostContent($content, $uploadedFiles) {
        $mediaHtml = '';

        if (!empty($uploadedFiles)) {
            $images = array_filter($uploadedFiles, function($f) { return $f['type'] === 'image'; });
            $videos = array_filter($uploadedFiles, function($f) { return $f['type'] === 'video'; });

            // 添加图片
            if (!empty($images)) {
                $mediaHtml .= "\n\n";
                foreach ($images as $img) {
                    $mediaHtml .= "![图片]({$img['path']})\n";
                }
            }

            // 添加视频
            if (!empty($videos)) {
                $mediaHtml .= "\n\n";
                foreach ($videos as $video) {
                    $mediaHtml .= "<video src=\"{$video['path']}\" controls></video>\n";
                }
            }
        }

        return $content . $mediaHtml;
    }

    /**
     * 生成文章标题（从内容中提取）
     */
    private function generateTitle($content) {
        $content = trim($content);
        if (empty($content)) {
            return '无标题 - ' . date('Y-m-d H:i');
        }

        // 取第一行作为标题，最多50字符
        $firstLine = strtok($content, "\n");
        $title = mb_substr(strip_tags($firstLine), 0, 50, 'UTF-8');

        return $title ?: '无标题 - ' . date('Y-m-d H:i');
    }

    /**
     * 生成唯一slug
     */
    private function generateSlug($content) {
        $slug = 'post-' . date('YmdHis') . '-' . substr(uniqid(), -6);
        return $slug;
    }

    /**
     * 保存文章自定义字段
     */
    private function savePostField($cid, $name, $type, $value) {
        $db = Db::get();

        $data = [
            'cid' => $cid,
            'name' => $name,
            'type' => $type,
            'str_value' => ($type === 'str') ? $value : null,
            'int_value' => ($type === 'int') ? intval($value) : 0,
            'float_value' => ($type === 'float') ? floatval($value) : 0
        ];

        $db->query($db->insert('table.fields')->rows($data));
    }

    /**
     * 保存文章附件记录
     */
    private function savePostAttachments($cid, $files) {
        $db = Db::get();
        $user = Widget::widget('Widget_User');

        foreach ($files as $index => $file) {
            // 将附件保存到contents表（作为attachment类型）
            $attachmentData = [
                'title' => $file['name'],
                'slug' => 'attachment-' . uniqid(),
                'created' => time(),
                'modified' => time(),
                'text' => $this->encodeAttachmentMetadata($file),
                'order' => $index,
                'authorId' => $user->uid,
                'type' => 'attachment',
                'status' => 'publish',
                'parent' => $cid,
                'commentsNum' => 0,
                'allowComment' => '0',
                'allowPing' => '0',
                'allowFeed' => '0'
            ];

            $db->query($db->insert('table.contents')->rows($attachmentData));
        }
    }

    private function buildAttachmentMetadata(array $file) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov'
        ];
        $mime = (string) ($file['mime'] ?? '');
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('无法识别附件文件类型');
        }

        $name = mb_convert_encoding((string) ($file['name'] ?? 'media'), 'UTF-8', 'UTF-8');
        return [
            'name' => $name,
            'path' => (string) $file['path'],
            'size' => (int) $file['size'],
            'type' => $extensions[$mime],
            'mime' => $mime,
            'storage' => $file['storage'] ?? 'local',
            'objectKey' => $file['objectKey'] ?? null,
            'url' => $file['url'] ?? $file['path']
        ];
    }

    private function encodeAttachmentMetadata(array $file, $typechoVersion = null) {
        $metadata = $this->buildAttachmentMetadata($file);
        $version = $typechoVersion === null
            ? \Typecho\Common::VERSION
            : (string) $typechoVersion;
        if (version_compare($version, '1.3.0', '<')) {
            return serialize($metadata);
        }

        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('附件元数据编码失败');
        }
        return $encoded;
    }

    private function getAlbums() {
        $db = Db::get();
        $user = Widget::widget('Widget_User');
        $query = $db->select()->from('table.icefox_albums');
        if (!$user->hasLogin()) {
            $query->where('visibility = ?', 'public');
        }
        $query->order('is_moments', Db::SORT_DESC)
            ->order('is_pinned', Db::SORT_DESC)
            ->order('sort_order', Db::SORT_ASC)
            ->order('id', Db::SORT_ASC);

        $albums = array_map([$this, 'formatAlbum'], $db->fetchAll($query));
        $this->returnJson(['success' => true, 'albums' => $albums]);
    }

    private function getAlbum() {
        $request = Request::getInstance();
        $identifier = trim((string) $request->get('album', ''));
        if ($identifier === '') {
            $this->returnJson(['success' => false, 'message' => '相册标识不能为空']);
            return;
        }

        $album = $this->findAlbum($identifier);
        $user = Widget::widget('Widget_User');
        if (!$album || ($album['visibility'] === 'private' && !$user->hasLogin())) {
            $this->returnJson(['success' => false, 'message' => '相册不存在']);
            return;
        }

        $this->returnJson(['success' => true, 'album' => $this->formatAlbum($album)]);
    }

    private function saveAlbum() {
        $request = Request::getInstance();
        $db = Db::get();
        $albumId = trim((string) $request->get('albumId', ''));
        $name = trim((string) $request->get('name', ''));
        $requestedDescription = $request->get('description', null);
        $cover = trim((string) $request->get('cover', ''));
        $visibility = $request->get('visibility', 'public') === 'private' ? 'private' : 'public';
        $storageTarget = $this->resolveStorageTarget($request->get('storage', 'local'));
        $uploadedFiles = [];
        $existing = null;
        $savedId = 0;
        $databaseWritten = false;

        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) {
            $this->returnJson(['success' => false, 'message' => '相册名称不能为空且最多 80 个字符']);
            return;
        }
        if ($cover !== '' && !$this->isImageReference($cover)) {
            $this->returnJson(['success' => false, 'message' => '相册封面必须是 HTTP/HTTPS 图片地址']);
            return;
        }
        if ($requestedDescription !== null && mb_strlen(trim((string) $requestedDescription), 'UTF-8') > 1000) {
            $this->returnJson(['success' => false, 'message' => '相册说明最多 1000 个字符']);
            return;
        }

        try {
            Plugin::ensureAlbumTableSchema();
            $remotePhotos = $this->parseAlbumRemotePhotos($request->get('remotePhotos', '[]'), $name);
            $stagedUploads = $request->get('stagedUploads', '[]');
            $existing = $albumId !== '' ? $this->findAlbum($albumId) : null;
            if ($albumId !== '' && !$existing) {
                throw new \InvalidArgumentException('要编辑的相册不存在');
            }
            if ($existing && (int) $existing['is_moments'] === 1) {
                throw new \InvalidArgumentException('朋友圈相册不可编辑，只能在外观设置中控制是否显示');
            }
            $existingPhotos = $existing ? $this->deduplicateAlbumPhotos($this->decodeAlbumPhotos($existing['photos'])) : [];
            $photosWithRemote = $this->deduplicateAlbumPhotos(array_merge($existingPhotos, $remotePhotos));
            $requestUploadCount = $this->albumRequestUploadCount($stagedUploads);
            if (count($photosWithRemote) + $requestUploadCount > self::ALBUM_PHOTO_LIMIT
                && (count($photosWithRemote) > count($existingPhotos) || $requestUploadCount > 0)) {
                throw new \InvalidArgumentException('每个相册最多 ' . self::ALBUM_PHOTO_LIMIT . ' 张图片');
            }
            $description = $requestedDescription === null
                ? trim((string) ($existing['description'] ?? ''))
                : trim((string) $requestedDescription);
            $tags = Plugin::normalizeAlbumTags($request->get('tags', ''));

            $uploadedFiles = $this->consumeStagedAlbumUploads($stagedUploads);
            $uploadedFiles = array_merge($uploadedFiles, $this->handleMediaUpload($storageTarget, self::ALBUM_PHOTO_LIMIT, false));
            foreach ($uploadedFiles as $file) {
                if ($file['type'] !== 'image') {
                    throw new \InvalidArgumentException('相册只允许上传图片');
                }
            }
            $photos = $existingPhotos;
            foreach ($uploadedFiles as $file) {
                $photos[] = [
                    'src' => $file['path'],
                    'url' => $file['path'],
                    'alt' => $file['name'],
                    'storage' => $file['storage'] ?? 'local',
                    'objectKey' => $file['objectKey'] ?? null
                ];
            }
            $photos = $this->deduplicateAlbumPhotos(array_merge($photos, $remotePhotos));
            if (count($photos) > self::ALBUM_PHOTO_LIMIT
                && count($photos) > count($existingPhotos)) {
                throw new \InvalidArgumentException('每个相册最多 ' . self::ALBUM_PHOTO_LIMIT . ' 张图片');
            }
            if ($cover === '' && !empty($photos)) {
                $cover = $photos[0]['src'];
            }

            $isPinned = $request->get('isPinned', null) === null
                ? (int) ($existing['is_pinned'] ?? 0)
                : ($request->get('isPinned', '0') === '1' ? 1 : 0);
            $requestedSortOrder = $request->get('sortOrder', null);
            $sortOrder = $requestedSortOrder === null
                ? ($existing ? (int) $existing['sort_order'] : $this->nextAlbumSortOrder())
                : max(0, min(2147483647, (int) $requestedSortOrder));
            $now = time();
            $rows = [
                'name' => $name,
                'description' => $description,
                'cover' => $cover,
                'tags' => implode(',', $tags),
                'address' => trim((string) $request->get('address', '')),
                'visibility' => $visibility,
                'photos' => json_encode($photos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'is_pinned' => $isPinned,
                'sort_order' => $sortOrder,
                'updated_at' => $now
            ];

            if ($existing) {
                $db->query($db->update('table.icefox_albums')->rows($rows)->where('id = ?', (int) $existing['id']));
                $savedId = (int) $existing['id'];
            } else {
                $rows['slug'] = 'album-' . bin2hex(random_bytes(8));
                $rows['is_moments'] = 0;
                $rows['created_at'] = $now;
                $savedId = (int) $db->query($db->insert('table.icefox_albums')->rows($rows));
            }
            if (!$savedId) {
                throw new \RuntimeException('相册记录写入失败');
            }
            $databaseWritten = true;
            Plugin::syncAlbumTags($savedId, $tags);

            $saved = $this->findAlbum((string) $savedId);
            if (!$saved) {
                throw new \RuntimeException('无法读取已保存的相册');
            }
            $this->returnJson(['success' => true, 'message' => '相册保存成功', 'album' => $this->formatAlbum($saved)]);
        } catch (\Exception $error) {
            if ($databaseWritten) {
                $this->rollbackAlbumWrite($existing, $savedId);
            }
            $this->cleanupUploadedObjects($uploadedFiles);
            $this->returnJson(['success' => false, 'message' => '相册保存失败：' . $error->getMessage()]);
        }
    }

    private function stageAlbumUpload() {
        $request = Request::getInstance();
        $pluginClass = '\\TypechoPlugin\\IcefoxStorage\\Plugin';
        $uploadId = strtolower(trim((string) $request->get('uploadId', '')));
        $fileName = trim((string) $request->get('name', ''));
        $fileSize = (int) $request->get('size', 0);
        $chunkIndex = (int) $request->get('chunkIndex', -1);
        $chunkCount = (int) $request->get('chunkCount', 0);

        try {
            if ($request->get('storage', 'local') !== 'object') {
                throw new \InvalidArgumentException('分片上传仅用于对象存储');
            }
            if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
                throw new \InvalidArgumentException('上传任务标识无效');
            }
            if ($fileName === '' || mb_strlen($fileName, 'UTF-8') > 255) {
                throw new \InvalidArgumentException('图片文件名无效');
            }
            if (!class_exists($pluginClass) || !$pluginClass::isConfigured()) {
                throw new \RuntimeException('IcefoxStorage 插件未启用或对象存储配置不完整');
            }

            $maxFileSize = $pluginClass::maxFileSizeBytes();
            $expectedChunks = (int) ceil($fileSize / self::STAGED_UPLOAD_CHUNK_SIZE);
            if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                throw new \InvalidArgumentException('图片大小超过对象存储插件限制');
            }
            if ($chunkCount !== $expectedChunks || $chunkIndex < 0 || $chunkIndex >= $chunkCount) {
                throw new \InvalidArgumentException('图片分片参数无效');
            }

            $body = file_get_contents('php://input');
            $expectedChunkSize = $chunkIndex === $chunkCount - 1
                ? $fileSize - ($chunkIndex * self::STAGED_UPLOAD_CHUNK_SIZE)
                : self::STAGED_UPLOAD_CHUNK_SIZE;
            if (!is_string($body) || strlen($body) !== $expectedChunkSize) {
                throw new \RuntimeException('图片分片接收不完整');
            }

            $user = Widget::widget('Widget_User');
            $root = $this->stagedUploadRoot();
            $this->cleanupExpiredStagedUploads($root);
            $directory = $this->stagedUploadDirectory($uploadId, $user);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('无法创建图片分片暂存目录');
            }

            $partPath = $directory . '/' . sprintf('%04d.part', $chunkIndex);
            if (file_put_contents($partPath, $body, LOCK_EX) !== $expectedChunkSize) {
                throw new \RuntimeException('图片分片暂存失败');
            }

            if ($chunkIndex !== $chunkCount - 1) {
                $this->returnJson([
                    'success' => true,
                    'complete' => false,
                    'received' => $chunkIndex + 1,
                    'total' => $chunkCount
                ]);
                return;
            }

            $completePath = $directory . '/upload.bin';
            $output = fopen($completePath . '.tmp', 'wb');
            if ($output === false) {
                throw new \RuntimeException('无法合并图片分片');
            }
            try {
                for ($index = 0; $index < $chunkCount; $index++) {
                    $currentPart = $directory . '/' . sprintf('%04d.part', $index);
                    $input = fopen($currentPart, 'rb');
                    if ($input === false) {
                        throw new \RuntimeException('图片分片缺失，请重新上传');
                    }
                    try {
                        if (stream_copy_to_stream($input, $output) === false) {
                            throw new \RuntimeException('图片分片合并失败');
                        }
                    } finally {
                        fclose($input);
                    }
                }
            } finally {
                fclose($output);
            }

            if (!rename($completePath . '.tmp', $completePath) || filesize($completePath) !== $fileSize) {
                throw new \RuntimeException('图片分片合并结果不完整');
            }
            for ($index = 0; $index < $chunkCount; $index++) {
                @unlink($directory . '/' . sprintf('%04d.part', $index));
            }

            $createdAt = time();
            $receiptSignature = $this->stagedUploadReceiptSignature($uploadId, $fileName, $fileSize, $createdAt, $user);
            $manifest = [
                'uploadId' => $uploadId,
                'name' => $fileName,
                'size' => $fileSize,
                'createdAt' => $createdAt,
                'uid' => (int) $user->uid,
                'signature' => $receiptSignature
            ];
            $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($manifestJson === false || file_put_contents($directory . '/manifest.json', $manifestJson, LOCK_EX) === false) {
                throw new \RuntimeException('无法生成图片暂存凭据');
            }

            $this->returnJson([
                'success' => true,
                'complete' => true,
                'receipt' => $uploadId . '.' . $receiptSignature
            ]);
        } catch (\Exception $error) {
            $this->returnJson(['success' => false, 'message' => '图片暂存失败：' . $error->getMessage()]);
        }
    }

    private function consumeStagedAlbumUploads($rawValue) {
        $receipts = is_array($rawValue) ? $rawValue : json_decode((string) $rawValue, true);
        if ($receipts === null || $receipts === '') {
            return [];
        }
        if (!is_array($receipts) || count($receipts) > self::ALBUM_PHOTO_LIMIT) {
            throw new \InvalidArgumentException('图片暂存凭据格式不正确');
        }

        $pluginClass = '\\TypechoPlugin\\IcefoxStorage\\Plugin';
        if ($receipts && (!class_exists($pluginClass) || !$pluginClass::isConfigured())) {
            throw new \RuntimeException('IcefoxStorage 插件未启用或对象存储配置不完整');
        }

        $user = Widget::widget('Widget_User');
        $uploadedFiles = [];
        try {
            foreach ($receipts as $receipt) {
                if (!is_string($receipt) || !preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $receipt, $matches)) {
                    throw new \InvalidArgumentException('图片暂存凭据无效');
                }

                $uploadId = $matches[1];
                $signature = $matches[2];
                $directory = $this->stagedUploadDirectory($uploadId, $user);
                $manifestPath = $directory . '/manifest.json';
                $completePath = $directory . '/upload.bin';
                $manifest = is_file($manifestPath)
                    ? json_decode((string) file_get_contents($manifestPath), true)
                    : null;
                if (!is_array($manifest) || !is_file($completePath)) {
                    throw new \RuntimeException('图片暂存已失效，请重新选择图片');
                }

                $fileName = (string) ($manifest['name'] ?? '');
                $fileSize = (int) ($manifest['size'] ?? 0);
                $createdAt = (int) ($manifest['createdAt'] ?? 0);
                $expectedSignature = $this->stagedUploadReceiptSignature($uploadId, $fileName, $fileSize, $createdAt, $user);
                if ((int) ($manifest['uid'] ?? 0) !== (int) $user->uid
                    || !hash_equals($expectedSignature, $signature)
                    || !hash_equals((string) ($manifest['signature'] ?? ''), $signature)
                    || $createdAt < time() - self::STAGED_UPLOAD_TTL
                    || filesize($completePath) !== $fileSize) {
                    throw new \RuntimeException('图片暂存凭据校验失败，请重新上传');
                }

                $uploadedFiles[] = $pluginClass::uploadPath($completePath, $fileName);
                $this->removeStagedUploadDirectory($directory);
            }
        } catch (\Exception $error) {
            $this->cleanupUploadedObjects($uploadedFiles);
            throw $error;
        }

        return $uploadedFiles;
    }

    private function albumRequestUploadCount($stagedUploads) {
        $receipts = is_array($stagedUploads) ? $stagedUploads : json_decode((string) $stagedUploads, true);
        $uploadCount = is_array($receipts) ? count($receipts) : 0;

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'media_') === 0
                && is_array($file)
                && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadCount++;
            }
        }

        return $uploadCount;
    }

    private function stagedUploadRoot() {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'icefox-staged-uploads';
    }

    private function stagedUploadDirectory($uploadId, $user) {
        $directoryName = hash_hmac('sha256', (string) $uploadId, $this->stagedUploadSecret($user));
        return $this->stagedUploadRoot() . DIRECTORY_SEPARATOR . $directoryName;
    }

    private function stagedUploadSecret($user) {
        $options = Widget::widget('Widget_Options');
        return hash('sha256', (string) $options->secret . '|' . (int) $user->uid . '|' . (string) $user->authCode);
    }

    private function stagedUploadReceiptSignature($uploadId, $fileName, $fileSize, $createdAt, $user) {
        return hash_hmac(
            'sha256',
            implode('|', [(string) $uploadId, (string) $fileName, (int) $fileSize, (int) $createdAt]),
            $this->stagedUploadSecret($user)
        );
    }

    private function cleanupExpiredStagedUploads($root) {
        if (!is_dir($root)) {
            return;
        }
        $entries = scandir($root);
        if (!is_array($entries)) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || !preg_match('/^[a-f0-9]{64}$/', $entry)) {
                continue;
            }
            $directory = $root . DIRECTORY_SEPARATOR . $entry;
            $modifiedAt = filemtime($directory);
            if (is_dir($directory) && $modifiedAt !== false && $modifiedAt < time() - self::STAGED_UPLOAD_TTL) {
                $this->removeStagedUploadDirectory($directory);
            }
        }
    }

    private function removeStagedUploadDirectory($directory) {
        if (!is_dir($directory) || strpos($directory, $this->stagedUploadRoot() . DIRECTORY_SEPARATOR) !== 0) {
            return;
        }
        $entries = scandir($directory);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $path = $directory . DIRECTORY_SEPARATOR . $entry;
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
        }
        @rmdir($directory);
    }

    private function rollbackAlbumWrite($existing, $savedId) {
        $db = Db::get();
        try {
            if (!$existing) {
                if ($savedId) {
                    $db->query($db->delete('table.icefox_albums')->where('id = ?', (int) $savedId));
                }
                return;
            }

            $rows = [];
            foreach ([
                'slug', 'name', 'description', 'cover', 'tags', 'address', 'visibility', 'photos',
                'is_pinned', 'is_moments', 'sort_order', 'created_at', 'updated_at'
            ] as $column) {
                if (array_key_exists($column, $existing)) {
                    $rows[$column] = $existing[$column];
                }
            }
            $db->query(
                $db->update('table.icefox_albums')
                    ->rows($rows)
                    ->where('id = ?', (int) $existing['id'])
            );
            Plugin::syncAlbumTags((int) $existing['id'], $existing['tags'] ?? '');
        } catch (\Exception $rollbackError) {
            error_log('Icefox album rollback failed for album ID ' . (int) $savedId);
        }
    }

    private function findAlbum($identifier) {
        $db = Db::get();
        $identifier = trim((string) $identifier);
        $query = $db->select()->from('table.icefox_albums');
        if (ctype_digit($identifier)) {
            $query->where('id = ?', (int) $identifier);
        } elseif (in_array(strtolower($identifier), ['moments', 'pengyouquan'], true)) {
            $query->where('is_moments = ?', 1);
        } else {
            $query->where('slug = ?', $identifier);
        }
        return $db->fetchRow($query->limit(1));
    }

    private function findMomentsAlbum($create = true) {
        $db = Db::get();
        $album = $db->fetchRow($db->select()->from('table.icefox_albums')->where('is_moments = ?', 1)->limit(1));
        if ($album || !$create) {
            return $album;
        }

        $now = time();
        $id = $db->query($db->insert('table.icefox_albums')->rows([
            'slug' => 'moments',
            'name' => '朋友圈',
            'description' => '',
            'cover' => '',
            'tags' => '',
            'address' => '',
            'visibility' => 'public',
            'photos' => '[]',
            'is_pinned' => 1,
            'is_moments' => 1,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now
        ]));
        return $this->findAlbum((string) $id);
    }

    private function appendImagesToMomentsAlbum($postContent, array $uploadedFiles) {
        $photos = $this->extractPostImagePhotos($postContent);
        $metadataByUrl = [];
        foreach ($uploadedFiles as $file) {
            if (($file['type'] ?? '') === 'image') {
                $metadataByUrl[$file['path']] = $file;
            }
        }
        foreach ($photos as &$photo) {
            if (isset($metadataByUrl[$photo['src']])) {
                $file = $metadataByUrl[$photo['src']];
                $photo['storage'] = $file['storage'] ?? 'local';
                $photo['objectKey'] = $file['objectKey'] ?? null;
            }
        }
        unset($photo);
        if (!$photos) {
            return 0;
        }

        $album = $this->findMomentsAlbum();
        $existing = $this->decodeAlbumPhotos($album['photos']);
        $merged = $this->deduplicateAlbumPhotos(array_merge($existing, $photos));
        $added = count($merged) - count($this->deduplicateAlbumPhotos($existing));
        $cover = $album['cover'] ?: ($merged[0]['src'] ?? '');
        Db::get()->query(Db::get()->update('table.icefox_albums')->rows([
            'photos' => json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'cover' => $cover,
            'updated_at' => time()
        ])->where('id = ?', (int) $album['id']));
        return max(0, $added);
    }

    private function extractPostImagePhotos($postContent) {
        $candidates = [];
        if (preg_match_all('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+["\'][^"\']*["\'])?\)/u', $postContent, $markdownMatches, PREG_SET_ORDER)) {
            foreach ($markdownMatches as $match) {
                $candidates[] = ['src' => $match[2], 'alt' => $match[1]];
            }
        }
        if (preg_match_all('/<img\b[^>]*>/iu', $postContent, $htmlMatches)) {
            foreach ($htmlMatches[0] as $tag) {
                if (!preg_match('/\bsrc=["\']([^"\']+)["\']/iu', $tag, $srcMatch)) {
                    continue;
                }
                $alt = preg_match('/\balt=["\']([^"\']*)["\']/iu', $tag, $altMatch)
                    ? $altMatch[1]
                    : '';
                $candidates[] = ['src' => $srcMatch[1], 'alt' => $alt];
            }
        }

        $photos = [];
        foreach ($candidates as $candidate) {
            $url = html_entity_decode(trim($candidate['src']), ENT_QUOTES, 'UTF-8');
            if ($url === '' || ($url[0] !== '/' && !$this->isHttpUrl($url))) {
                continue;
            }
            $alt = trim(strip_tags(html_entity_decode($candidate['alt'], ENT_QUOTES, 'UTF-8')));
            $photos[] = ['src' => $url, 'url' => $url, 'alt' => $alt !== '' ? $alt : '图片'];
        }
        return $this->deduplicateAlbumPhotos($photos);
    }

    private function parseAlbumRemotePhotos($rawValue, $name = '') {
        if (is_array($rawValue)) {
            $values = $rawValue;
        } else {
            $values = json_decode((string) $rawValue, true);
        }
        if ($values === null || $values === '') {
            return [];
        }
        if (!is_array($values)) {
            throw new \InvalidArgumentException('远程图片列表格式不正确');
        }

        $photos = [];
        foreach ($values as $value) {
            $url = trim(is_array($value) ? (string) ($value['src'] ?? $value['url'] ?? '') : (string) $value);
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true) || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('远程图片仅支持有效的 HTTP/HTTPS 地址');
            }
            $photos[] = ['src' => $url, 'url' => $url, 'alt' => $name];
        }
        return $this->deduplicateAlbumPhotos($photos);
    }

    private function decodeAlbumPhotos($value) {
        if (is_array($value)) {
            return $value;
        }
        $photos = json_decode((string) $value, true);
        return is_array($photos) ? $photos : [];
    }

    private function deduplicateAlbumPhotos(array $photos) {
        $result = [];
        $seen = [];
        foreach ($photos as $photo) {
            $src = trim((string) ($photo['src'] ?? $photo['url'] ?? ''));
            if ($src === '' || isset($seen[$src])) {
                continue;
            }
            $seen[$src] = true;
            $photo['src'] = $src;
            $photo['url'] = $src;
            $result[] = $photo;
        }
        return $result;
    }

    private function nextAlbumSortOrder() {
        $row = Db::get()->fetchRow(Db::get()->select('MAX(sort_order) AS max_sort')->from('table.icefox_albums')->where('is_moments = ?', 0));
        return max(1, (int) ($row['max_sort'] ?? 0) + 1);
    }

    private function formatAlbum($album) {
        $photos = $this->decodeAlbumPhotos($album['photos'] ?? '[]');
        $tagLinks = Plugin::getAlbumTagLinks((int) $album['id'], $album['tags'] ?? '');
        $tags = array_column($tagLinks, 'name');
        return [
            'id' => (int) $album['id'],
            'slug' => (string) $album['slug'],
            'name' => (string) $album['name'],
            'description' => (string) ($album['description'] ?? ''),
            'cover' => (string) ($album['cover'] ?: ($photos[0]['src'] ?? '')),
            'tags' => $tags,
            'tagLinks' => $tagLinks,
            'address' => (string) ($album['address'] ?? ''),
            'visibility' => $album['visibility'] === 'private' ? 'private' : 'public',
            'photos' => $photos,
            'isPinned' => (bool) $album['is_pinned'],
            'isMoments' => (bool) $album['is_moments'],
            'sortOrder' => (int) $album['sort_order']
        ];
    }

    private function isHttpUrl($value) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function isImageReference($value) {
        $value = trim((string) $value);
        return (isset($value[0]) && $value[0] === '/' && strpos($value, '//') !== 0) || $this->isHttpUrl($value);
    }


    /**
     * 获取文章点赞记录列表（支持分页）
     */
    private function getLikeRecords() {
        $request = Request::getInstance();
        $db = Db::get();
        $prefix = $db->getPrefix();

        $cid = intval($request->get('cid'));
        $page = intval($request->get('page', 1));
        $pageSize = intval($request->get('pageSize', 10));

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        if ($page < 1) $page = 1;
        if ($pageSize < 1 || $pageSize > 100) $pageSize = 10;

        $offset = ($page - 1) * $pageSize;

        try {
            // 获取总数
            $totalResult = $db->fetchRow(
                $db->select('COUNT(*) as total')
                    ->from($prefix . 'icefox_likes')
                    ->where('cid = ?', $cid)
            );
            $total = $totalResult ? intval($totalResult['total']) : 0;
            $totalPages = ceil($total / $pageSize);

            // 获取分页数据
            $records = $db->fetchAll(
                $db->select('id', 'author', 'mail', 'ip', 'created_at')
                    ->from($prefix . 'icefox_likes')
                    ->where('cid = ?', $cid)
                    ->order('created_at', Db::SORT_DESC)
                    ->offset($offset)
                    ->limit($pageSize)
            );

            // 格式化数据
            $data = [];
            foreach ($records as $record) {
                $data[] = [
                    'id' => $record['id'],
                    'author' => $record['author'] ?: '匿名用户',
                    'mail' => $record['mail'] ?: '-',
                    'ip' => $record['ip'] ?: '-',
                    'created_at' => date('Y-m-d H:i', $record['created_at'])
                ];
            }

            $this->returnJson([
                'success' => true,
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => $totalPages
            ]);
        } catch (\Exception $e) {
            $this->returnJson([
                'success' => false,
                'message' => '获取点赞记录失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除单条点赞记录
     */
    private function deleteLikeRecord() {
        $request = Request::getInstance();
        $db = Db::get();
        $prefix = $db->getPrefix();

        $id = intval($request->get('id'));

        if (empty($id)) {
            $this->returnJson(['success' => false, 'message' => '记录ID缺失']);
            return;
        }

        try {
            // 查询记录是否存在，并获取 cid
            $record = $db->fetchRow(
                $db->select('id', 'cid', 'author')
                    ->from($prefix . 'icefox_likes')
                    ->where('id = ?', $id)
            );

            if (!$record) {
                $this->returnJson(['success' => false, 'message' => '记录不存在']);
                return;
            }

            $cid = (int) $record['cid'];
            $author = $record['author'] ?: '匿名用户';

            // 删除点赞记录
            $db->query(
                $db->delete($prefix . 'icefox_likes')
                    ->where('id = ?', $id)
            );

            // 同步更新 icefox_archive 表的 likes 计数
            $db->query(
                "UPDATE `{$prefix}icefox_archive` SET likes = GREATEST(likes - 1, 0) WHERE cid = " . $cid
            );

            $this->returnJson([
                'success' => true,
                'message' => '已删除「' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '」的点赞记录'
            ]);
        } catch (\Exception $e) {
            $this->returnJson([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 返回JSON响应
     */
    private function returnJson($data) {
        $this->response->setStatus(200);
        $this->response->setContentType('application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
