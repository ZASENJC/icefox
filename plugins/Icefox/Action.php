<?php

namespace TypechoPlugin\Icefox;

use Typecho\Widget;
use Typecho\Db;
use Typecho\Request;
use Widget\ActionInterface;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Action extends Widget implements ActionInterface {
    public function action(){
        $request = Request::getInstance();
        $user = Widget::widget('Widget_User');

        // 操作类型
        $do = $request->get('do');
        if (empty($do)) {
            $this->returnJson(['success' => false, 'message' => '操作类型缺失']);
            return;
        }

        $postActions = ['like', 'addComment', 'deleteFriendLink', 'deleteLikeRecord', 'createPost', 'saveAlbum'];
        if (in_array($do, $postActions, true) && !$this->request->isPost()) {
            $this->returnJson(['success' => false, 'message' => '该操作仅支持 POST 请求']);
            return;
        }
        if ($this->request->isPost()) {
            Widget::widget('Widget_Security')->protect();
        }

        // 公开读取、点赞和评论操作不需要管理员权限
        if ($do === 'like' || $do === 'getLikes' || $do === 'addComment' || $do === 'getFriendLinks' || $do === 'getAlbums' || $do === 'getAlbum') {
            if ($do === 'like') {
                $this->toggleLike();
            } else if ($do === 'getLikes') {
                $this->getLikes();
            } else if ($do === 'addComment') {
                $this->addComment();
            } else if ($do === 'getFriendLinks') {
                $this->getFriendLinks();
            } else if ($do === 'getAlbums') {
                $this->getAlbums();
            } else if ($do === 'getAlbum') {
                $this->getAlbum();
            }
            return;
        }

        // 删除友情链接需要管理员权限
        if ($do === 'deleteFriendLink') {
            if (!$user->pass('administrator')) {
                $this->returnJson(['success' => false, 'message' => '无权操作']);
                return;
            }
            $this->deleteFriendLink();
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
        if ($do === 'createPost' || $do === 'saveAlbum') {
            if (!$user->hasLogin()) {
                $this->returnJson(['success' => false, 'message' => '请先登录']);
                return;
            }
            if ($do === 'createPost') {
                $this->createPost();
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

        try {
            $remotePhotos = $this->parseAlbumRemotePhotos($request->get('remotePhotos', '[]'), $name);
            $existing = $albumId !== '' ? $this->findAlbum($albumId) : null;
            if ($albumId !== '' && !$existing) {
                throw new \InvalidArgumentException('要编辑的相册不存在');
            }

            $uploadedFiles = $this->handleMediaUpload($storageTarget, 30, false);
            foreach ($uploadedFiles as $file) {
                if ($file['type'] !== 'image') {
                    throw new \InvalidArgumentException('相册只允许上传图片');
                }
            }
            if (count($uploadedFiles) + count($remotePhotos) > 30) {
                throw new \InvalidArgumentException('每次最多添加 30 张图片');
            }

            $photos = $existing ? $this->decodeAlbumPhotos($existing['photos']) : [];
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
            if ($cover === '' && !empty($photos)) {
                $cover = $photos[0]['src'];
            }

            $isMoments = $existing && (int) $existing['is_moments'] === 1;
            $isPinned = $isMoments
                ? 1
                : ($request->get('isPinned', null) === null
                    ? (int) ($existing['is_pinned'] ?? 0)
                    : ($request->get('isPinned', '0') === '1' ? 1 : 0));
            $requestedSortOrder = $request->get('sortOrder', null);
            $sortOrder = $isMoments
                ? 0
                : ($requestedSortOrder === null
                    ? ($existing ? (int) $existing['sort_order'] : $this->nextAlbumSortOrder())
                    : max(0, min(2147483647, (int) $requestedSortOrder)));
            $now = time();
            $rows = [
                'name' => $name,
                'cover' => $cover,
                'tags' => trim((string) $request->get('tags', '')),
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
                'slug', 'name', 'cover', 'tags', 'address', 'visibility', 'photos',
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
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,，]/u', (string) ($album['tags'] ?? '')))));
        return [
            'id' => (int) $album['id'],
            'slug' => (string) $album['slug'],
            'name' => (string) $album['name'],
            'cover' => (string) ($album['cover'] ?: ($photos[0]['src'] ?? '')),
            'tags' => $tags,
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
     * 获取友情链接列表
     */
    private function getFriendLinks() {
        $db = Db::get();
        $prefix = $db->getPrefix();

        try {
            $links = $db->fetchAll(
                $db->select()
                    ->from($prefix . 'icefox_links')
                    ->where('status = ?', 1)
                    ->order('sort', Db::SORT_ASC)
            );

            $this->returnJson([
                'success' => true,
                'data' => $links ? $links : []
            ]);
        } catch (\Exception $e) {
            $this->returnJson([
                'success' => false,
                'message' => '获取友情链接失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 删除友情链接
     */
    private function deleteFriendLink() {
        $request = Request::getInstance();
        $db = Db::get();
        $prefix = $db->getPrefix();

        $id = intval($request->get('id'));

        if (empty($id)) {
            $this->returnJson(['success' => false, 'message' => '链接ID缺失']);
            return;
        }

        try {
            // 检查链接是否存在
            $link = $db->fetchRow(
                $db->select()
                    ->from($prefix . 'icefox_links')
                    ->where('id = ?', $id)
            );

            if (!$link) {
                $this->returnJson(['success' => false, 'message' => '链接不存在']);
                return;
            }

            // 执行删除
            $db->query(
                $db->delete($prefix . 'icefox_links')
                    ->where('id = ?', $id)
            );

            $this->returnJson([
                'success' => true,
                'message' => '友情链接「' . htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8') . '」已删除'
            ]);
        } catch (\Exception $e) {
            $this->returnJson([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ]);
        }
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
