<?php
/** 初始化上下文 */
\Widget\Options::alloc()->to($options);
\Widget\User::alloc()->to($user);
\Widget\Security::alloc()->to($security);
\Widget\Menu::alloc()->to($menu);

$request = $options->request;
$response = $options->response;
$isLoggedIn = $user->hasLogin();

// 检查是否有登录错误
$loginError = '';

// 1. 检查 URL 参数
$errorMsg = $request->get('msg') ?: $request->get('error') ?: $request->get('login_error');

// 2. 检查 Typecho 的 Notice cookie（主要的错误来源）
// Typecho 使用带前缀的 Cookie 名称
$noticeValue = \Typecho\Cookie::get('__typecho_notice');
$noticeType = \Typecho\Cookie::get('__typecho_notice_type');

// 尝试直接从 $_COOKIE 读取（带前缀）
$cookiePrefix = \Typecho\Cookie::getPrefix();
$rawNoticeValue = $_COOKIE[$cookiePrefix . '__typecho_notice'] ?? null;

if ($errorMsg) {
    $errorMessages = [
        'invalid' => '用户名或密码错误',
        'logout' => '您已成功退出登录',
        'expired' => '登录已过期，请重新登录',
        'fail' => '登录失败，请重试',
        'protected' => '该页面需要登录后访问'
    ];
    $loginError = isset($errorMessages[$errorMsg]) ? $errorMessages[$errorMsg] : '登录失败，请重试';
} elseif ($noticeValue || $rawNoticeValue) {
    // 优先使用原始值（可能更准确）
    $valueToProcess = $rawNoticeValue ?: $noticeValue;

    // Typecho 的 notice 是 JSON 编码的数组
    // 格式：["错误消息"] 或 ["success", "成功消息"]
    $notices = @json_decode($valueToProcess, true);

    if (is_array($notices) && !empty($notices)) {
        // 如果是数组，取第一个元素作为消息
        $loginError = is_string($notices[0]) ? $notices[0] : '';

        // 如果有第二个元素，说明第一个是类型，第二个才是消息
        if (count($notices) > 1 && is_string($notices[1])) {
            $loginError = $notices[1];
        }
    } else {
        // 如果不是 JSON，直接使用原始值
        $loginError = $valueToProcess;
    }

    // 清除 Cookie（防止刷新后重复显示）
    \Typecho\Cookie::delete('__typecho_notice');
    \Typecho\Cookie::delete('__typecho_notice_type');
}

?>

