<?php

namespace Typecho {
    class Widget
    {
        public $request;
        public $response;

        public static function widget($name)
        {
            if ($name === 'Widget_User') {
                return new \TestUser();
            }
            if ($name === 'Widget_Security') {
                return new \TestSecurity();
            }

            throw new \RuntimeException('Unexpected widget: ' . $name);
        }
    }

    class Db
    {
    }

    class Request
    {
        public static function getInstance()
        {
            return new self();
        }

        public function get($name, $default = null)
        {
            if ($name === 'do') {
                return getenv('ICEFOX_SECURITY_TEST_MODE') === 'token'
                    ? 'getSecurityToken'
                    : 'createPost';
            }
            if ($name === '_' && getenv('ICEFOX_SECURITY_TEST_MODE') !== 'token') {
                return 'fresh-token';
            }

            return $default;
        }
    }
}

namespace Widget {
    interface ActionInterface
    {
    }
}

namespace {
    class TestRequest
    {
        public function isPost()
        {
            return getenv('ICEFOX_SECURITY_TEST_MODE') !== 'token';
        }

        public function getReferer()
        {
            return 'https://example.com/';
        }
    }

    class TestResponse
    {
        public function setStatus($status)
        {
            $tokenMode = getenv('ICEFOX_SECURITY_TEST_MODE') === 'token';
            if ($tokenMode && empty($GLOBALS['icefox_security_token_generated'])) {
                throw new RuntimeException('Fresh CSRF token was not generated');
            }
            if (!$tokenMode && empty($GLOBALS['icefox_security_protected'])) {
                throw new RuntimeException('POST response was emitted without CSRF protection');
            }
            if ($status !== 200) {
                throw new RuntimeException('Unexpected response status');
            }
        }

        public function setContentType($contentType)
        {
            if ($contentType !== 'application/json') {
                throw new RuntimeException('POST response must be JSON');
            }
        }
    }

    class TestSecurity
    {
        public function protect()
        {
            $GLOBALS['icefox_security_protected'] = true;
        }

        public function getToken($referer)
        {
            if ($referer !== 'https://example.com/') {
                throw new RuntimeException('CSRF token must use the current referer');
            }
            $GLOBALS['icefox_security_token_generated'] = true;
            return 'fresh-token';
        }
    }

    class TestUser
    {
        public function hasLogin()
        {
            return getenv('ICEFOX_SECURITY_TEST_MODE') === 'token';
        }
    }

    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
    require_once __DIR__ . '/../plugins/IcefoxPlugin/Action.php';

    $reflection = new ReflectionClass('TypechoPlugin\\IcefoxPlugin\\Action');
    $action = $reflection->newInstanceWithoutConstructor();
    $action->request = new TestRequest();
    $action->response = new TestResponse();
    $action->action();
}