<div class="settings-account-panel">

        <!-- 错误/成功提示 -->
        <?php if (!empty($loginError)): ?>
            <?php
            $isSuccess = (strpos($loginError, '成功') !== false) || (strpos($loginError, '退出登录') !== false);
            $alertClass = $isSuccess ? 'login-success-alert' : 'login-error-alert';
            ?>
            <div id="loginAlertMessage" class="<?php echo $alertClass; ?>" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="20" height="20" stroke-width="1.5" stroke="currentColor">
                    <?php if ($isSuccess): ?>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    <?php else: ?>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    <?php endif; ?>
                </svg>
                <span><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if (!$isSuccess): ?>
                    <button type="button" class="login-error-close" onclick="document.getElementById('loginAlertMessage').style.display='none'">×</button>
                <?php endif; ?>
            </div>

            <script>
            (function() {
                // 自动显示登录弹窗和错误提示
                const modal = document.querySelector('.setting-modal');
                const alert = document.getElementById('loginAlertMessage');

                if (modal && alert) {
                    // 获取 Alpine.js 数据
                    setTimeout(() => {
                        const alpineData = Alpine.$data(modal);
                        if (alpineData) {
                            alpineData.settingModalShow = true;

                            // 显示错误提示（带动画）
                            setTimeout(() => {
                                alert.style.display = 'flex';
                                alert.style.animation = 'slideInDown 0.3s ease-out';

                                // 5秒后自动隐藏错误提示
                                setTimeout(() => {
                                    alert.style.animation = 'fadeOut 0.2s ease-in';
                                    setTimeout(() => {
                                        alert.style.display = 'none';
                                    }, 200);
                                }, 5000);
                            }, 100);
                        }
                    }, 100);
                }
            })();
            </script>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <!-- 已登录状态：显示用户信息 -->
            <div class="user-info-panel">
                <div class="user-info-avatar">
                    <img src="<?php echo getGravatarUrl($user->mail, 80, 'identicon', 'g'); ?>" alt="<?php echo htmlspecialchars($user->screenName, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="user-info-details">
                    <div class="user-info-item user-info-item-nickname">
                        <span class="user-info-label">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            昵称：
                        </span>
                        <span class="user-info-value"><?php echo htmlspecialchars($user->screenName, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="user-info-item user-info-item-email">
                        <span class="user-info-label">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            邮箱：
                        </span>
                        <span class="user-info-value"><?php echo htmlspecialchars($user->mail, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if ($user->url): ?>
                    <div class="user-info-item user-info-item-url">
                        <span class="user-info-label">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                            </svg>
                            网址：
                        </span>
                        <span class="user-info-value">
                            <a href="<?php echo htmlspecialchars($user->url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($user->url, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="user-info-item user-info-item-group">
                        <span class="user-info-label">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            权限组：
                        </span>
                        <span class="user-info-value user-group-<?php echo htmlspecialchars($user->group, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php
                            $groupNames = [
                                'administrator' => '管理员',
                                'editor' => '编辑',
                                'contributor' => '贡献者',
                                'subscriber' => '订阅者',
                                'visitor' => '访客'
                            ];
                            echo isset($groupNames[$user->group]) ? $groupNames[$user->group] : htmlspecialchars($user->group, ENT_QUOTES, 'UTF-8');
                            ?>
                        </span>
                    </div>
                </div>
                <div class="user-info-actions">
                    <?php if ($user->pass('administrator', true) || $user->pass('editor', true)): ?>
                    <a href="<?php $options->adminUrl(); ?>" class="user-info-btn user-info-btn-admin" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        管理后台
                    </a>
                    <?php endif; ?>
                    <a href="<?php $options->logoutUrl(); ?>" class="user-info-btn user-info-btn-logout">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </svg>
                        退出登录
                    </a>
                </div>
            </div>

            <!-- 登录成功后，将用户信息写入 localStorage -->
            <script>
            (function() {
                try {
                    localStorage.setItem('icefox_comment_author', <?php echo json_encode($user->screenName, JSON_UNESCAPED_UNICODE); ?>);
                    localStorage.setItem('icefox_comment_email', <?php echo json_encode($user->mail, JSON_UNESCAPED_UNICODE); ?>);
                    <?php if ($user->url): ?>
                    localStorage.setItem('icefox_comment_url', <?php echo json_encode($user->url, JSON_UNESCAPED_UNICODE); ?>);
                    <?php endif; ?>
                } catch (e) {
                    // Ignore storage failures in restricted browser contexts.
                }
            })();
            </script>
        <?php else: ?>
            <!-- 未登录状态：显示登录表单 -->
            <form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form" class="login-form" id="loginForm">
                <!-- 客户端验证错误提示 -->
                <div id="validationError" class="login-error-alert" style="display: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="20" height="20" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <span id="validationErrorText"></span>
                    <button type="button" class="login-error-close" onclick="document.getElementById('validationError').style.display='none'">×</button>
                </div>
                <div class="login-form-group">
                    <label for="name" class="login-form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        用户名
                    </label>
                    <input type="text" id="name" name="name" value="" placeholder="请输入用户名" class="login-form-input" autocomplete="username" autofocus required>
                </div>

                <div class="login-form-group">
                    <label for="password" class="login-form-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        密码
                    </label>
                    <input type="password" id="password" name="password" class="login-form-input" placeholder="请输入密码" autocomplete="current-password" required>
                </div>

                <div class="login-form-remember">
                    <label for="remember" class="login-remember-label">
                        <input<?php if(\Typecho\Cookie::get('__typecho_remember_remember')): ?> checked<?php endif; ?> type="checkbox" name="remember" class="login-checkbox" value="1" id="remember" />
                        <span>下次自动登录</span>
                    </label>
                </div>

                <div class="login-form-submit">
                    <button type="submit" class="login-submit-btn" id="loginSubmitBtn">
                        <svg id="loginIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="18" height="18" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <svg id="loginSpinner" class="login-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="18" height="18" style="display: none;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="loginBtnText">登录</span>
                    </button>
                    <input type="hidden" name="referer" value="<?php
                        // 获取当前页面完整 URL（去除错误参数）
                        $currentUrl = $request->getRequestUrl();
                        // 移除可能的错误参数
                        $currentUrl = preg_replace('/[?&](msg|error|login_error)=[^&]*&?/', '', $currentUrl);
                        $currentUrl = rtrim($currentUrl, '?&');
                        echo htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');
                    ?>" />
                </div>
            </form>

            <!-- 表单验证脚本 -->
            <script>
            (function() {
                const form = document.getElementById('loginForm');
                const submitBtn = document.getElementById('loginSubmitBtn');
                const btnText = document.getElementById('loginBtnText');
                const loginIcon = document.getElementById('loginIcon');
                const loginSpinner = document.getElementById('loginSpinner');
                const validationError = document.getElementById('validationError');
                const validationErrorText = document.getElementById('validationErrorText');

                if (form) {
                    form.addEventListener('submit', function(e) {
                        const username = document.getElementById('name').value.trim();
                        const password = document.getElementById('password').value;

                        // 验证用户名
                        if (username.length < 2) {
                            e.preventDefault();
                            showValidationError('用户名至少需要2个字符');
                            return false;
                        }

                        // 验证密码
                        if (password.length < 6) {
                            e.preventDefault();
                            showValidationError('密码至少需要6个字符');
                            return false;
                        }

                        // 验证通过，显示loading状态
                        submitBtn.disabled = true;
                        submitBtn.classList.add('loading');
                        loginIcon.style.display = 'none';
                        loginSpinner.style.display = 'inline-block';
                        btnText.textContent = '登录中...';
                    });
                }

                function showValidationError(message) {
                    validationErrorText.textContent = message;
                    validationError.style.display = 'flex';
                    validationError.style.animation = 'slideInDown 0.3s ease-out';

                    // 3秒后自动隐藏
                    setTimeout(() => {
                        validationError.style.animation = 'fadeOut 0.2s ease-in';
                        setTimeout(() => {
                            validationError.style.display = 'none';
                        }, 200);
                    }, 3000);
                }
            })();
            </script>
        <?php endif; ?>
</div>
